<?php
/**
 * Authorize.Net Payment Webhook (QuickPay-only, HMAC-verified)
 * - Uses webhook payload directly (no GetTransactionDetails)
 * - Fires NinjaOne script only when invoiceNumber starts with QP
 * - Verbose logging; always returns 200 to Authorize.Net
 */

declare(strict_types=1);
require __DIR__ . '/../config.php';

header('Content-Type: application/json');

// ─────────────────────────── setup logging ───────────────────────────
$logDir = __DIR__ . '/../../logs';
if (!is_dir($logDir)) { @mkdir($logDir, 0755, true); }
$logFile = "$logDir/authorize-webhook.log";

$raw     = file_get_contents('php://input') ?: '';
$headers = function_exists('getallheaders') ? getallheaders() : [];
@file_put_contents(
  $logFile,
  "=== [" . date('c') . "] POST ===\nHeaders:\n" . print_r($headers, true) . "Body:\n$raw\n",
  FILE_APPEND
);

// ───────────────── HMAC verification (dual-key + sandbox bypass) ─────────────
$hdr = $_SERVER['HTTP_X_ANET_SIGNATURE'] ?? '';
$provided = strtolower(substr($hdr, 7)); // strip "sha512=" (lowercase for compare)

$keys = [];
if (defined('AUTH_SIGNATURE_KEY_HEX')) {
  $k = preg_replace('/\s+/', '', AUTH_SIGNATURE_KEY_HEX);
  if (preg_match('/^[A-Fa-f0-9]{128}$/', $k)) $keys[] = strtolower($k);
}
if (defined('AUTH_SIGNATURE_KEY_HEX_ALT')) {
  $k = preg_replace('/\s+/', '', AUTH_SIGNATURE_KEY_HEX_ALT);
  if (preg_match('/^[A-Fa-f0-9]{128}$/', $k)) $keys[] = strtolower($k);
}

$ok = false;
$tried = [];
foreach ($keys as $hex) {
  $cmp = strtolower(hash_hmac('sha512', $raw, hex2bin($hex)));
  $tried[] = substr($hex, 0, 12) . '…'; // identify which key we tried (prefix only)
  if (hash_equals($cmp, $provided)) { $ok = true; break; }
}

if (!$ok) {
  if (defined('AUTH_ENV') && AUTH_ENV === 'sandbox') {
    // proceed in sandbox so we can finish the integration
    file_put_contents(
      $logFile,
      "⚠️  HMAC mismatch (SANDBOX bypass ON)\nprovided=$provided\ncomputed-with=(" . implode(",", $tried) . ")\n\n",
      FILE_APPEND
    );
  } else {
    file_put_contents(
      $logFile,
      "❌ HMAC mismatch (PROD - blocking)\nprovided=$provided\ncomputed-with=(" . implode(",", $tried) . ")\n\n",
      FILE_APPEND
    );
    http_response_code(200);
    echo json_encode(["ok"=>false,"error"=>"signature-mismatch"]);
    return;
  }
} else {
  file_put_contents($logFile, "CHECKPOINT: signature-ok using key ".($tried ? $tried[count($tried)-1] : 'n/a')."\n", FILE_APPEND);
}

// ────────────────────────── parse & gate event ───────────────────────────────
$data = json_decode($raw, true);
if (!$data) {
  @file_put_contents($logFile, "CHECKPOINT: invalid-json\n\n", FILE_APPEND);
  http_response_code(200); echo json_encode(["ok"=>false,"error"=>"invalid-json"]); return;
}

$eventType     = $data['eventType'] ?? '';
$payload       = $data['payload']   ?? [];
$responseCode  = (int)($payload['responseCode'] ?? 0);
$invoiceNumber = trim($payload['invoiceNumber'] ?? '');
$transId       = $payload['id'] ?? null;
$amount        = (float)($payload['authAmount'] ?? 0.0);

