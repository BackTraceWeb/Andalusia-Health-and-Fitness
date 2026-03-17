<?php
/**
 * Admin API: Get Abandoned Signups
 * Retrieves signup attempts where payment may have succeeded but signup didn't complete
 */
declare(strict_types=1);
header('Content-Type: application/json');

require_once __DIR__ . '/../../_bootstrap.php';

session_start();
if (empty($_SESSION['logged_in'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'unauthorized']);
    exit;
}

try {
    $pdo = pdo();

    // Get all pending signups older than 15 minutes (to allow time for completion)
    $stmt = $pdo->query("
        SELECT
            id,
            session_id,
            member_name,
            member_email,
            member_phone,
            membership_plan,
            monthly_fee,
            status,
            created_at,
            completed_at,
            TIMESTAMPDIFF(MINUTE, created_at, NOW()) as age_minutes
        FROM pending_signups
        WHERE status = 'pending'
          AND created_at < DATE_SUB(NOW(), INTERVAL 15 MINUTE)
        ORDER BY created_at DESC
        LIMIT 50
    ");

    $pending = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Also get recently completed ones for reference
    $stmt = $pdo->query("
        SELECT
            id,
            session_id,
            member_name,
            member_email,
            status,
            created_at,
            completed_at
        FROM pending_signups
        WHERE status = 'completed'
        ORDER BY completed_at DESC
        LIMIT 10
    ");

    $completed = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'ok' => true,
        'pending' => $pending,
        'completed' => $completed
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage()
    ]);
}
