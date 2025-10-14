<?php
/**
 * authorize-success.php
 * Receives successful Authorize.Net payment webhooks,
 * finds the matching member, and triggers NinjaOne to update AxTrax.
 */

header('Content-Type: application/json');

// 1️⃣ Get the incoming webhook body
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

// If Authorize.Net posts form-encoded data instead of JSON:
if (!$data && $_POST) $data = $_POST;

if (!$data) {
    http_response_code(400);
    echo json_encode(["ok" => false, "error" => "No payload received"]);
    exit;
}

// 2️⃣ Extract key info
$first     = trim($data['first_name']  ?? '');
$last      = trim($data['last_name']   ?? '');
$invoiceId = trim($data['invoice_id']  ?? '');
$amount    = trim($data['amount']      ?? '');

if (!$first || !$last) {
    http_response_code(400);
    echo json_encode(["ok" => false, "error" => "Missing name fields"]);
    exit;
}

// 3️⃣ Connect to your local MySQL DB
require_once __DIR__ . '/../db.php';   // adjust path to your PDO connection

// 4️⃣ Find the member record
$stmt = $pdo->prepare("SELECT id FROM members WHERE first_name = ? AND last_name = ? LIMIT 1");
$stmt->execute([$first, $last]);
$member = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$member) {
    http_response_code(404);
    echo json_encode(["ok" => false, "error" => "No member found for $first $last"]);
    exit;
}

$memberId = $member['id'];

// 5️⃣ Prepare payload for NinjaOne API
$ninjaPayload = [
    "device_id"   => "YOUR_AXTRAX_DEVICE_ID", // ← replace this
    "script_name" => "Update AxTrax Member (Authorize.net Payment)",
    "parameters"  => [
        "memberId"  => (string)$memberId,
        "invoiceId" => (string)$invoiceId
    ]
];

// 6️⃣ Send to NinjaOne
$ch = curl_init("https://api.ninjarmm.com/v2/scripts/execute");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_HTTPHEADER     => [
        "Authorization: Bearer YOUR_NINJA_API_TOKEN", // ← replace this
        "Content-Type: application/json"
    ],
    CURLOPT_POSTFIELDS     => json_encode($ninjaPayload)
]);
$response  = curl_exec($ch);
$httpcode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// 7️⃣ Log result
$logDir = __DIR__ . '/../../logs';
if (!is_dir($logDir)) mkdir($logDir, 0755, true);
file_put_contents(
    "$logDir/authorize-webhook.log",
    "[".date('Y-m-d H:i:s')."] Member:$memberId Invoice:$invoiceId HTTP:$httpcode\n$response\n\n",
    FILE_APPEND
);

// 8️⃣ Respond to Authorize.Net
if ($httpcode >= 200 && $httpcode < 300) {
    echo json_encode(["ok" => true, "memberId" => $memberId, "invoiceId" => $invoiceId]);
} else {
    http_response_code(500);
    echo json_encode(["ok" => false, "error" => "NinjaOne call failed", "response" => $response]);
}
