<?php
/**
 * Authorize.Net Payment Webhook (QuickPay-only, HMAC-verified)
 * Logs every request and returns 200 OK on valid signature.
 * Triggers your existing NinjaOne script exactly as before.
 */

declare(strict_types=1);
require __DIR__ . '/../config.php'; // uses AUTH_* constants

header('Content-Type: application/json');

// === 0) Log every request (kept from your version) ===
$logDir = __DIR__ . '/../../logs';
if (!is_dir($logDir)) mkdir($logDir, 0755, true);
$logFile = "$logDir/authorize-webhook.log";

$method   = $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN';
$headers  = function_exists('getallheaders') ? getallheaders() : [];
$raw      = file_get_contents('php://input') ?: '';
$ts       = date('c');
file_put_contents($logFile,
  "=== [$ts] $method ===\nHeaders:\n" . print_r($headers, true) . "Body:\n$raw\n\n",
  FILE_APPEND
);

// === 1) Verify Authorize.Net HMAC signature ===
$hdr = $_SERVER['HTTP_X_ANET_SIGNATURE'] ?? '';
if (!str_starts_with($hdr, 'sha512=')) {
  http_response_code(400);
  echo json_encode(["ok"=>false, "error"=>"Bad signature header"]);
  exit;
}
if (!defined('AUTH_SIGNATURE_KEY_HEX') || !preg_match('/^[A-Fa-f0-9]{128}$/', AUTH_SIGNATURE_KEY_HEX)) {
  http_response_code(500);
  echo json_encode(["ok"=>false, "error"=>"Signature key missing/invalid"]);
  exit;
}
$provided = substr($hdr, 7);
$computed = hash_hmac('sha512', $raw, pack('H*', AUTH_SIGNATURE_KEY_HEX));
if (!hash_equals($computed, $provided)) {
  http_response_code(401);
  echo json_encode(["ok"=>false, "error"=>"Signature mismatch"]);
  exit;
}

// === 2) Parse event JSON ===
$data = json_decode($raw, true);
if (!$data) {
  http_response_code(400);
  echo json_encode(["ok"=>false, "error"=>"Invalid JSON"]);
  exit;
}
$eventType = $data['eventType'] ?? '';
$payload   = $data['payload']   ?? [];
$transId   = $payload['id']     ?? null;

// Only handle successful auth-capture payments
if ($eventType !== 'net.authorize.payment.authcapture.created' || !$transId) {
  echo json_encode(["ok"=>true, "ignored"=>$eventType ?: 'no-event']);
  exit;
}

// === 3) Fetch full transaction details (more reliable than webhook body) ===
function anet_get_txn(string $id): ?array {
  $req = [
    'getTransactionDetailsRequest' => [
      'merchantAuthentication' => [
        'name'           => AUTH_LOGIN_ID,
        'transactionKey' => AUTH_TRANSACTION_KEY,
      ],
      'transId' => $id,
    ],
  ];
  $ch = curl_init(AUTH_API_URL);
  curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS     => json_encode($req),
    CURLOPT_TIMEOUT        => 20,
  ]);
  $resp = curl_exec($ch);
  if ($resp === false) return null;
  $d = json_decode($resp, true);
  if (($d['messages']['resultCode'] ?? '') !== 'Ok') return null;
  return $d['transaction'] ?? null;
}

$txn = anet_get_txn($transId);
if (!$txn) {
  file_put_contents($logFile, "No txn details for $transId\n", FILE_APPEND);
  echo json_encode(["ok"=>true, "ignored"=>"no-transaction-details"]);
  exit;
}

// === 4) QuickPay-only filter ===
$isApproved    = ((int)($txn['responseCode'] ?? 0) === 1);
$invoiceNumber = $txn['order']['invoiceNumber'] ?? '';
$mdf           = $txn['merchantDefinedFields'] ?? [];
$tags = [];
foreach ($mdf as $f) { $tags[$f['name']] = $f['value']; }
$isQuickPay = (str_starts_with((string)$invoiceNumber, 'QP-') || (($tags['flow'] ?? '') === 'quickpay'));

if (!$isApproved || !$isQuickPay) {
  file_put_contents($logFile, "Ignored txn $transId (approved=$isApproved quickpay=$isQuickPay invoice=$invoiceNumber)\n", FILE_APPEND);
  echo json_encode(["ok"=>true, "ignored"=>"not-quickpay-or-not-approved"]);
  exit;
}

