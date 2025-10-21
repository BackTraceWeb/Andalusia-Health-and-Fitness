<?php
/**
 * Authorize.Net Payment Webhook (QuickPay-only, HMAC-verified)
 * Uses payload directly and fires NinjaOne for invoices starting with QP.
 */

declare(strict_types=1);
require __DIR__ . '/../config.php';

header('Content-Type: application/json');

// ── logging
$logDir = __DIR__ . '/../../logs';
if (!is_dir($logDir)) { @mkdir($logDir, 0755, true); }
$logFile = "$logDir/authorize-webhook.log";
$raw     = file_get_contents('php://input') ?: '';
$headers = function_exists('getallheaders') ? getallheaders() : [];
@file_put_contents($logFile,
  "=== [" . date('c') . "] POST ===\nHeaders:\n" . print_r($headers, true) . "Body:\n$raw\n",
  FILE_APPEND
);
// --- HMAC verification (dual-key + sandbox bypass) ---
$hdr = $_SERVER['HTTP_X_ANET_SIGNATURE'] ?? '';
$provided = strtolower(substr($hdr, 7)); // strip "sha512="; OK if header missing—it'll just be empty

// collect candidate keys (current + optional ALT)
$keys = [];
if (defined('AUTH_SIGNATURE_KEY_HEX')) {
  $k = preg_replace('/\s+/', '', AUTH_SIGNATURE_KEY_HEX);
  if (preg_match('/^[A-Fa-f0-9]{128}$/', $k)) $keys[] = strtolower($k);
}
if (defined('AUTH_SIGNATURE_KEY_HEX_ALT')) {
  $k = preg_replace('/\s+/', '', AUTH_SIGNATURE_KEY_HEX_ALT);
  if (preg_match('/^[A-Fa-f0-9]{128}$/', $k)) $keys[] = strtolower($k);
}

$rawBody = $raw ?? file_get_contents('php://input');
$ok = false;
$tried = [];
foreach ($keys as $hex) {
  $cmp = strtolower(hash_hmac('sha512', $rawBody, hex2bin($hex)));
  $tried[] = substr($hex, 0, 12) . '…'; // log which key we tried (prefix only)
  if (hash_equals($cmp, $provided)) { $ok = true; break; }
}

if (!$ok) {
  // In SANDBOX, let it pass but log loudly so you can finish wiring NinjaOne now
  if (defined('AUTH_ENV') && AUTH_ENV === 'sandbox') {
    file_put_contents($logFile,
      "⚠️  HMAC mismatch (SANDBOX bypass ON)\nprovided=$provided\ncomputed-with=(" . implode(",", $tried) . ")\n\n",
      FILE_APPEND
    );
  } else {
    file_put_contents($logFile,
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

// ── parse event
$data = json_decode($raw, true);
if (!$data) {
  @file_put_contents($logFile, "CHECKPOINT: invalid-json\n\n", FILE_APPEND);
  http_response_code(200); echo json_encode(["ok"=>false,"error"=>"invalid-json"]); return;
}

$eventType    = $data['eventType'] ?? '';
$payload      = $data['payload'] ?? [];
$responseCode = (int)($payload['responseCode'] ?? 0);
$invoiceNumber= trim($payload['invoiceNumber'] ?? '');
$transId      = $payload['id'] ?? null;
$amount       = (float)($payload['authAmount'] ?? 0.0);

if ($eventType !== 'net.authorize.payment.authcapture.created' || !$transId) {
  @file_put_contents($logFile, "CHECKPOINT: ignored-non-target-event\n\n", FILE_APPEND);
  http_response_code(200); echo json_encode(["ok"=>true,"ignored"=>$eventType ?: 'no-event']); return;
}
if ($responseCode !== 1 || !$invoiceNumber) {
  @file_put_contents($logFile, "CHECKPOINT: ignored-not-approved-or-missing-invoice\n\n", FILE_APPEND);
  http_response_code(200); echo json_encode(["ok"=>true,"ignored"=>"not-approved-or-missing-fields"]); return;
}
@file_put_contents($logFile, "CHECKPOINT: event-ok transId=$transId invoice=$invoiceNumber amount=$amount\n", FILE_APPEND);

// ── QuickPay filter via invoice prefix
if (strncasecmp($invoiceNumber, 'QP', 2) !== 0) {
  @file_put_contents($logFile, "CHECKPOINT: ignored-not-quickpay\n\n", FILE_APPEND);
  http_response_code(200); echo json_encode(["ok"=>true,"ignored"=>"not-quickpay"]); return;
}

// Expect "QP<duesId>M<memberId>"
$invoiceId = null;  // duesId
$memberId  = null;
if (preg_match('/^QP(\d+)M(\d+)$/i', $invoiceNumber, $m)) {
  $invoiceId = $m[1];
  $memberId  = $m[2];
} else {
  @file_put_contents($logFile, "CHECKPOINT: invoice-format-unexpected: {$invoiceNumber}\n", FILE_APPEND);
}

@file_put_contents($logFile,
  sprintf("CHECKPOINT: quickpay-approved parsed invoiceId=%s memberId=%s\n", $invoiceId ?: '-', $memberId ?: '-'),
  FILE_APPEND
);

// === NinjaOne OAuth (form-encoded; smart scope fallback) ===
$clientId     = "qJGajqV0AiEiiRMRbGaIJ3cGQuI";
$clientSecret = "TCPQK-WLS0F4X3gqtb_KqdwMIf_4qgtRMd7h6dVkYYB2S1R1rVY7Mg";
$authUrl      = "https://api.us2.ninjarmm.com/oauth/token";
$execUrl      = "https://api.us2.ninjarmm.com/v2/scripts/execute";

function ninja_token($authUrl, $clientId, $clientSecret, $scopeOrNull, $logFile) {
  $fields = [
    "grant_type"    => "client_credentials",
    "client_id"     => $clientId,
    "client_secret" => $clientSecret,
  ];
  if ($scopeOrNull !== null && $scopeOrNull !== '') {
    $fields["scope"] = $scopeOrNull; // space-separated if multiple
  }
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
  file_put_contents($logFile, "CHECKPOINT: ninja-auth-attempt scope='".($scopeOrNull??'')."' http=$code err='$err' resp='$resp'\n", FILE_APPEND);
  return [null, $code, $resp];
}

// 1) Try WITHOUT scope (most tenants inherit app scopes)
[$token, $httpAuth, $authResp] = ninja_token($authUrl, $clientId, $clientSecret, null, $logFile);

// 2) If invalid_scope, try ONLY 'management' (your app has Management checked)
if (!$token && $httpAuth === 400 && stripos($authResp, 'invalid_scope') !== false) {
  [$token, $httpAuth, $authResp] = ninja_token($authUrl, $clientId, $clientSecret, 'management', $logFile);
}

if (!$token) {
  file_put_contents($logFile, "CHECKPOINT: ninja-auth-failed-final http=$httpAuth resp='$authResp'\n\n", FILE_APPEND);
  http_response_code(200); echo json_encode(["ok"=>false,"error"=>"ninja-auth-failed"]); return;
}

file_put_contents($logFile, "CHECKPOINT: ninja-auth-ok\n", FILE_APPEND);

