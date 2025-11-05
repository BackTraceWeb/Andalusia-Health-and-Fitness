<?php
/**
 * Authorize.Net Hosted Payment (Sandbox)
 * - urlMethod=GET so your return handler sees query params
 * - userFields to carry memberId/invoiceId through receipts/webhooks
 * - response logging for faster debugging
 * - acceptHostedUrl() guard maps apitest→test.accept to avoid prod flips
 */
declare(strict_types=1);
header('Content-Type: text/html; charset=utf-8');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../../_bootstrap.php';

/**
 * Map the form POST target to the correct Accept Hosted domain.
 * If AUTH_API_URL points at apitest, we force the "test" pay page.
 * Otherwise it's the live pay page.
 */
function acceptHostedUrl(): string {
    if (defined('AUTH_API_URL') && stripos(AUTH_API_URL, 'apitest.authorize.net') !== false) {
        return 'https://test.authorize.net/payment/payment';
    }
    return 'https://accept.authorize.net/payment/payment';
}

$pdo = pdo();
if (!$pdo) {
    http_response_code(500);
    exit('Database not connected.');
}

$memberId = isset($_GET['memberId']) ? (int)$_GET['memberId'] : 0;
$duesId   = isset($_GET['invoiceId']) ? (int)$_GET['invoiceId'] : 0;
if ($memberId <= 0 || $duesId <= 0) {
    http_response_code(400);
    exit('Missing memberId or invoiceId.');
}

$stmt = $pdo->prepare('SELECT first_name,last_name,email,zip FROM members WHERE id=?');
$stmt->execute([$memberId]);
$m = $stmt->fetch(PDO::FETCH_ASSOC);

$stmt2 = $pdo->prepare('SELECT amount_cents,period_start,period_end FROM dues WHERE id=?');
$stmt2->execute([$duesId]);
$d = $stmt2->fetch(PDO::FETCH_ASSOC);

if (!$m || !$d) {
    http_response_code(404);
    exit('Member or dues record not found.');
}

$amount = number_format(($d['amount_cents'] / 100), 2, '.', '');

// Safe, short invoice (<=20 alnum)
$invoice = substr(preg_replace('/[^A-Za-z0-9]/', '', "QP{$duesId}M{$memberId}"), 0, 20);

// Build return URL
$returnUrl = "https://andalusiahealthandfitness.com/api/payments/authorize-return.php?memberId=$memberId&invoiceId=$duesId";

$payload = [
  "getHostedPaymentPageRequest" => [
    "merchantAuthentication" => [
      "name"           => AUTH_LOGIN_ID,
      "transactionKey" => AUTH_TRANSACTION_KEY,
    ],
    "transactionRequest" => [
      "transactionType" => "authCaptureTransaction",
      "amount"          => $amount,
      "order" => [
        "invoiceNumber" => $invoice,
        "description"   => "Gym Membership Dues",
      ],
      "customer" => [
        "email" => $m['email'] ?? '',
      ],
      // Carry your identifiers through to webhooks/settlement
      "userFields" => [
        "userField" => [
          [ "name" => "memberId",      "value" => (string)$memberId ],
          [ "name" => "invoiceId",     "value" => (string)$duesId ],
          [ "name" => "invoiceNumber", "value" => $invoice ],
        ]
      ],
      "billTo" => [
        "firstName" => $m['first_name'] ?? '',
        "lastName"  => $m['last_name']  ?? '',
        "zip"       => $m['zip']        ?? '',
      ],
    ],
    "hostedPaymentSettings" => [
      "setting" => [
        [
          "settingName"  => "hostedPaymentReturnOptions",
          "settingValue" => json_encode([
            "showReceipt" => false,
            "url"         => $returnUrl,
            "urlMethod"   => "GET",
            "cancelUrl"   => "https://andalusiahealthandfitness.com/quickpay/"
          ], JSON_UNESCAPED_SLASHES),
        ],
        [
          "settingName"  => "hostedPaymentPaymentOptions",
          "settingValue" => json_encode([
            "cardCodeRequired" => true
          ], JSON_UNESCAPED_SLASHES),
        ],
        [
          "settingName"  => "hostedPaymentOrderOptions",
          "settingValue" => json_encode([
            "show" => true
          ], JSON_UNESCAPED_SLASHES),
        ],
      ],
    ],
  ],
];

// --- logging (request + response) ---
$logDir = __DIR__ . '/../../logs';
if (!is_dir($logDir)) { @mkdir($logDir, 0755, true); }
@file_put_contents("$logDir/authorize-debug.json",
    json_encode($payload, JSON_PRETTY_PRINT) . PHP_EOL);

$ch = curl_init(AUTH_API_URL); // Sandbox: https://apitest.authorize.net/xml/v1/request.api
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json','Accept: application/json'],
    CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_SLASHES),
    CURLOPT_TIMEOUT        => 20,
]);
$response  = curl_exec($ch);
$curlError = curl_error($ch);
curl_close($ch);

@file_put_contents("$logDir/authorize-response.json",
    "[" . date('c') . "]\n" . (string)$response . "\n", FILE_APPEND);

if ($curlError) {
    exit("<h3>cURL Error</h3><pre>" . htmlspecialchars($curlError, ENT_QUOTES, 'UTF-8') . "</pre>");
}
if (!$response)  {
    exit('<h3>No response from Authorize.Net</h3>');
}

$data = json_decode(preg_replace('/^\xEF\xBB\xBF/', '', $response), true);
if (json_last_error() !== JSON_ERROR_NONE) {
    exit('<h3>JSON Decode Error:</h3><pre>' . htmlspecialchars(json_last_error_msg(), ENT_QUOTES, 'UTF-8') . '</pre>');
}

if (empty($data['token'])) {
    exit('<h2>Authorize.Net Error</h2><pre>' .
         htmlspecialchars(json_encode($data, JSON_PRETTY_PRINT), ENT_QUOTES, 'UTF-8') .
         '</pre>');
}

$token = htmlspecialchars($data['token'], ENT_QUOTES, 'UTF-8');
$payUrl = acceptHostedUrl();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Redirecting...</title>
</head>
<body onload="document.forms[0].submit()">
  <p>Redirecting to Secure Payment...</p>
  <form method="POST" action="<?= htmlspecialchars($payUrl, ENT_QUOTES, 'UTF-8') ?>">
    <!-- LIVE would be https://accept.authorize.net/payment/payment -->
    <input type="hidden" name="token" value="<?= $token ?>">
    <noscript><button type="submit">Continue</button></noscript>
  </form>
</body>
</html>
