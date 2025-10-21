<?php
/**
 * Authorize.Net Hosted Payment Page (QuickPay Integration)
 * Auto-redirects to webhook on successful payment.
 */

declare(strict_types=1);
header('Content-Type: text/html; charset=utf-8');

// ------------------------------------------------------------------
// Includes
// ------------------------------------------------------------------
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../../_bootstrap.php';

$pdo = pdo();
if (!$pdo) {
    http_response_code(500);
    echo "Database not connected.";
    exit;
}

// ------------------------------------------------------------------
// Inputs
// ------------------------------------------------------------------
$memberId = isset($_GET['memberId']) ? (int)$_GET['memberId'] : 0;
$duesId   = isset($_GET['invoiceId']) ? (int)$_GET['invoiceId'] : 0;

if ($memberId <= 0 || $duesId <= 0) {
    http_response_code(400);
    echo "Missing memberId or invoiceId.";
    exit;
}

// ------------------------------------------------------------------
// Get member & dues data
// ------------------------------------------------------------------
$stmt = $pdo->prepare("SELECT first_name,last_name,email,zip FROM members WHERE id=?");
$stmt->execute([$memberId]);
$m = $stmt->fetch(PDO::FETCH_ASSOC);

$stmt2 = $pdo->prepare("SELECT amount_cents FROM dues WHERE id=?");
$stmt2->execute([$duesId]);
$d = $stmt2->fetch(PDO::FETCH_ASSOC);

if (!$m || !$d) {
    http_response_code(404);
    echo "Member or dues record not found.";
    exit;
}

// ------------------------------------------------------------------
// Build payload
// ------------------------------------------------------------------
$amount  = number_format(($d['amount_cents'] / 100), 2, '.', '');
$invoice = "DUES{$duesId}-MEM{$memberId}";

$payload = [
    "getHostedPaymentPageRequest" => [
        "merchantAuthentication" => [
            "name" => AUTH_LOGIN_ID,
            "transactionKey" => AUTH_TRANSACTION_KEY
        ],
        "transactionRequest" => [
            "transactionType" => "authCaptureTransaction",
            "amount" => $amount,
            "order" => [
                "invoiceNumber" => $invoice,
                "description"   => "Membership dues for {$m['first_name']} {$m['last_name']}"
            ],
            "billTo" => [
                "firstName" => $m['first_name'],
                "lastName"  => $m['last_name'],
                "zip"       => $m['zip'] ?? ''
            ]
        ],
        "hostedPaymentSettings" => [
            "setting" => [
                [
                    "settingName"  => "hostedPaymentReturnOptions",
                    "settingValue" => json_encode([
                        "showReceipt" => false,
                        "url"         => "https://andalusiahealthandfitness.com/api/payments/authorize-return.php?memberId={$memberId}&invoiceId={$duesId}",
                        "cancelUrl"   => "https://andalusiahealthandfitness.com/quickpay/",
                        "linkMethod"  => "POST"
                    ], JSON_UNESCAPED_SLASHES)
                ],
                [
                    "settingName"  => "hostedPaymentButtonOptions",
                    "settingValue" => '{"text":"Pay Now"}'
                ],
                [
                    "settingName"  => "hostedPaymentStyleOptions",
                    "settingValue" => '{"bgColor":"#000000"}'
                ]
            ]
        ]
    ]
];

// ------------------------------------------------------------------
// Send request to Authorize.Net
// ------------------------------------------------------------------
$ch = curl_init(AUTH_API_URL);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Accept: application/json'
    ],
    CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_SLASHES),
    CURLOPT_TIMEOUT        => 20
]);

$response  = curl_exec($ch);
$curlError = curl_error($ch);
curl_close($ch);

// ------------------------------------------------------------------
// Logging
// ------------------------------------------------------------------
$logDir = __DIR__ . '/../../logs';
if (!is_dir($logDir)) mkdir($logDir, 0755, true);
file_put_contents("$logDir/authorize-hosted.log",
    date('c') . " Payload:\n" . json_encode($payload, JSON_PRETTY_PRINT) .
    "\nResponse:\n" . $response . "\n\n",
    FILE_APPEND
);

// ------------------------------------------------------------------
// Handle response
// ------------------------------------------------------------------
if ($curlError) {
    echo "<h3>cURL Error</h3><pre>{$curlError}</pre>";
    exit;
}

if (!$response) {
    echo "<h3>No response from Authorize.Net</h3>";
    exit;
}

$response = preg_replace('/^\xEF\xBB\xBF/', '', $response);
$data = json_decode($response, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    echo "<h3>JSON Decode Error:</h3><pre>" . json_last_error_msg() . "</pre>";
    echo "<pre>RAW RESPONSE:\n" . htmlspecialchars($response) . "</pre>";
    exit;
}

if (!isset($data['token'])) {
    echo "<h2>Authorize.Net Error</h2><pre>" .
         htmlspecialchars(json_encode($data, JSON_PRETTY_PRINT)) .
         "</pre>";
    exit;
}

// ------------------------------------------------------------------
// Redirect to Hosted Payment Page
// ------------------------------------------------------------------
$token = htmlspecialchars($data['token']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Redirecting to Secure Payment...</title>
</head>
<body onload="document.forms[0].submit()">
  <p>Redirecting to Secure Payment...</p>
  <form method="POST" action="https://test.authorize.net/payment/payment">
    <input type="hidden" name="token" value="<?= $token ?>">
    <noscript><button type="submit">Continue</button></noscript>
  </form>
</body>
</html>
