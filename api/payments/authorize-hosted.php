<?php
/**
 * Authorize.Net Hosted Payment - QuickPay Flow
 *
 * STABLE WORKING VERSION - DO NOT MODIFY WITHOUT TESTING
 *
 * Purpose:
 * - Generates Authorize.Net Accept Hosted payment token for existing members paying dues
 * - Used by QuickPay workflow (not membership signup)
 * - Redirects user to Authorize.Net hosted payment page
 *
 * Flow:
 * 1. Receives memberId and invoiceId from QuickPay form
 * 2. Looks up member and dues records from database
 * 3. Generates payment token via Authorize.Net API
 * 4. Auto-submits form to redirect to Authorize.Net payment page
 * 5. After payment, Authorize.Net calls webhook (authorize-webhook.php)
 * 6. Webhook processes payment and updates database
 *
 * URL: /api/payments/authorize-hosted.php?memberId=1700&invoiceId=3
 *
 * Important:
 * - This version uses MINIMAL payload (no customer/billTo fields)
 * - Intentionally omits description to avoid API errors
 * - Return URL does NOT include memberId/invoiceId (webhook handles via transactionId lookup)
 * - Logs all API requests to logs/authorize-debug.json for debugging
 */
declare(strict_types=1);
header('Content-Type: text/html; charset=utf-8');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../../_bootstrap.php';

// ========================================
// 1. Database Connection & Input Validation
// ========================================

$pdo = pdo();
if (!$pdo) {
    http_response_code(500);
    exit('Database not connected.');
}

// Get memberId and invoiceId from URL parameters
// Expected URL: ?memberId=1700&invoiceId=3
$memberId = isset($_GET['memberId']) ? (int)$_GET['memberId'] : 0;
$duesId   = isset($_GET['invoiceId']) ? (int)$_GET['invoiceId'] : 0;
if ($memberId <= 0 || $duesId <= 0) {
    http_response_code(400);
    exit('Missing memberId or invoiceId.');
}

// ========================================
// 2. Database Lookups
// ========================================

// Look up member details (for logging/reference, not sent to Authorize.Net in this minimal version)
$stmt = $pdo->prepare('SELECT first_name,last_name,email,zip FROM members WHERE id=?');
$stmt->execute([$memberId]);
$m = $stmt->fetch(PDO::FETCH_ASSOC);

// Look up dues record to get payment amount
$stmt2 = $pdo->prepare('SELECT amount_cents,period_start,period_end FROM dues WHERE id=?');
$stmt2->execute([$duesId]);
$d = $stmt2->fetch(PDO::FETCH_ASSOC);

if (!$m || !$d) {
    http_response_code(404);
    exit('Member or dues record not found.');
}

// Convert amount from cents to dollars (e.g., 5500 -> "55.00")
$amount  = number_format(($d['amount_cents'] / 100), 2, '.', '');

// Generate invoice number: "QP3M1700" (QuickPay + duesId + Member + memberId)
// Must be alphanumeric only, max 20 characters per Authorize.Net requirements
$invoice = substr(preg_replace('/[^A-Za-z0-9]/','', "QP{$duesId}M{$memberId}"), 0, 20);

// ========================================
// 3. Build Authorize.Net API Payload
// ========================================

/**
 * IMPORTANT: This uses a MINIMAL payload approach
 * - Only sends required fields: transactionType, amount, invoiceNumber
 * - Does NOT send customer or billTo fields (causes errors in some configurations)
 * - Does NOT send order description (intentionally omitted to avoid API errors)
 * - This minimal approach has been tested and works reliably
 */
$payload = [
  "getHostedPaymentPageRequest" => [
    // Merchant credentials from config.php
    "merchantAuthentication" => [
      "name" => AUTH_LOGIN_ID,
      "transactionKey" => AUTH_TRANSACTION_KEY
    ],

    // Transaction details
    "transactionRequest" => [
      "transactionType" => "authCaptureTransaction",  // Authorize and capture immediately
      "amount" => $amount,                            // e.g., "55.00"
      "order" => [
        "invoiceNumber" => $invoice                   // e.g., "QP3M1700"
        // NOTE: Description intentionally omitted - caused API errors in testing
        // NOTE: customer and billTo fields intentionally omitted - minimal payload approach
      ]
    ],

    // Hosted payment page settings
    "hostedPaymentSettings" => [
      "setting" => [
        // Return URLs after payment completion/cancellation
        [
          "settingName"  => "hostedPaymentReturnOptions",
          "settingValue" => json_encode([
            "showReceipt" => false,                   // Don't show Authorize.Net receipt
            // Return URL: Webhook will handle the actual payment processing
            // No memberId/invoiceId params needed - webhook looks up via transactionId
            "url"         => "https://andalusiahealthandfitness.com/api/payments/authorize-return.php",
            "cancelUrl"   => "https://andalusiahealthandfitness.com/quickpay/"
          ], JSON_UNESCAPED_SLASHES)
        ],

        // Payment form options
        [
          "settingName"  => "hostedPaymentPaymentOptions",
          "settingValue" => json_encode([
            "cardCodeRequired" => true                // Require CVV for security
          ], JSON_UNESCAPED_SLASHES)
        ],

        // Display order summary on payment page
        [
          "settingName"  => "hostedPaymentOrderOptions",
          "settingValue" => json_encode([
            "show" => true                            // Show order details to customer
          ], JSON_UNESCAPED_SLASHES)
        ]
      ]
    ]
  ]
];

// ========================================
// 4. Logging (for debugging)
// ========================================

// Log the request payload to help debug any issues
$logDir = __DIR__ . '/../../logs';
if (!is_dir($logDir)) mkdir($logDir, 0755, true);
file_put_contents("$logDir/authorize-debug.json", json_encode($payload, JSON_PRETTY_PRINT));

// ========================================
// 5. Call Authorize.Net API
// ========================================

// Make API request to get hosted payment page token
$ch = curl_init(AUTH_API_URL);  // Sandbox: https://apitest.authorize.net/xml/v1/request.api
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

// Handle API errors
if ($curlError) exit("<h3>cURL Error</h3><pre>$curlError</pre>");
if (!$response)  exit('<h3>No response from Authorize.Net</h3>');

// Parse JSON response (strip BOM if present)
$data = json_decode(preg_replace('/^\xEF\xBB\xBF/', '', $response), true);
if (json_last_error() !== JSON_ERROR_NONE) {
    exit('<h3>JSON Decode Error:</h3><pre>' . json_last_error_msg() . '</pre>');
}

// Check if token was returned
if (empty($data['token'])) {
    exit('<h2>Authorize.Net Error</h2><pre>' .
         htmlspecialchars(json_encode($data, JSON_PRETTY_PRINT)) .
         '</pre>');
}

// Extract the payment token (used to load the hosted payment form)
$token = htmlspecialchars($data['token']);
?>
<!-- ========================================
     6. Auto-Submit Form to Redirect to Authorize.Net
     ========================================

     This page auto-submits a form with the payment token to redirect
     the user to Authorize.Net's hosted payment page.

     Flow after redirect:
     1. User fills out credit card form on Authorize.Net's site
     2. On submit, Authorize.Net processes payment
     3. Authorize.Net calls our webhook (authorize-webhook.php) with transaction details
     4. Webhook updates database and calls AxTrax API
     5. User is redirected back to authorize-return.php (success/cancel page)
-->
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
