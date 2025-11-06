<?php
/**
 * Authorize.Net Hosted Payment (ULTRA-stable with iframe)
 * - Only sends amount + transactionType
 * - Displays form embedded in iframe (no redirect)
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

$stmt2 = $pdo->prepare('SELECT first_name,last_name FROM members WHERE id=?');
$stmt2->execute([$memberId]);
$member = $stmt2->fetch(PDO::FETCH_ASSOC);

$amount = number_format(((int)$row['amount_cents'] / 100), 2, '.', '');
if (!is_numeric($amount) || (float)$amount <= 0) {
  http_response_code(400);
  exit('Invalid amount for this invoice.');
}

$memberName = $member ? trim(($member['first_name'] ?? '') . ' ' . ($member['last_name'] ?? '')) : 'Member';
$invoice = "DUES{$duesId}-MEM{$memberId}";

// ---------- Hosted URL by ENV ----------
$hostedUrl = (defined('AUTH_ENV') && strtolower(AUTH_ENV) === 'sandbox')
  ? 'https://test.authorize.net/payment/payment'
  : 'https://accept.authorize.net/payment/payment';

// ---------- Token request with minimal settings ----------
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
        "description"   => "Gym Membership Dues"
      ]
    ],
    "hostedPaymentSettings" => [
      "setting" => [
        [
          "settingName"  => "hostedPaymentReturnOptions",
          "settingValue" => json_encode([
            "showReceipt" => false,
            "url"         => $returnUrl,
            "cancelUrl"   => "https://andalusiahealthandfitness.com/quickpay/"
          ], JSON_UNESCAPED_SLASHES)
        ],
        [
          "settingName"  => "hostedPaymentButtonOptions",
          "settingValue" => json_encode([
            "text" => "Pay"
          ], JSON_UNESCAPED_SLASHES)
        ],
        [
          "settingName"  => "hostedPaymentPaymentOptions",
          "settingValue" => json_encode([
            "cardCodeRequired" => true,
            "showCreditCard" => true,
            "showBankAccount" => false
          ], JSON_UNESCAPED_SLASHES)
        ],
        [
          "settingName"  => "hostedPaymentSecurityOptions",
          "settingValue" => json_encode([
            "captcha" => false
          ], JSON_UNESCAPED_SLASHES)
        ],
        [
          "settingName"  => "hostedPaymentOrderOptions",
          "settingValue" => json_encode([
            "show" => true
          ], JSON_UNESCAPED_SLASHES)
        ],
        [
          "settingName"  => "hostedPaymentBillingAddressOptions",
          "settingValue" => json_encode([
            "show" => true,
            "required" => true
          ], JSON_UNESCAPED_SLASHES)
        ],
        [
          "settingName"  => "hostedPaymentShippingAddressOptions",
          "settingValue" => json_encode([
            "show" => false,
            "required" => false
          ], JSON_UNESCAPED_SLASHES)
        ]
      ]
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
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Processing Payment — Andalusia Health & Fitness</title>
  <link rel="stylesheet" href="/styles.css"/>
  <style>
    body {
      background: #000;
      color: #fff;
      font-family: "Helvetica Neue", Arial, sans-serif;
      margin: 0;
      padding: 80px 20px 40px;
      text-align: center;
    }
    .payment-container {
      max-width: 500px;
      margin: 0 auto;
      background: #111;
      border-radius: 16px;
      padding: 40px;
      box-shadow: 0 0 25px rgba(216, 27, 96, 0.3);
      border: 1px solid rgba(216, 27, 96, 0.2);
    }
    h1 {
      color: #d81b60;
      margin: 0 0 20px;
      font-size: 28px;
    }
    .member-info {
      margin-bottom: 20px;
      padding: 20px;
      background: #1a1a1a;
      border-radius: 8px;
      border-left: 3px solid #d81b60;
      text-align: left;
    }
    .member-info p {
      margin: 8px 0;
      font-size: 15px;
    }
    .member-info strong {
      color: #d81b60;
    }
    .spinner {
      border: 4px solid rgba(255, 255, 255, 0.1);
      border-top: 4px solid #d81b60;
      border-radius: 50%;
      width: 40px;
      height: 40px;
      animation: spin 1s linear infinite;
      margin: 20px auto;
    }
    @keyframes spin {
      0% { transform: rotate(0deg); }
      100% { transform: rotate(360deg); }
    }
  </style>
</head>
<body class="theme-andalusia">

<!-- Topbar -->
<div class="topbar">
  <div class="shell">
    <div class="brand-pill">
      <a href="/index.html"><img src="/AHFlogo.png" alt="Andalusia Health & Fitness"></a>
    </div>
  </div>
</div>

<div class="payment-container">
  <h1>Processing Payment</h1>
  <div class="member-info">
    <p><strong>Member:</strong> <?= htmlspecialchars($memberName) ?></p>
    <p><strong>Amount Due:</strong> $<?= $amount ?></p>
    <p><strong>Invoice:</strong> <?= htmlspecialchars($invoice) ?></p>
  </div>
  <p>Redirecting to secure payment page...</p>
  <div class="spinner"></div>

  <form id="tokenForm" method="POST" action="<?= htmlspecialchars($hostedUrl, ENT_QUOTES, 'UTF-8') ?>" style="display:none;">
    <input type="hidden" name="token" value="<?= $token ?>">
  </form>
</div>

<script>
(function() {
  // Auto-redirect to Authorize.Net payment page (like membership flow)
  setTimeout(function() {
    document.getElementById('tokenForm').submit();
  }, 1500);
})();
</script>

</body>
</html>
