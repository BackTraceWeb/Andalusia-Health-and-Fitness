<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../db_connect.php'; // make sure this points to your existing PDO setup

// Capture member and invoice IDs from GET or POST
$memberId  = $_GET['memberId']  ?? $_POST['memberId']  ?? null;
$invoiceId = $_GET['invoiceId'] ?? $_POST['invoiceId'] ?? null;

// Validate inputs
if (!$memberId || !$invoiceId) {
  http_response_code(400);
  exit("Missing memberId or invoiceId");
}

// Look up invoice and member info
$stmt = $pdo->prepare("
  SELECT i.amount_cents, i.id AS invoice_id, i.period_start, i.period_end,
         m.first_name, m.last_name, m.email
  FROM invoices i
  JOIN members m ON m.id = i.member_id
  WHERE i.id = :invoiceId AND m.id = :memberId
");
$stmt->execute(['invoiceId' => $invoiceId, 'memberId' => $memberId]);
$invoice = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$invoice) {
  http_response_code(404);
  exit("Invoice not found for this member");
}

// Convert to dollars
$amount = round($invoice['amount_cents'] / 100, 2);
$invoiceNumber = $invoice['invoice_id'];
$description   = "Andalusia Health & Fitness Membership (" .
                 ($invoice['period_start'] ?? '') . "–" .
                 ($invoice['period_end'] ?? '') . ")";

// Build the Authorize.Net request
$request = [
  "getHostedPaymentPageRequest" => [
    "merchantAuthentication" => [
      "name" => AUTH_LOGIN_ID,
      "transactionKey" => AUTH_TRANSACTION_KEY
    ],
    "transactionRequest" => [
      "transactionType" => "authCaptureTransaction",
      "amount" => $amount,
      "order" => [
        "invoiceNumber" => $invoiceNumber,
        "description"   => $description
      ],
      "customer" => [
        "id" => $memberId,
        "email" => $invoice['email']
      ]
    ],
    "hostedPaymentSettings" => [
      "setting" => [
        [
          "settingName" => "hostedPaymentReturnOptions",
          "settingValue" => json_encode([
            "showReceipt" => false,
            "url" => "https://andalusiahealthandfitness.com/api/webhooks/authorize-success.php",
            "urlText" => "Return to Andalusia Health & Fitness",
            "cancelUrl" => "https://andalusiahealthandfitness.com/quickpay/",
            "cancelUrlText" => "Cancel"
          ])
        ],
        [
          "settingName" => "hostedPaymentButtonOptions",
          "settingValue" => json_encode(["text" => "Pay Securely"])
        ],
        [
          "settingName" => "hostedPaymentStyleOptions",
          "settingValue" => json_encode(["bgColor" => "#ffffff"])
        ],
        [
          "settingName" => "hostedPaymentBillingAddressOptions",
          "settingValue" => json_encode(["show" => false])
        ]
      ]
    ]
  ]
];

// Send to Authorize.Net
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

// Log for debugging
$logDir = __DIR__ . '/../../logs';
if (!is_dir($logDir)) mkdir($logDir, 0755, true);
file_put_contents("$logDir/authorize-hosted.log", "[".date('Y-m-d H:i:s')."] $response\n", FILE_APPEND);

// Redirect or show error
if ($token) {
  echo "<form id='PayForm' method='post' action='https://accept.authorize.net/payment/payment'>";
  echo "<input type='hidden' name='token' value='{$token}'/>";
  echo "</form>";
  echo "<script>document.getElementById('PayForm').submit();</script>";
} else {
  echo "<pre>❌ Error getting payment token:\n$response</pre>";
}
