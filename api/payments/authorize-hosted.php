<?php
/**
 * Authorize.Net Hosted Payment Integration (Sandbox)
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
// Input
// ------------------------------------------------------------------
$memberId = isset($_GET['memberId']) ? intval($_GET['memberId']) : 0;
$duesId   = isset($_GET['invoiceId']) ? intval($_GET['invoiceId']) : 0;

if ($memberId <= 0 || $duesId <= 0) {
  http_response_code(400);
  echo "Missing memberId or invoiceId.";
  exit;
}

// ------------------------------------------------------------------
// Fetch member and dues info
// ------------------------------------------------------------------
$member = $pdo->prepare("SELECT first_name, last_name, payment_type, monthly_fee FROM members WHERE id = ?");
$member->execute([$memberId]);
$m = $member->fetch(PDO::FETCH_ASSOC);

$dues = $pdo->prepare("SELECT amount_cents, period_start, period_end FROM dues WHERE id = ?");
$dues->execute([$duesId]);
$d = $dues->fetch(PDO::FETCH_ASSOC);

if (!$m || !$d) {
  http_response_code(404);
  echo "Member or dues record not found.";
  exit;
}

// ------------------------------------------------------------------
// Build API payload
// ------------------------------------------------------------------
$loginId        = AUTH_LOGIN_ID;
$transactionKey = AUTH_TRANSACTION_KEY;
$apiUrl         = AUTH_API_URL;

$amount = number_format(($d['amount_cents'] / 100), 2, '.', '');
$invoiceNumber = "DUES{$duesId}-MEM{$memberId}";
$description = "Membership Dues for {$m['first_name']} {$m['last_name']} ({$d['period_start']}–{$d['period_end']})";

$payload = [
  'getHostedPaymentPageRequest' => [
    'merchantAuthentication' => [
      'name' => $loginId,
      'transactionKey' => $transactionKey
    ],
    'transactionRequest' => [
      'transactionType' => 'authCaptureTransaction',
      'amount' => $amount,
      'order' => [
        'invoiceNumber' => $invoiceNumber,
        'description' => $description
      ],
      'customer' => [
        'id' => (string)$memberId,
        'email' => 'noemail@andalusiahealthandfitness.com'
      ],
      'billTo' => [
        'firstName' => $m['first_name'],
        'lastName'  => $m['last_name']
      ]
    ],
    'hostedPaymentSettings' => [
      'setting' => [
        [
          'settingName'  => 'hostedPaymentReturnOptions',
          'settingValue' => json_encode([
            'showReceipt' => true,
            'url' => 'https://andalusiahealthandfitness.com/quickpay/thanks.html',
            'urlText' => 'Return to Andalusia Health & Fitness',
            'cancelUrl' => 'https://andalusiahealthandfitness.com/quickpay/',
            'cancelUrlText' => 'Cancel'
          ])
        ],
        [
          'settingName'  => 'hostedPaymentButtonOptions',
          'settingValue' => json_encode(['text' => 'Pay Now'])
        ],
        [
          'settingName'  => 'hostedPaymentStyleOptions',
          'settingValue' => json_encode(['bgColor' => '#000000'])
        ]
      ]
    ]
  ]
];

// ------------------------------------------------------------------
// Send to Authorize.Net
// ------------------------------------------------------------------
$ch = curl_init($apiUrl);
curl_setopt_array($ch, [
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_POST => true,
  CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
  CURLOPT_POSTFIELDS => json_encode($payload)
]);

$response = curl_exec($ch);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
  echo "<h2>CURL Error:</h2><pre>$curlError</pre>";
  exit;
}

$data = json_decode($response, true);

// ------------------------------------------------------------------
// Debug (when no token returned)
// ------------------------------------------------------------------
if (!isset($data['token'])) {
  echo "<h2>Authorize.Net Error</h2>";
  echo "<pre>" . htmlspecialchars(json_encode($data, JSON_PRETTY_PRINT)) . "</pre>";
  exit;
}

// ------------------------------------------------------------------
// Redirect to Hosted Payment Form
// ------------------------------------------------------------------
$token = $data['token'];
echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><title>Redirecting...</title></head>
<body onload="document.forms[0].submit()">
  <p>Redirecting to Secure Payment...</p>
  <form method="POST" action="https://test.authorize.net/payment/payment">
    <input type="hidden" name="token" value="{$token}">
  </form>
</body>
</html>
HTML;