if ($eventType !== 'net.authorize.payment.authcapture.created' || !$transId) {
  @file_put_contents($logFile, "CHECKPOINT: ignored-non-target-event\n\n", FILE_APPEND);
  http_response_code(200); echo json_encode(["ok"=>true,"ignored"=>$eventType ?: 'no-event']); return;
}
if ($responseCode !== 1 || !$invoiceNumber) {
  @file_put_contents($logFile, "CHECKPOINT: ignored-not-approved-or-missing-invoice\n\n", FILE_APPEND);
  http_response_code(200); echo json_encode(["ok"=>true,"ignored"=>"not-approved-or-missing-fields"]); return;
}
@file_put_contents($logFile, "CHECKPOINT: event-ok transId=$transId invoice=$invoiceNumber amount=$amount\n", FILE_APPEND);

// QuickPay-only filter: "QP<duesId>M<memberId>"
if (strncasecmp($invoiceNumber, 'QP', 2) !== 0) {
  @file_put_contents($logFile, "CHECKPOINT: ignored-not-quickpay\n\n", FILE_APPEND);
  http_response_code(200); echo json_encode(["ok"=>true,"ignored"=>"not-quickpay"]); return;
}

$invoiceId = null; // duesId
$memberId  = null;
if (preg_match('/^QP(\d+)M(\d+)$/i', $invoiceNumber, $m)) {
  $invoiceId = $m[1];
  $memberId  = $m[2];
} else {
  @file_put_contents($logFile, "CHECKPOINT: invoice-format-unexpected: {$invoiceNumber}\n", FILE_APPEND);
}

@file_put_contents(
  $logFile,
  sprintf("CHECKPOINT: quickpay-approved parsed invoiceId=%s memberId=%s\n", $invoiceId ?: '-', $memberId ?: '-'),
  FILE_APPEND
);

// ───────────────────────────── NinjaOne OAuth ────────────────────────────────
$clientId     = "qJGajqV0AiEiiRMRbGaIJ3cGQuI";
$clientSecret = "TCPQK-WLS0F4X3gqtb_KqdwMIf_4qgtRMd7h6dVkYYB2S1R1rVY7Mg";
$authUrl      = "https://api.us2.ninjarmm.com/oauth/token";

$token = null;

// helper to request token with optional scope
$fetchToken = function (?string $scope) use ($authUrl, $clientId, $clientSecret, $logFile): array {
  $fields = [
    "grant_type"    => "client_credentials",
    "client_id"     => $clientId,
    "client_secret" => $clientSecret,
  ];
  if ($scope !== null && $scope !== '') $fields["scope"] = $scope;

  $body = http_build_query($fields, '', '&');

  $ch = curl_init($authUrl);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_HTTPHEADER     => ["Content-Type: application/x-www-form-urlencoded"],
    CURLOPT_POSTFIELDS     => $body,
    CURLOPT_TIMEOUT        => 20,
  ]);
  $resp = curl_exec($ch);
  $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $err  = curl_error($ch);
  curl_close($ch);

  if ($resp && ($j = json_decode($resp, true)) && !empty($j['access_token'])) {
    return [$j['access_token'], $code, $resp];
  }
  file_put_contents($logFile, "CHECKPOINT: ninja-auth-attempt scope='".($scope??'')."' http=$code err='$err' resp='$resp'\n", FILE_APPEND);
  return [null, $code, $resp];
};

// 1) try without scope (many tenants inherit app scopes)
[$token, $httpAuth, $authResp] = $fetchToken(null);

// 2) if invalid_scope, try ONLY 'management' (your app shows Management checked)
if (!$token && $httpAuth === 400 && stripos($authResp, 'invalid_scope') !== false) {
  [$token, $httpAuth, $authResp] = $fetchToken('management');
}

if (!$token) {
  file_put_contents($logFile, "CHECKPOINT: ninja-auth-failed-final http=$httpAuth resp='$authResp'\n\n", FILE_APPEND);
  http_response_code(200); echo json_encode(["ok"=>false,"error"=>"ninja-auth-failed"]); return;
}
file_put_contents($logFile, "CHECKPOINT: ninja-auth-ok\n", FILE_APPEND);

