<?php
/**
 * Get Recent Payment Intent
 * Looks up the most recent unprocessed payment intent (within last hour)
 */
declare(strict_types=1);
header('Content-Type: application/json');

require_once __DIR__ . '/../../_bootstrap.php';

try {
    $pdo = pdo();

    // Get the most recent unprocessed payment intent from the last hour
    $stmt = $pdo->prepare("
        SELECT member_id, invoice_id, created_at
        FROM payment_intents
        WHERE processed_at IS NULL
          AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ORDER BY created_at DESC
        LIMIT 1
    ");
    $stmt->execute();
    $intent = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($intent) {
        echo json_encode([
            'ok' => true,
            'memberId' => (int)$intent['member_id'],
            'invoiceId' => (int)$intent['invoice_id'],
            'createdAt' => $intent['created_at']
        ]);
    } else {
        echo json_encode([
            'ok' => false,
            'error' => 'No recent unprocessed payment intent found'
        ]);
    }

} catch (Exception $e) {
    error_log("Get recent intent error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage()
    ]);
}
