<?php
require_once __DIR__ . '/../config.php';

// 1️⃣ Set transaction details (you can make these dynamic)
$amount = isset($_GET['amount']) ? floatval($_GET['amount']) : 10.00;
$invoice = isset($_GET['invoice']) ? $_GET['invoice'] : 'TEST-' . time();

// 2️⃣ Build the request for Authorize.Net
$request = [
  "getHostedPaymentPageRequest" => [
    "merchantAuthentication" => [
      "name" => AUTH_LOGIN_ID,
      "transactionKey" => AUTH_TRANSACTION_KEY
    ],
    "transactionRequest" => [
      "transactionType" => "authCaptureTransaction",
      "amount" => $amount,
      "order" => ["invoiceNumber" => $invoice, "description" => "Andalusia Health & Fitness Payment"]
    ],
    "hostedPaymentSettings" => [
      "setting" => [
        ["settingName" => "hostedPaymentReturnOptions", "settingValue" => json_encode([
          "showReceipt" => false,
          "url" => "https://andalusiahealthandfitness.com/api/webhooks/authorize-success.php",
          "urlText" => "Return to Andalusia Health & Fitness",
          "cancelUrl" => "https://andalusiahealthandfitness.com/quickpay/",
          "cancelUrlText" => "Cancel"
        ])],
        ["settingName" => "hostedPaymentButtonOptions", "settingValue" => json_encode(["text" => "Pay Securely"])],
        ["settingName" => "hostedPaymentStyleOptions", "settingValue" => json_encode(["bgColor" => "#ffffff"])],
      ]
    ]
  ]
];

// 3️⃣ Send it to Authorize.Net Sandbox API
$ch = curl_init(AUTH_API_URL);
curl_setopt_array($ch, [
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_POST => true,
  CURLOPT_HTTPHEADER => ["Content-Type: application/json"],
  CURLOPT_POSTFIELDS => json_encode($request)
]);
$response = curl_exec($ch);
curl_close($ch);

$data = json_decode($response, true);
$token = $data['token'] ?? null;

// 4️⃣ Output the form redirect
if ($token) {
  echo "<form id='PayForm' method='post' action='https://accept.authorize.net/payment/payment'>";
  echo "<input type='hidden' name='token' value='{$token}'/>";
  echo "</form>";
  echo "<script>document.getElementById('PayForm').submit();</script>";
} else {
  echo "<pre>❌ Error getting payment token:\n$response</pre>";
}
