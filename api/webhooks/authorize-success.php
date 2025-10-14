<?php
/**
 * authorize-success.php
 * Handles successful Authorize.Net payments and triggers NinjaOne to update AxTrax.
 */

header('Content-Type: application/json');

// 1️⃣ Read webhook payload (JSON or form)
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!$data && $_POST) $data = $_POST;

if (!$data) {
    http_response_code(400);
    echo json_encode(["ok" => false, "error" => "No payload received"]);
    exit;
}

// 2️⃣ Extract fields
$first     = trim($data['first_name'] ?? '');
$last      = trim($data['last_name'] ?? '');
$invoiceId = trim($data['invoice_id'] ?? '');
$amount    = trim($data['amount'] ?? '');

if (!$first || !$last) {
    http_response_code(400);
    echo json_encode(["ok" => false, "error" => "Missing name fields"]);
    exit;
}

// 3️⃣ Connect to local MySQL database
require_once __DIR__ . '/../db.php'; // adjust if needed

// 4️⃣ Find the member by name
$stmt = $pdo->prepare("SELECT id FROM members WHERE first_name = ? AND last_name = ? LIMIT 1");
$stmt->execute([$first, $last]);
$member = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$member) {
    http_response_code(404);
    echo json_encode(["ok" => false, "error" => "No member found for $first $last"]);
    exit;
}

$memberId = $member['id'];

// 5️⃣ === GET NINJAONE ACCESS TOKEN (US2 REGION) ===
$clientId     = "qJGajqV0AiEiiRMRbGaIJ3cGQuI";       // ← replace with your NinjaOne Client ID
$clientSecret = "TCPQK-WLS0F4X3gqtb_KqdwMIf_4qgtRMd7h6dVkYYB2S1R1rVY7Mg";   // ← replace with your NinjaOne Client Secret
$authUrl      = "https://api.us2.ninjarmm.com/oauth/token"; // us2 region

$authPayload = [
    "grant_type"    => "client_credentials",
    "client_id"     => $clientId,
    "client_secret" => $clientSecret
];

$ch = curl_init($authUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_HTTPHEADER     => ["Content-Type: application/json"],
    CURLOPT_POSTFIELDS     => json_encode($authPayload)
]);
$authResponse = curl_exec($ch);
curl_close($ch);

$authData = json_decode($authResponse, true);
$accessToken = $authData['access_token'] ?? null;

if (!$accessToken) {
    error_log("❌ Failed to get NinjaOne token: " . $authResponse);
    http_response_code(500);
    echo json_encode(["ok" => false, "error" => "Failed to authenticate with NinjaOne"]);
    exit;
}

// 6️⃣ Prepare payload for NinjaOne script execution
$ninjaPayload = [
    "device_id"   => "DESKTOP-DTDNBM0", // ← replace with AxTrax PC device_id from Ninja
    "script_name" => "Update AxTrax Member (Authorize.net Payment)",
    "parameters"  => [
        "memberId"  => (string)$memberId,
        "invoiceId" => (string)$invoiceId
    ]
];

// 7️⃣ Send script execute call to NinjaOne API (US2 REGION)
$ch = curl_init("https://api.us2.ninjarmm.com/v2/scripts/execute");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_HTTPHEADER     => [
        "Authorization: Bearer $accessToken",
        "Content-Type: application/json"
    ],
    CURLOPT_POSTFIELDS     => json_encode($ninjaPayload)
]);

$response  = curl_exec($ch);
$httpcode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// 8️⃣ Logging (for troubleshooting & audits)
$logDir = __DIR__ . '/../../logs';
if (!is_dir($logDir)) mkdir($logDir, 0755, true);
$logFile = "$logDir/authorize-webhook.log";
$logMsg = "[" . date('Y-m-d H:i:s') . "] Member:$memberId Invoice:$invoiceId HTTP:$httpcode\nResponse:$response\n\n";
file_put_contents($logFile, $logMsg, FILE_APPEND);

// 9️⃣ Respond to Authorize.Net
if ($httpcode >= 200 && $httpcode < 300) {
    echo json_encode(["ok" => true, "memberId" => $memberId, "invoiceId" => $invoiceId]);
} else {
    http_response_code(500);
    echo json_encode(["ok" => false, "error" => "NinjaOne call failed", "response" => $response]);
}
