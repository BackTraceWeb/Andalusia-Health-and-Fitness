<?php
/**
 * Authorize.Net Hosted Payment (Stable + Minimal Switch + Debug Logging)
 * - Uses config.php for creds/env
 * - Builds Accept Hosted token and auto-redirects
 * - Logs request + HTTP code + raw response for fast diagnosis
 * - Optional: &mode=qp → invoice "QP<duesId>M<memberId>" (webhook acts on these)
 * - Optional: &minimal=1 → ultra-minimal ANet payload to isolate E00001 causes
 */

declare(strict_types=1);
header('Content-Type: text/html; charset=utf-8');

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../../_bootstrap.php';   // must provide pdo()

// ---------- DB ----------
$pdo = pdo();
if (!$pdo) {
    http_response_code(500);
    exit('Database not connected.');
}

// ---------- Inputs ----------
$memberId   = isset($_GET['memberId']) ? (int)$_GET['memberId'] : 0;
$duesId     = isset($_GET['invoiceId']) ? (int)$_GET['invoiceId'] : 0;
$qpMode     = isset($_GET['mode']) ? strtolower(trim($_GET['mode'])) : '';
$isQuickPay = ($qpMode === 'qp');
$minimal    = isset($_GET['minimal']) && $_GET['minimal'] == '1';

if ($memberId <= 0 || $duesId <= 0) {
    http_response_code(400);
    exit('Missing memberId or invoiceId.');
}

// ---------- Lookups ----------
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
$invoice = $isQuickPay
         ? "QP{$duesId}M{$memberId}"            // webhook will act only on QuickPay
         : "DUES{$duesId}-MEM{$memberId}";

// ---------- Env-specific Accept Hosted URL ----------
$hostedUrl = (defined('AUTH_ENV') && strtolower(AUTH_ENV) === 'sandbox')
  ? 'https://test.authorize.net/payment/payment'
  : 'https://accept.authorize.net/payment/payment';

// ---------- Build token request ----------
$baseUrl = rtrim((defined('SITE_BASE_URL') ? SITE_BASE_URL : 'https://andalusiahealthandfitness.com'), '/');

if ($minimal) {
    // 🔎 Minimal request to rule out field/setting issues
    $payload = [
        "getHostedPaymentPageRequest" => [
            "merchantAuthentication" => [
                "name"           => AUTH_LOGIN_ID,
                "transactionKey" => AUTH_TRANSACTION_KEY,
            ],
            "transactionRequest" => [
                "transactionType" => "authCaptureTransaction",
                "amount"          => $amount
            ],
            "hostedPaymentSettings" => [
                "setting" => [[
                    "settingName"  => "hostedPaymentReturnOptions",
                    "settingValue" => json_encode([
                        "showReceipt"   => false,
                        "url"           => "{$baseUrl}/api/payments/authorize-return.php?memberId={$memberId}&invoiceId={$duesId}",
                        "urlText"       => "",
                        "cancelUrl"     => "{$baseUrl}/quickpay/",
                        "cancelUrlText" => "Cancel",
                        "linkMethod"    => "POST",
                    ], JSON_UNESCAPED_SLASHES),
                ]],
            ],
        ],
    ];
} else {
    // 🟢 Normal stable payload (no risky fields)
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
                    "description"   => "Membership dues for {$m['first_name']} {$m['last_name']}",
                ],
                "billTo" => [
                    "firstName" => $m['first_name'],
                    "lastName"  => $m['last_name'],
                    "zip"       => (string)($m['zip'] ?? ''),
                ],
            ],
            "hostedPaymentSettings" => [
                "setting" => [
                    [
                        "settingName"  => "hostedPaymentReturnOptions",
                        "settingValue" => json_encode([
                            "showReceipt"   => false,
                            "url"           => "{$baseUrl}/api/payments/authorize-return.php?memberId={$memberId}&invoiceId={$duesId}",
                            "urlText"       => "",
                            "cancelUrl"     => "{$baseUrl}/quickpay/",
                            "cancelUrlText" => "Cancel",
                            "linkMethod"    => "POST",
                        ], JSON_UNESCAPED_SLASHES),
                    ],
                    [
                        "settingName"  => "hostedPaymentButtonOptions",
                        "settingValue" => json_encode(["text" => "Pay Now"], JSON_UNESCAPED_SLASHES),
                    ],
                    [
                        "settingName"  => "hostedPaymentStyleOptions",
                        "settingValue" => json_encode(["bgColor" => "#000000"], JSON_UNESCAPED_SLASHES),
                    ],
                ],
            ],
        ],
    ];
}

// ---------- Debug logs (non-fatal) ----------
$logDir = __DIR__ . '/../../logs';
if (!is_dir($logDir)) { @mkdir($logDir, 0755, true); }
$nowTag = date('Ymd-His');
@file_put_contents("$logDir/authorize-debug-$nowTag.json", json_encode($payload, JSON_PRETTY_PRINT));

// ---------- Call Authorize.Net ----------
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

// Log HTTP + mode + raw response (helps diagnose E00001)
@file_put_contents(
    "$logDir/authorize-token-response.log",
    "[".date('c')."] MODE=".($minimal?'MIN':'FULL')." HTTP=$httpCode ENV=".(defined('AUTH_ENV')?AUTH_ENV:'?')." URL=".AUTH_API_URL." INV=".$invoice."\n".$response."\n\n",
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
  <title>Redirecting…</title>
</head>
<body onload="document.forms[0].submit()">
  <p>Redirecting to Secure Payment…</p>
  <form method="POST" action="<?= htmlspecialchars($hostedUrl, ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="token" value="<?= $token ?>">
    <noscript><button type="submit">Continue</button></noscript>
  </form>
</body>
</html>