// Pull clean fields
$amount     = (float)($txn['authAmount'] ?? $txn['settleAmount'] ?? 0);
$memberId   = $tags['memberId']  ?? null;
$invoiceId  = $tags['invoiceId'] ?? null;
$first      = trim($txn['billTo']['firstName'] ?? '');
$last       = trim($txn['billTo']['lastName']  ?? '');

// If invoiceNumber looks like "QP-123", use that as a fallback invoiceId
if (!$invoiceId && preg_match('/^QP-(\d+)/', (string)$invoiceNumber, $m)) {
  $invoiceId = $m[1];
}

file_put_contents($logFile,
  "QuickPay APPROVED: transId=$transId amount=$amount invoiceNumber=$invoiceNumber memberId=$memberId invoiceId=$invoiceId\n",
  FILE_APPEND
);

// === 5) Resolve member if not provided (fallback to your original name lookup) ===
if (!$memberId) {
  require_once __DIR__ . '/../db.php';
  if ($first && $last) {
    $stmt = $pdo->prepare("SELECT id FROM members WHERE LOWER(first_name)=LOWER(?) AND LOWER(last_name)=LOWER(?) LIMIT 1");
    $stmt->execute([$first, $last]);
    $member = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($member) $memberId = $member['id'];
  }
  if (!$memberId) {
    http_response_code(404);
    echo json_encode(["ok"=>false, "error"=>"Member not resolved", "name"=>"$first $last"]);
    exit;
  }
}

// === 6) Authenticate with NinjaOne (unchanged, but consider moving secrets to config/env) ===
$clientId     = "qJGajqV0AiEiiRMRbGaIJ3cGQuI";
$clientSecret = "TCPQK-WLS0F4X3gqtb_KqdwMIf_4qgtRMd7h6dVkYYB2S1R1rVY7Mg";
$authUrl      = "https://api.us2.ninjarmm.com/oauth/token";

$authPayload = [
  "grant_type"    => "client_credentials",
  "client_id"     => $clientId,
  "client_secret" => $clientSecret
];

$ch = curl_init($authUrl);
curl_setopt_array($ch, [
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_POST           => true,
  CURLOPT_HTTPHEADER     => ["Content-Type: application/json"],
  CURLOPT_POSTFIELDS     => json_encode($authPayload)
]);
$authResponse = curl_exec($ch);
curl_close($ch);

$authData    = json_decode($authResponse, true);
$accessToken = $authData['access_token'] ?? null;

if (!$accessToken) {
  file_put_contents($logFile, "❌ Failed to get NinjaOne token: $authResponse\n", FILE_APPEND);
  http_response_code(500);
  echo json_encode(["ok"=>false, "error"=>"Failed to authenticate with NinjaOne"]);
  exit;
}

// === 7) Trigger AxTrax update via NinjaOne (kept from your version) ===
$ninjaPayload = [
  "device_id"   => "DESKTOP-DTDNBM0", // your AxTrax machine ID
  "script_name" => "Update AxTrax Member (Authorize.net Payment)",
  "parameters"  => [
    "memberId"  => (string)$memberId,
    "invoiceId" => (string)($invoiceId ?? ''),
    "amount"    => number_format($amount, 2, '.', '')
  ]
];

$ch = curl_init("https://api.us2.ninjarmm.com/v2/scripts/execute");
curl_setopt_array($ch, [
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_POST           => true,
  CURLOPT_HTTPHEADER     => [
    "Authorization: Bearer $accessToken",
    "Content-Type: application/json"
  ],
  CURLOPT_POSTFIELDS     => json_encode($ninjaPayload)
]);

$response = curl_exec($ch);
$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// === 8) Log result & respond ===
$logMsg = "[" . date('Y-m-d H:i:s') . "] OK trans:$transId member:$memberId invoice:$invoiceId amount:$amount HTTP:$httpcode\nResponse:$response\n\n";
file_put_contents($logFile, $logMsg, FILE_APPEND);

if ($httpcode >= 200 && $httpcode < 300) {
  echo json_encode(["ok"=>true, "memberId"=>$memberId, "invoiceId"=>$invoiceId, "amount"=>$amount]);
} else {
  http_response_code(502);
  echo json_encode(["ok"=>false, "error"=>"NinjaOne call failed", "response"=>$response]);
}
