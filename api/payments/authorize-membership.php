<?php
/**
 * Authorize.Net Hosted Payment for Membership Signups
 * Creates payment token for new membership signups (not existing members)
 */
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config.php';

// Get POST data
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data || !isset($data['amount'], $data['invoice'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required fields: amount and invoice']);
    exit;
}

$amount = $data['amount'];
$invoice = substr(preg_replace('/[^A-Za-z0-9]/', '', $data['invoice']), 0, 20);
$customer = $data['customer'] ?? [];

// Build Authorize.Net request
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
                "description" => "Andalusia Health & Fitness Membership"
            ],
            "customer" => [
                "email" => $customer['email'] ?? ''
            ],
            "billTo" => [
                "firstName" => $customer['firstName'] ?? '',
                "lastName" => $customer['lastName'] ?? '',
                "zip" => $customer['zip'] ?? ''
            ]
        ],
        "hostedPaymentSettings" => [
            "setting" => [
                [
                    "settingName" => "hostedPaymentReturnOptions",
                    "settingValue" => json_encode([
                        "showReceipt" => false,
                        "url" => "https://andalusiahealthandfitness.com/api/payments/authorize-return.php?type=membership",
                        "cancelUrl" => "https://andalusiahealthandfitness.com/membership.html"
                    ], JSON_UNESCAPED_SLASHES)
                ],
                [
                    "settingName" => "hostedPaymentPaymentOptions",
                    "settingValue" => json_encode([
                        "cardCodeRequired" => true
                    ], JSON_UNESCAPED_SLASHES)
                ],
                [
                    "settingName" => "hostedPaymentOrderOptions",
                    "settingValue" => json_encode([
                        "show" => true
                    ], JSON_UNESCAPED_SLASHES)
                ],
                [
                    "settingName" => "hostedPaymentBillingAddressOptions",
                    "settingValue" => json_encode([
                        "show" => false,
                        "required" => false
                    ], JSON_UNESCAPED_SLASHES)
                ]
            ]
        ]
    ]
];

// Log request for debugging
$logDir = __DIR__ . '/../../logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0755, true);
}
file_put_contents("$logDir/authorize-membership-" . date('Y-m-d') . ".json",
    date('Y-m-d H:i:s') . "\n" . json_encode($payload, JSON_PRETTY_PRINT) . "\n\n",
    FILE_APPEND
);

// Call Authorize.Net API
$ch = curl_init(AUTH_API_URL);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
    CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_SLASHES),
    CURLOPT_TIMEOUT => 20
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    http_response_code(500);
    echo json_encode(['error' => 'cURL error: ' . $curlError]);
    exit;
}

if (!$response) {
    http_response_code(500);
    echo json_encode(['error' => 'No response from Authorize.Net']);
    exit;
}

// Parse response
$responseData = json_decode(preg_replace('/^\xEF\xBB\xBF/', '', $response), true);

// Log response
file_put_contents("$logDir/authorize-membership-" . date('Y-m-d') . ".json",
    "RESPONSE:\n" . json_encode($responseData, JSON_PRETTY_PRINT) . "\n" . str_repeat('=', 80) . "\n\n",
    FILE_APPEND
);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(500);
    echo json_encode(['error' => 'JSON decode error: ' . json_last_error_msg()]);
    exit;
}

if (empty($responseData['token'])) {
    http_response_code(500);
    $errorMsg = isset($responseData['messages']['message'][0]['text'])
        ? $responseData['messages']['message'][0]['text']
        : 'Failed to create payment page';
    echo json_encode([
        'error' => $errorMsg,
        'details' => $responseData
    ]);
    exit;
}

// Success - return token
echo json_encode([
    'success' => true,
    'token' => $responseData['token'],
    'paymentUrl' => 'https://test.authorize.net/payment/payment'
]);
