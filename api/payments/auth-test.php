<?php
require_once __DIR__ . '/../config.php';

$payload = [
  "authenticateTestRequest" => [
    "merchantAuthentication" => [
      "name" => AUTH_LOGIN_ID,
      "transactionKey" => AUTH_TRANSACTION_KEY
    ]
  ]
];

$ch = curl_init(AUTH_API_URL);
curl_setopt_array($ch, [
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_POST => true,
  CURLOPT_HTTPHEADER => ["Content-Type: application/json"],
  CURLOPT_POSTFIELDS => json_encode($payload)
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

header('Content-Type: application/json');
echo $response ?: json_encode(["error" => "No response", "httpCode" => $httpCode]);
