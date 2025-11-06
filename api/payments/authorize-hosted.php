<?php
/**
 * Authorize.Net Hosted Payment (ULTRA-stable)
 * - Only sends amount + transactionType
 * - No extra fields, no webhook coupling
 * - Uses AUTH_ENV to choose test vs prod hosted URL
 * - Logs raw token response for diagnosis
 */

declare(strict_types=1);
header('Content-Type: text/html; charset=utf-8');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../../_bootstrap.php'; // must provide pdo()

// ---------- DB ----------
$pdo = pdo();
if (!$pdo) { http_response_code(500); exit('Database not connected.'); }

// ---------- Inputs ----------
$memberId = isset($_GET['memberId']) ? (int)$_GET['memberId'] : 0;
$duesId   = isset($_GET['invoiceId']) ? (int)$_GET['invoiceId'] : 0;
if ($memberId <= 0 || $duesId <= 0) {
  http_response_code(400);
  exit('Missing memberId or invoiceId.');
}

// ---------- Lookups ----------
$stmt = $pdo->prepare('SELECT amount_cents FROM dues WHERE id=?');
$stmt->execute([$duesId]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$row) { http_response_code(404); exit('Dues record not found.'); }

$amount = number_format(((int)$row['amount_cents'] / 100), 2, '.', '');
if (!is_numeric($amount) || (float)$amount <= 0) {
  http_response_code(400);
  exit('Invalid amount for this invoice.');
}

// ---------- Hosted URL by ENV ----------
$hostedUrl = (defined('AUTH_ENV') && strtolower(AUTH_ENV) === 'sandbox')
  ? 'https://test.authorize.net/payment/payment'
  : 'https://accept.authorize.net/payment/payment';

// ---------- Minimal token request ----------
$payload = [
  "getHostedPaymentPageRequest" => [
    "merchantAuthentication" => [
      "name"           => AUTH_LOGIN_ID,
      "transactionKey" => AUTH_TRANSACTION_KEY,
    ],
    "transactionRequest" => [
      "transactionType" => "authCaptureTransaction",
      "amount"          => $amount
    ]
  ]
];

// ---------- Logging ----------
$logDir = __DIR__ . '/../../logs';
if (!is_dir($logDir)) { @mkdir($logDir, 0755, true); }
$nowTag = date('Ymd-His');
@file_put_contents("$logDir/authorize-debug-$nowTag.json", json_encode($payload, JSON_PRETTY_PRINT));

// ---------- Call ANet ----------
$ch = curl_init(AUTH_API_URL);
curl_setopt_array($ch, [
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_POST           => true,
  CURLOPT_HTTPHEADER     => ['Content-Type: application/json','Accept: application/json'],
  CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_SLASHES),
  CURLOPT_TIMEOUT        => 20,
]);
$response  = curl_exec($ch);
$httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

@file_put_contents(
  "$logDir/authorize-token-response.log",
  "[".date('c')."] HTTP=$httpCode ENV=".(defined('AUTH_ENV')?AUTH_ENV:'?')." URL=".AUTH_API_URL." AMT=$amount DUES=$duesId MEM=$memberId\n".$response."\n\n",
  FILE_APPEND
);

if ($curlError) {
  http_response_code(502);
  exit("<h3>cURL Error</h3><pre>" . htmlspecialchars($curlError) . "</pre>");
}
if (!$response) {
  http_response_code(502);
  exit('<h3>No response from Authorize.Net</h3>');
}

$data = json_decode(preg_replace('/^\xEF\xBB\xBF/', '', $response), true);
if (json_last_error() !== JSON_ERROR_NONE) {
  http_response_code(502);
  exit('<h3>JSON Decode Error:</h3><pre>' . htmlspecialchars(json_last_error_msg()) . '</pre>');
}

if (empty($data['token'])) {
  http_response_code(400);
  exit('<h2>Authorize.Net Error</h2><pre>' .
       htmlspecialchars(json_encode($data, JSON_PRETTY_PRINT)) .
       '</pre>');
}

$token = htmlspecialchars($data['token'], ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><title>Redirecting…</title></head>
<body onload="document.forms[0].submit()">
  <p>Redirecting to Secure Payment…</p>
  <form method="POST" action="<?= htmlspecialchars($hostedUrl, ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="token" value="<?= $token ?>">
    <noscript><button type="submit">Continue</button></noscript>
  </form>
</body>
</html>
