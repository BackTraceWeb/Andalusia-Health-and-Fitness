<?php
/**
 * Create Payment Intent
 * Stores payment intent in database before redirecting to Authorize.Net
 * This allows the return page to look up what payment was being processed
 */
declare(strict_types=1);
header('Content-Type: application/json');

require_once __DIR__ . '/../../_bootstrap.php';

try {
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

    // Store payment intent in database
    // We'll use a simple approach: update the dues record with a processing timestamp
    $stmt = $pdo->prepare("
        UPDATE dues
        SET processing_started_at = NOW()
        WHERE id = :invoice_id AND member_id = :member_id AND status = 'due'
    ");

    $stmt->execute([
        ':invoice_id' => $invoiceId,
        ':member_id' => $memberId
    ]);

    echo json_encode([
        'ok' => true,
        'message' => 'Payment intent created'
    ]);

} catch (Exception $e) {
    error_log("Payment intent error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage()
    ]);
}
