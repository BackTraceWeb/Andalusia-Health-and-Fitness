<?php
/**
 * Authorize.Net Hosted Payment (Sandbox)
 */

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
$memberId = isset($_GET['memberId']) ? intval($_GET['memberId']) : 0;
$duesId   = isset($_GET['invoiceId']) ? intval($_GET['invoiceId']) : 0;

if ($memberId <= 0 || $duesId <= 0) {
  http_response_code(400);
  echo "Missing memberId or invoiceId.";
  exit;
}

// ------------------------------------------------------------------
// Get member & dues data
// ------------------------------------------------------------------
$stmt = $pdo->prepare("SELECT first_name,last_name,monthly_fee,payment_type FROM members WHERE id=?");
$stmt->execute([$memberId]);
$m = $stmt->fetch(PDO::FETCH_ASSOC);

$stmt2 = $pdo->prepare("SELECT amount_cents,period_start,period_end FROM dues WHERE id=?");
$stmt2->execute([$duesId]);
$d = $stmt2->fetch(PDO::FETCH_ASSOC);

if (!$m || !$d) {
  http_response_code(404);
  echo "Member or dues record not found.";
  exit;
}

// ------------------------------------------------------------------
// Payload
// ------------------------------------------------------------------
$amount = number_format(($d['amount_cents'] / 100), 2, '.', '');
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
        "description" => "Membership dues for {$m['first_name']} {$m['last_name']}"
      ],
      "billTo" => [
        "firstName" => $m['first_name'],
        "lastName"  => $m['last_name']
      ]
    ],
    "hostedPaymentSettings" => [
      "setting" => [
        [
          "settingName" => "hostedPaymentReturnOptions",
          "settingValue" => json_encode([
            "showReceipt" => true,
            "url" => "https://andalusiahealthandfitness.com/quickpay/thanks.html",
            "urlText" => "Return to Andalusia Health and Fitness",
            "cancelUrl" => "https://andalusiahealthandfitness.com/quickpay/",
            "cancelUrlText" => "Cancel"
          ], JSON_UNESCAPED_SLASHES)
        ],
        [
          "settingName" => "hostedPaymentButtonOptions",
          "settingValue" => json_encode(["text" => "Pay Now"])
        ],
        [
          "settingName" => "hostedPaymentStyleOptions",
          "settingValue" => json_encode(["bgColor" => "#000000"])
        ]
      ]
    ]
  ]
];

// ------------------------------------------------------------------
// Send request
// ------------------------------------------------------------------
$ch = curl_init(AUTH_API_URL);
curl_setopt_array($ch, [
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_POST => true,
  CURLOPT_HTTPHEADER => [
    'Content-Type: application/json',
    'Accept: application/json'
  ],
  CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_SLASHES),
  CURLOPT_TIMEOUT => 20
]);

$response = curl_exec($ch);
$curlError = curl_error($ch);
curl_close($ch);

// ------------------------------------------------------------------
// Debug
// ------------------------------------------------------------------
if ($curlError) {
  echo "<h3>cURL Error</h3><pre>{$curlError}</pre>";
  exit;
}
if (!$response) {
  echo "<h3>No response from Authorize.Net</h3>";
  exit;
}

$data = json_decode($response, true);
if (json_last_error() !== JSON_ERROR_NONE) {
  echo "<h3>JSON Decode Error:</h3><pre>" . json_last_error_msg() . "</pre>";
  echo "<pre>RAW RESPONSE:\n" . htmlspecialchars($response) . "</pre>";
  exit;
}

// ------------------------------------------------------------------
// Handle token
// ------------------------------------------------------------------
if (!isset($data['token'])) {
  echo "<h2>Authorize.Net Error</h2><pre>" . htmlspecialchars(json_encode($data, JSON_PRETTY_PRINT)) . "</pre>";
  exit;
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
  </form>
</body>
</html>
