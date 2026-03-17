<?php
/**
 * Admin API: Get QuickPay Payments
 * Returns all QuickPay payments with stats
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
        FROM payment_intents
        WHERE processed_at IS NOT NULL
    ");
    $totalCount = (int)$stmt->fetch(PDO::FETCH_ASSOC)['count'];

    // Get 24h count
    $stmt = $pdo->query("
        SELECT COUNT(*) as count
        FROM payment_intents
        WHERE processed_at IS NOT NULL
        AND processed_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ");
    $count24h = (int)$stmt->fetch(PDO::FETCH_ASSOC)['count'];

    // Get 7d count
    $stmt = $pdo->query("
        SELECT COUNT(*) as count
        FROM payment_intents
        WHERE processed_at IS NOT NULL
        AND processed_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
    ");
    $count7d = (int)$stmt->fetch(PDO::FETCH_ASSOC)['count'];

    // Get QuickPay payments
    $stmt = $pdo->prepare("
        SELECT
            pi.id,
            pi.member_id,
            pi.invoice_id,
            pi.processed_at,
            d.amount_cents,
            d.epn_ticket as transaction_id,
            m.first_name,
            m.last_name,
            m.email,
            m.valid_until,
            m.status
        FROM payment_intents pi
        LEFT JOIN members m ON pi.member_id = m.id
        LEFT JOIN dues d ON pi.invoice_id = d.id
        WHERE pi.processed_at IS NOT NULL
        ORDER BY pi.processed_at DESC
        LIMIT :limit
    ");
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    $quickpays = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format member names and add reactivation status
    foreach ($quickpays as &$qp) {
        $qp['member_name'] = trim(($qp['first_name'] ?? '') . ' ' . ($qp['last_name'] ?? ''));

        // Check if payment extended/reactivated access
        $validUntil = $qp['valid_until'] ? strtotime($qp['valid_until']) : null;
        $now = time();
        if ($validUntil && $validUntil > $now && $qp['status'] === 'current') {
            $qp['reactivated'] = true;
            $qp['valid_until_formatted'] = date('M j, Y', $validUntil);
        } else {
            $qp['reactivated'] = false;
        }

        unset($qp['first_name'], $qp['last_name'], $qp['valid_until'], $qp['status']);
    }

    echo json_encode([
        'ok' => true,
        'quickpays' => $quickpays,
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
