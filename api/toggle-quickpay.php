<?php
/**
 * Toggle QuickPay access for draft members
 * Staff-only endpoint to enable/disable manual payment for draft members
 */
declare(strict_types=1);
header('Content-Type: application/json');

require_once __DIR__ . '/../_bootstrap.php';

// Require admin authentication
session_start();
if (empty($_SESSION['logged_in'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'unauthorized']);
    exit;
}

$pdo = pdo();
if (!$pdo) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'database_error']);
    exit;
}

// Get input
$input = json_decode(file_get_contents('php://input'), true);
$memberId = (int)($input['member_id'] ?? 0);
$enabled = !empty($input['enabled']); // Convert to boolean

if ($memberId <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid_member_id']);
    exit;
}

try {
    // Verify member exists and is on draft
    $stmt = $pdo->prepare("SELECT id, first_name, last_name, is_draft FROM members WHERE id = ?");
    $stmt->execute([$memberId]);
    $member = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$member) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'member_not_found']);
        exit;
    }

    // Only allow toggling for draft members
    if ((int)$member['is_draft'] !== 1) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'not_draft_member']);
        exit;
    }

    // Update allow_quickpay flag
    $newValue = $enabled ? 1 : 0;
    $updateStmt = $pdo->prepare("UPDATE members SET allow_quickpay = ? WHERE id = ?");
    $updateStmt->execute([$newValue, $memberId]);

    // Log the action
    $action = $enabled ? 'enabled' : 'disabled';
    error_log("QuickPay toggle: Staff {$_SESSION['username']} {$action} QuickPay for draft member #{$memberId} ({$member['first_name']} {$member['last_name']})");

    echo json_encode([
        'ok' => true,
        'member_id' => $memberId,
        'allow_quickpay' => $newValue,
        'message' => $enabled ? 'QuickPay enabled for this member' : 'QuickPay disabled for this member'
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'server_error', 'message' => $e->getMessage()]);
}
