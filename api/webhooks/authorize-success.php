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

// ── HMAC verify
$hdr = $_SERVER['HTTP_X_ANET_SIGNATURE'] ?? '';
if (!str_starts_with($hdr, 'sha512=')) {
  @file_put_contents($logFile, "CHECKPOINT: bad-signature-header\n\n", FILE_APPEND);
  http_response_code(200); echo json_encode(["ok"=>false,"error"=>"bad-signature-header"]); return;
}
if (!defined('AUTH_SIGNATURE_KEY_HEX') || !preg_match('/^[A-Fa-f0-9]{128}$/', AUTH_SIGNATURE_KEY_HEX)) {
  @file_put_contents($logFile, "CHECKPOINT: signature-key-missing\n\n", FILE_APPEND);
  http_response_code(200); echo json_encode(["ok"=>false,"error"=>"signature-key-missing"]); return;
}
$provided = substr($hdr, 7);
$computed = hash_hmac('sha512', $raw, pack('H*', AUTH_SIGNATURE_KEY_HEX));
if (!hash_equals($computed, $provided)) {
  @file_put_contents($logFile, "CHECKPOINT: signature-mismatch\n\n", FILE_APPEND);
  http_response_code(200); echo json_encode(["ok"=>false,"error"=>"signature-mismatch"]); return;
}
@file_put_contents($logFile, "CHECKPOINT: signature-ok\n", FILE_APPEND);

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

// ── NinjaOne OAuth
$clientId     = "qJGajqV0AiEiiRMRbGaIJ3cGQuI";
$clientSecret = "TCPQK-WLS0F4X3gqtb_KqdwMIf_4qgtRMd7h6dVkYYB2S1R1rVY7Mg";
$authUrl      = "https://api.us2.ninjarmm.com/oauth/token";
$execUrl      = "https://api.us2.ninjarmm.com/v2/scripts/execute";

$ch = curl_init($authUrl);
curl_setopt_array($ch, [
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_POST           => true,
  CURLOPT_HTTPHEADER     => ["Content-Type: application/json"],
  CURLOPT_POSTFIELDS     => json_encode([
    "grant_type"    => "client_credentials",
    "client_id"     => $clientId,
    "client_secret" => $clientSecret
  ], JSON_UNESCAPED_SLASHES),
  CURLOPT_TIMEOUT        => 20,
]);
$authResp = curl_exec($ch);
$errAuth  = curl_error($ch);
curl_close($ch);
$token = ($authResp && ($j = json_decode($authResp, true))) ? ($j['access_token'] ?? null) : null;

if (!$token) {
  @file_put_contents($logFile, "CHECKPOINT: ninja-auth-failed curl='$errAuth' resp='$authResp'\n\n", FILE_APPEND);
  http_response_code(200); echo json_encode(["ok"=>false,"error"=>"ninja-auth-failed"]); return;
}
@file_put_contents($logFile, "CHECKPOINT: ninja-auth-ok\n", FILE_APPEND);

// ── NinjaOne execute
$execPayload = [
  "device_id"   => "DESKTOP-DTDNBM0",
  "script_name" => "Update AxTrax Member (Authorize.net Payment)",
  "parameters"  => [
    "memberId"  => (string)$memberId,
    "invoiceId" => (string)$invoiceId,
    "amount"    => number_format($amount, 2, '.', ''),
    "transId"   => (string)$transId,
  ]
];

$ch = curl_init($execUrl);
curl_setopt_array($ch, [
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_POST           => true,
  CURLOPT_HTTPHEADER     => [
    "Authorization: Bearer $token",
    "Content-Type: application/json"
  ],
  CURLOPT_POSTFIELDS     => json_encode($execPayload, JSON_UNESCAPED_SLASHES),
  CURLOPT_TIMEOUT        => 30,
]);
$resp  = curl_exec($ch);
$code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$errEx = curl_error($ch);
curl_close($ch);

@file_put_contents($logFile, "CHECKPOINT: ninja-exec http=$code err='$errEx'\n$resp\n\n", FILE_APPEND);

// Always ACK to stop retries
http_response_code(200);
echo json_encode(["ok" => ($code>=200 && $code<300), "http"=>$code]);
