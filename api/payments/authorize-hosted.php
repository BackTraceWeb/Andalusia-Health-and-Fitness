<?php
// Minimal Accept Hosted token request for SANDBOX with deep diagnostics
declare(strict_types=1);
header('Content-Type: text/html; charset=utf-8');

require_once __DIR__ . '/../config.php'; // Must define AUTH_LOGIN_ID, AUTH_TRANSACTION_KEY, AUTH_API_URL

$logDir = __DIR__ . '/../../logs';
if (!is_dir($logDir)) { @mkdir($logDir, 0755, true); }

$payload = [
  "getHostedPaymentPageRequest" => [
    "merchantAuthentication" => [
      "name"           => AUTH_LOGIN_ID,
      "transactionKey" => AUTH_TRANSACTION_KEY
    ],
    "transactionRequest" => [
      "transactionType" => "authCaptureTransaction",
      "amount"          => "1.00" // keep minimal to test token creation
    ],
    "hostedPaymentSettings" => [
      "setting" => [[
        "settingName"  => "hostedPaymentReturnOptions",
        "settingValue" => json_encode([
          "showReceipt" => false,
          "url"         => "https://example.com/return", // dummy ok for sandbox token test
          "urlMethod"   => "GET"
        ], JSON_UNESCAPED_SLASHES)
      ]]
    ]
  ]
];

$ch = curl_init(AUTH_API_URL); // Sandbox should be https://apitest.authorize.net/xml/v1/request.api
curl_setopt_array($ch, [
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_POST           => true,
  CURLOPT_HTTPHEADER     => ['Content-Type: application/json','Accept: application/json'],
  CURLOPT_POSTFIELDS     => json_encode($payload, JSON_UNESCAPED_SLASHES),
  CURLOPT_TIMEOUT        => 20,
]);
$response  = curl_exec($ch);
$curlError = curl_error($ch);
$info      = curl_getinfo($ch);
curl_close($ch);

@file_put_contents("$logDir/accept-minimal-request.json", json_encode($payload, JSON_PRETTY_PRINT));
@file_put_contents("$logDir/accept-minimal-response.json",
  "[".date('c')."]\nHTTP ".$info['http_code']."\n".$response."\n", FILE_APPEND);

echo "<h2>Diagnostics</h2><pre>";
echo "AUTH_API_URL: ".AUTH_API_URL."\n";
echo "HTTP Code: ".$info['http_code']."\n";
echo "Content-Type: ".$info['content_type']."\n";
if ($curlError) echo "cURL Error: $curlError\n";
echo "</pre>";

if (!$response) { exit('<p>No response body.</p>'); }

$data = json_decode($response, true);
echo "<h3>Parsed JSON</h3><pre>".htmlspecialchars(json_encode($data, JSON_PRETTY_PRINT), ENT_QUOTES, 'UTF-8')."</pre>";

if (!empty($data['token'])) {
  echo "<p><strong>SUCCESS:</strong> Token received.</p>";
  echo "<p>Use this pay page URL for SANDBOX:</p>";
  echo '<pre>https://test.authorize.net/payment/payment</pre>';
} else {
  echo "<p><strong>FAILED:</strong> No token. Messages:</strong></p>";
  echo "<pre>".htmlspecialchars(json_encode($data['messages'] ?? $data, JSON_PRETTY_PRINT), ENT_QUOTES, 'UTF-8')."</pre>";
}
