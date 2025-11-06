<?php
/**
 * Authorize.Net Hosted Payment for QuickPay
 * Displays payment form embedded on this page (no redirect)
 */
declare(strict_types=1);
header('Content-Type: text/html; charset=utf-8');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../../_bootstrap.php';

$pdo = pdo();
if (!$pdo) { http_response_code(500); exit('Database not connected.'); }

$memberId = (int)($_GET['memberId'] ?? 0);
$duesId   = (int)($_GET['invoiceId'] ?? 0);
if ($memberId <= 0 || $duesId <= 0) { http_response_code(400); exit('Missing memberId or invoiceId.'); }

$stmt = $pdo->prepare('SELECT first_name,last_name,email,zip FROM members WHERE id=?');
$stmt->execute([$memberId]);
$m = $stmt->fetch(PDO::FETCH_ASSOC);

$stmt2 = $pdo->prepare('SELECT amount_cents,period_start,period_end FROM dues WHERE id=?');
$stmt2->execute([$duesId]);
$d = $stmt2->fetch(PDO::FETCH_ASSOC);

if (!$m || !$d) { http_response_code(404); exit('Member or dues record not found.'); }

$amount  = number_format(($d['amount_cents'] / 100), 2, '.', '');
$invoice = substr(preg_replace('/[^A-Za-z0-9]/','', "QP{$duesId}M{$memberId}"), 0, 20);
$returnUrl = "https://andalusiahealthandfitness.com/api/payments/authorize-return.php?memberId=$memberId&invoiceId=$duesId";

$payload = [
  "getHostedPaymentPageRequest" => [
    "merchantAuthentication" => [
      "name"           => AUTH_LOGIN_ID,
      "transactionKey" => AUTH_TRANSACTION_KEY
    ],
    "transactionRequest" => [
      "transactionType" => "authCaptureTransaction",
      "amount" => $amount,
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
          "settingName"  => "hostedPaymentPaymentOptions",
          "settingValue" => json_encode([
            "cardCodeRequired" => true
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
            "show" => false,
            "required" => false
          ], JSON_UNESCAPED_SLASHES)
        ]
      ]
    ]
  ]
];

$logDir = __DIR__ . '/../../logs';
if (!is_dir($logDir)) { @mkdir($logDir, 0755, true); }
@file_put_contents("$logDir/authorize-quickpay-" . date('Y-m-d') . ".json",
    date('Y-m-d H:i:s') . "\n" . json_encode($payload, JSON_PRETTY_PRINT) . "\n\n", FILE_APPEND);

$ch = curl_init(AUTH_API_URL);
curl_setopt_array($ch, [
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_POST           => true,
  CURLOPT_HTTPHEADER     => ['Content-Type: application/json','Accept: application/json'],
  CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_SLASHES),
  CURLOPT_TIMEOUT        => 20
]);
$response  = curl_exec($ch);
$curlErr   = curl_error($ch);
$httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

@file_put_contents("$logDir/authorize-quickpay-" . date('Y-m-d') . ".json",
    "RESPONSE HTTP $httpCode:\n" . $response . "\n" . str_repeat('=', 80) . "\n\n", FILE_APPEND);

if ($curlErr) exit("<h3>cURL Error</h3><pre>".htmlspecialchars($curlErr, ENT_QUOTES, 'UTF-8')."</pre>");
if (!$response) exit('<h3>No response from Authorize.Net</h3>');

$data = json_decode(preg_replace('/^\xEF\xBB\xBF/', '', $response), true);
if (json_last_error() !== JSON_ERROR_NONE) {
  exit('<h3>JSON Decode Error</h3><pre>'.htmlspecialchars(json_last_error_msg(), ENT_QUOTES, 'UTF-8')."</pre>");
}

if (empty($data['token'])) {
  exit('<h2>Authorize.Net Error</h2><pre>'.htmlspecialchars(json_encode($data, JSON_PRETTY_PRINT), ENT_QUOTES, 'UTF-8').'</pre>');
}

$token  = htmlspecialchars($data['token'], ENT_QUOTES, 'UTF-8');
$memberName = htmlspecialchars(trim(($m['first_name'] ?? '') . ' ' . ($m['last_name'] ?? '')), ENT_QUOTES, 'UTF-8');

// Determine iframe URL based on environment
$iframeUrl = (defined('AUTH_API_URL') && stripos(AUTH_API_URL, 'apitest.authorize.net') !== false)
    ? 'https://test.authorize.net/payment/payment'
    : 'https://accept.authorize.net/payment/payment';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Secure Payment — Andalusia Health & Fitness</title>
  <link rel="stylesheet" href="/styles.css"/>
  <style>
    body {
      background: #000;
      color: #fff;
      font-family: "Helvetica Neue", Arial, sans-serif;
      margin: 0;
      padding: 80px 20px 40px;
    }
    .payment-container {
      max-width: 800px;
      margin: 0 auto;
      background: #111;
      border-radius: 16px;
      padding: 30px;
      box-shadow: 0 0 25px rgba(216, 27, 96, 0.3);
      border: 1px solid rgba(216, 27, 96, 0.2);
    }
    h1 {
      color: #d81b60;
      margin: 0 0 10px;
      font-size: 28px;
    }
    .member-info {
      margin-bottom: 20px;
      padding: 15px;
      background: #1a1a1a;
      border-radius: 8px;
      border-left: 3px solid #d81b60;
    }
    .member-info p {
      margin: 5px 0;
      font-size: 14px;
    }
    .member-info strong {
      color: #d81b60;
    }
    #paymentFrame {
      width: 100%;
      height: 700px;
      border: none;
      border-radius: 8px;
      background: white;
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
  <h1>Secure Payment</h1>
  <div class="member-info">
    <p><strong>Member:</strong> <?= $memberName ?></p>
    <p><strong>Amount Due:</strong> $<?= $amount ?></p>
    <p><strong>Invoice:</strong> <?= $invoice ?></p>
  </div>

  <iframe id="paymentFrame" name="paymentFrame"></iframe>

  <form id="tokenForm" method="POST" action="<?= $iframeUrl ?>" target="paymentFrame" style="display:none;">
    <input type="hidden" name="token" value="<?= $token ?>">
  </form>
</div>

<script>
(function() {
  // Submit token to iframe immediately
  document.getElementById('tokenForm').submit();
})();
</script>

</body>
</html>