// ───────────────────────── NinjaOne script execute ───────────────────────────
// If this identifier is actually a hostname, some tenants require numeric deviceId.
// If you know the numeric ID, replace it here.
$deviceIdentifier = "DESKTOP-DTDNBM0";

$execEndpoints = [
  "https://api.us2.ninjarmm.com/v2/scripts/execute",
  "https://api.us2.ninjarmm.com/v2/automation/scripts/execute",
];

$variants = [
  // snake_case single device
  [
    "label"   => "A1",
    "payload" => [
      "device_id"   => $deviceIdentifier,
      "script_name" => "Update AxTrax Member (Authorize.net Payment)",
      "parameters"  => [
        "memberId"  => (string)$memberId,
        "invoiceId" => (string)$invoiceId,
        "amount"    => number_format($amount, 2, '.', ''),
        "transId"   => (string)$transId,
      ],
    ],
  ],
  // camelCase single device
  [
    "label"   => "A2",
    "payload" => [
      "deviceId"   => $deviceIdentifier,
      "scriptName" => "Update AxTrax Member (Authorize.net Payment)",
      "parameters" => [
        "memberId"  => (string)$memberId,
        "invoiceId" => (string)$invoiceId,
        "amount"    => number_format($amount, 2, '.', ''),
        "transId"   => (string)$transId,
      ],
    ],
  ],
  // camelCase array of devices
  [
    "label"   => "B1",
    "payload" => [
      "deviceIds"  => [$deviceIdentifier],
      "scriptName" => "Update AxTrax Member (Authorize.net Payment)",
      "parameters" => [
        "memberId"  => (string)$memberId,
        "invoiceId" => (string)$invoiceId,
        "amount"    => number_format($amount, 2, '.', ''),
        "transId"   => (string)$transId,
      ],
    ],
  ],
  // snake_case array of devices
  [
    "label"   => "B2",
    "payload" => [
      "device_ids" => [$deviceIdentifier],
      "script_name"=> "Update AxTrax Member (Authorize.net Payment)",
      "parameters" => [
        "memberId"  => (string)$memberId,
        "invoiceId" => (string)$invoiceId,
        "amount"    => number_format($amount, 2, '.', ''),
        "transId"   => (string)$transId,
      ],
    ],
  ],
];

$tryExec = function (string $url, array $payload, string $label) use ($token, $logFile) {
  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_HTTPHEADER     => [
      "Authorization: Bearer $token",
      "Content-Type: application/json"
    ],
    CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_SLASHES),
    CURLOPT_TIMEOUT        => 30,
  ]);
  $resp = curl_exec($ch);
  $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $err  = curl_error($ch);
  curl_close($ch);

  file_put_contents(
    $logFile,
    "CHECKPOINT: ninja-exec try={$label} url={$url} http={$code} err='{$err}'\npayload=" .
    json_encode($payload) . "\nresp={$resp}\n",
    FILE_APPEND
  );
  return [$code, $resp];
};

$ok = false; $finalCode = 0; $finalResp = ''; $attempts = 0;
foreach ($execEndpoints as $eIdx => $url) {
  foreach ($variants as $v) {
    [$code, $resp] = $tryExec($url, $v['payload'], "{$v['label']}@{$eIdx}");
    $attempts++; $finalCode = $code; $finalResp = $resp;
    if ($code >= 200 && $code < 300) { $ok = true; break 2; }
  }
}

file_put_contents(
  $logFile,
  "CHECKPOINT: ninja-exec-final ok=" . ($ok ? 'true' : 'false') . " attempts={$attempts} http={$finalCode}\n\n",
  FILE_APPEND
);

// Always ACK to Authorize.Net
http_response_code(200);
echo json_encode(["ok" => $ok, "http" => $finalCode, "attempts" => $attempts]);
