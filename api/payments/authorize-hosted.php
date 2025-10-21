<?php
/**
 * Authorize.Net Hosted Payment (Sandbox)
 * Stable baseline — loads payment page and supports webhook return.
 */
declare(strict_types=1);
header('Content-Type: text/html; charset=utf-8');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../../_bootstrap.php';

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
    ],

    // 🔻 ADD THIS: lets the webhook positively identify QuickPay
    "merchantDefinedFields" => [
        ["name" => "flow",     "value" => "quickpay"],
        ["name" => "memberId", "value" => (string)$memberId],
        ["name" => "invoiceId","value" => (string)$duesId]
    ]
],

        "hostedPaymentSettings" => [
            "setting" => [
                [
                    "settingName"  => "hostedPaymentReturnOptions",
                    "settingValue" => json_encode([
                        "showReceipt"   => false,
                        "url"           => "https://andalusiahealthandfitness.com/api/payments/authorize-return.php?memberId={$memberId}&invoiceId={$duesId}",
                        "urlText"       => "",
                        "cancelUrl"     => "https://andalusiahealthandfitness.com/quickpay/",
                        "cancelUrlText" => "Cancel",
                        "linkMethod"    => "POST"
                    ], JSON_UNESCAPED_SLASHES)
                ],
                [
                    "settingName"  => "hostedPaymentButtonOptions",
                    "settingValue" => json_encode(["text" => "Pay Now"], JSON_UNESCAPED_SLASHES)
                ],
                [
                    "settingName"  => "hostedPaymentStyleOptions",
                    "settingValue" => json_encode(["bgColor" => "#000000"], JSON_UNESCAPED_SLASHES)
                ]
            ]
        ]
    ]
];

$logDir = __DIR__ . '/../../logs';
if (!is_dir($logDir)) mkdir($logDir, 0755, true);
file_put_contents("$logDir/authorize-debug.json", json_encode($payload, JSON_PRETTY_PRINT));

$ch = curl_init(AUTH_API_URL);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json','Accept: application/json'],
    CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_SLASHES),
    CURLOPT_TIMEOUT        => 20
]);
$response  = curl_exec($ch);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) exit("<h3>cURL Error</h3><pre>$curlError</pre>");
if (!$response)  exit('<h3>No response from Authorize.Net</h3>');

$data = json_decode(preg_replace('/^\xEF\xBB\xBF/', '', $response), true);
if (json_last_error() !== JSON_ERROR_NONE) {
    exit('<h3>JSON Decode Error:</h3><pre>' . json_last_error_msg() . '</pre>');
}

if (empty($data['token'])) {
    exit('<h2>Authorize.Net Error</h2><pre>' .
         htmlspecialchars(json_encode($data, JSON_PRETTY_PRINT)) .
         '</pre>');
}

$token = htmlspecialchars($data['token']);
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><title>Redirecting...</title></head>
<body onload="document.forms[0].submit()">
<p>Redirecting to Secure Payment...</p>
<form method="POST" action="https://test.authorize.net/payment/payment">
  <input type="hidden" name="token" value="<?= $token ?>">
  <noscript><button type="submit">Continue</button></noscript>
</form>
</body>
</html>
