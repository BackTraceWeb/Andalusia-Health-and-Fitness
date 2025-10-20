<?php
/**
 * Authorize.Net Payment Success Webhook
 * - Accepts validation pings with no body
 * - Handles real payment events (authcapture.created)
 * - Logs all traffic
 */

header('Content-Type: application/json');

// === ✅ EARLY EXIT FOR VALIDATION TESTS ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = file_get_contents('php://input');

    // Authorize.net test ping sends an empty body
    if (trim($input) === '') {
        echo json_encode(["ok" => true, "message" => "Validation successful"]);
        http_response_code(200);
        exit;
    }
}

// === 1️⃣ Read webhook payload ===
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
 
// Fallback if JSON decode fails
if (!$data && !empty($_POST)) {
    $data = $_POST;
}

if (!$data) {
    http_response_code(400);
    echo json_encode(["ok" => false, "error" => "No payload received"]);
    exit;
}

// === 2️⃣ Log every request (for visibility) ===
$logDir = __DIR__ . '/../../logs';
if (!is_dir($logDir)) mkdir($logDir, 0755, true);
$logFile = "$logDir/authorize-webhook.log";
file_put_contents($logFile, date('c') . " RAW: " . $raw . "\n", FILE_APPEND);

// === 3️⃣ Handle only payment capture events ===
$eventType = $data['eventType'] ?? '';
if ($eventType !== 'net.authorize.payment.authcapture.created') {
    echo json_encode(["ok" => true, "ignored" => $eventType]);
    http_response_code(200);
    exit;
}

// === 4️⃣ Extract fields from Authorize.net payload ===
$payload    = $data['payload'] ?? [];
$amount     = $payload['authAmount'] ?? 0;
$invoiceId  = $payload['invoiceNumber'] ?? '';
$customer   = $payload['customer'] ?? [];
$email      = $customer['email'] ?? '';
$customerId = $customer['id'] ?? '';
$first      = trim($payload['billTo']['firstName'] ?? '');
$last       = trim($payload['billTo']['lastName'] ?? '');

if (!$first || !$last) {
    http_response_code(400);
    echo json_encode(["ok" => false, "error" => "Missing customer name"]);
    exit;
}

// === 5️⃣ Connect to local DB ===
require_once __DIR__ . '/../db.php';
$stmt = $pdo->prepare("SELECT id FROM members WHERE LOWER(first_name)=LOWER(?) AND LOWER(last_name)=LOWER(?) LIMIT 1");
$stmt->execute([$first, $last]);
$member = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$member) {
    http_response_code(404);
    echo json_encode(["ok" => false, "error" => "Member not found", "name" => "$first $last"]);
    exit;
}

$memberId = $member['id'];

// === 6️⃣ Authenticate with NinjaOne API ===
$clientId     = "qJGajqV0AiEiiRMRbGaIJ3cGQuI";
$clientSecret = "TCPQK-WLS0F4X3gqtb_KqdwMIf_4qgtRMd7h6dVkYYB2S1R1rVY7Mg";
$authUrl      = "https://api.us2.ninjarmm.com/oauth/token";

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
    file_put_contents($logFile, "❌ Failed to get NinjaOne token: $authResponse\n", FILE_APPEND);
    http_response_code(500);
    echo json_encode(["ok" => false, "error" => "Failed to authenticate with NinjaOne"]);
    exit;
}

// === 7️⃣ Trigger AxTrax update via NinjaOne ===
$ninjaPayload = [
    "device_id"   => "DESKTOP-DTDNBM0", // ← your AxTrax machine ID
    "script_name" => "Update AxTrax Member (Authorize.net Payment)",
    "parameters"  => [
        "memberId"  => (string)$memberId,
        "invoiceId" => (string)$invoiceId,
        "amount"    => (string)$amount
    ]
];

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

// === 8️⃣ Log result ===
$logMsg = "[" . date('Y-m-d H:i:s') . "] Member:$memberId $first $last Invoice:$invoiceId Amount:$amount HTTP:$httpcode\nResponse:$response\n\n";
file_put_contents($logFile, $logMsg, FILE_APPEND);

// === 9️⃣ Respond OK to Authorize.net ===
if ($httpcode >= 200 && $httpcode < 300) {
    echo json_encode(["ok" => true, "memberId" => $memberId, "invoiceId" => $invoiceId, "amount" => $amount]);
} else {
    http_response_code(500);
    echo json_encode(["ok" => false, "error" => "NinjaOne call failed", "response" => $response]);
}
