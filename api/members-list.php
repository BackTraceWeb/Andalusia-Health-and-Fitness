<?php
require __DIR__ . '/../_bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

$logFile = __DIR__ . '/../logs/members-list-debug.log';

try {
    $pdo = pdo();

    // Get optional status filter from query parameter (?status=active or ?status=inactive)
    $statusFilter = $_GET['status'] ?? 'active';  // Default to active members only

    // --- Fetch members
    $sql = "SELECT
                id,
                first_name,
                last_name,
                department_name,
                payment_type,
                monthly_fee,
                valid_from,
                valid_until,
                status,
                is_draft,
                allow_quickpay,
                notes
            FROM members";

    // Add WHERE clause based on status filter
    if ($statusFilter === 'active') {
        $sql .= " WHERE status != 'inactive'";
    } elseif ($statusFilter === 'inactive') {
        $sql .= " WHERE status = 'inactive'";
    }
    // 'all' returns everything (no WHERE clause)

    $sql .= " ORDER BY id DESC";

    $stmt = $pdo->query($sql);
    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);

    file_put_contents($logFile, date('c') . " - fetched " . count($members) . " members\n", FILE_APPEND);

    foreach ($members as &$m) {
        // Format values
        $m['monthly_fee'] = number_format((float)$m['monthly_fee'], 2);
        $m['valid_from'] = $m['valid_from'] ?: '';
        $m['valid_until'] = $m['valid_until'] ?: '';
        $m['is_draft'] = (int)($m['is_draft'] ?? 0);
        $m['allow_quickpay'] = (int)($m['allow_quickpay'] ?? 0);
        $m['notes'] = $m['notes'] ?? '';

        // Use status from database (already calculated in sync)
        $m['status'] = $m['status'] ?: 'current';
    }
    unset($m);

    echo json_encode([
        'ok' => true,
        'members' => $members
    ], JSON_PRETTY_PRINT);

} catch (Throwable $e) {
    file_put_contents($logFile, date('c') . " - error: " . $e->getMessage() . "\n", FILE_APPEND);
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage()
    ]);
}
