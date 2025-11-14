<?php
/**
 * Process QuickPay Payment
 * Called immediately after successful payment to extend AxTrax membership
 * This replaces relying on Authorize.Net webhooks which may not fire immediately in production
 */
declare(strict_types=1);
header('Content-Type: application/json');

require_once __DIR__ . '/../../_bootstrap.php';
require_once __DIR__ . '/../axtrax-config.php';
require_once __DIR__ . '/../axtrax-helpers.php';

try {
    // Read JSON input
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (!$data || empty($data['memberId']) || empty($data['invoiceId'])) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'Missing memberId or invoiceId']);
        exit;
    }

    $memberId = (int)$data['memberId'];
    $invoiceId = (int)$data['invoiceId'];

    $pdo = pdo();
    $config = require __DIR__ . '/../axtrax-config.php';

    // Get member details
    $stmt = $pdo->prepare('SELECT email, first_name, last_name FROM members WHERE id = ?');
    $stmt->execute([$memberId]);
    $member = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$member) {
        throw new Exception('Member not found');
    }

    // Calculate new valid_until date (30 days from today)
    $newValidUntil = date('Y-m-d', strtotime('+30 days'));

    error_log("QuickPay: Processing payment for member #$memberId ({$member['first_name']} {$member['last_name']})");

    // Extend membership in AxTrax (30 days)
    $axtraxSuccess = axtraxExtendMembership(
        $member['email'],
        $member['first_name'],
        $member['last_name'],
        30, // days
        $config
    );

    if ($axtraxSuccess) {
        error_log("QuickPay: AxTrax API - Successfully extended membership for member #$memberId");
    } else {
        error_log("QuickPay: AxTrax API - Failed to extend membership for member #$memberId (will update database anyway)");
    }

    // Update our database (always do this regardless of AxTrax success)
    $stmt = $pdo->prepare("
        UPDATE members
        SET valid_until = :valid_until,
            status = 'current',
            updated_at = NOW()
        WHERE id = :member_id
    ");
    $stmt->execute([
        ':valid_until' => $newValidUntil,
        ':member_id' => $memberId
    ]);
    error_log("QuickPay: Database - Updated member #$memberId valid_until to $newValidUntil");

    // Mark invoice as paid
    $stmt2 = $pdo->prepare("
        UPDATE dues
        SET status = 'paid',
            paid_at = NOW()
        WHERE id = :dues_id AND status IN ('due', 'failed')
    ");
    $stmt2->execute([':dues_id' => $invoiceId]);
    error_log("QuickPay: Database - Marked invoice #$invoiceId as paid");

    echo json_encode([
        'ok' => true,
        'message' => 'Payment processed successfully',
        'axtraxSuccess' => $axtraxSuccess,
        'validUntil' => $newValidUntil
    ]);

} catch (Exception $e) {
    error_log("QuickPay: Processing error - " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage()
    ]);
}
