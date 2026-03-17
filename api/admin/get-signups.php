<?php
/**
 * Admin API: Get New Signups
 * Returns all completed signups with stats
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

    // Get limit from query param (default 50, max 200)
    $limit = isset($_GET['limit']) ? min((int)$_GET['limit'], 200) : 50;

    // Get total count
    $stmt = $pdo->query("
        SELECT COUNT(*) as count
        FROM pending_signups
        WHERE status = 'completed'
        AND completed_at IS NOT NULL
    ");
    $totalCount = (int)$stmt->fetch(PDO::FETCH_ASSOC)['count'];

    // Get 24h count
    $stmt = $pdo->query("
        SELECT COUNT(*) as count
        FROM pending_signups
        WHERE status = 'completed'
        AND completed_at IS NOT NULL
        AND completed_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ");
    $count24h = (int)$stmt->fetch(PDO::FETCH_ASSOC)['count'];

    // Get 7d count
    $stmt = $pdo->query("
        SELECT COUNT(*) as count
        FROM pending_signups
        WHERE status = 'completed'
        AND completed_at IS NOT NULL
        AND completed_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
    ");
    $count7d = (int)$stmt->fetch(PDO::FETCH_ASSOC)['count'];

    // Get signups
    $stmt = $pdo->prepare("
        SELECT
            id,
            member_name,
            member_email,
            member_phone,
            membership_plan,
            monthly_fee,
            completed_at,
            created_at
        FROM pending_signups
        WHERE status = 'completed'
        AND completed_at IS NOT NULL
        ORDER BY completed_at DESC
        LIMIT :limit
    ");
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $signups = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'ok' => true,
        'signups' => $signups,
        'stats' => [
            'total' => $totalCount,
            'last_24h' => $count24h,
            'last_7d' => $count7d
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage()
    ]);
}
