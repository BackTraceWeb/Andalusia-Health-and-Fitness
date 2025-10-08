<?php
require __DIR__ . '/../_bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = pdo();

    // Fetch member records
    $stmt = $pdo->query("
        SELECT 
            id,
            first_name,
            last_name,
            department_name,
            payment_type,
            monthly_fee,
            valid_from,
            valid_until,
            last_updated
        FROM members
        ORDER BY id DESC
    ");

    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $today = new DateTime('today');

    foreach ($members as &$m) {
        // Parse date if exists
        $validUntil = !empty($m['valid_until']) ? new DateTime($m['valid_until']) : null;

        // ✅ Always current if payment type is draft
        if (strtolower(trim($m['payment_type'])) === 'draft') {
            $m['status'] = 'current';
        }
        // ✅ Otherwise current if still valid
        elseif ($validUntil && $validUntil >= $today) {
            $m['status'] = 'current';
        }
        // ❌ Otherwise due
        else {
            $m['status'] = 'due';
        }

        // Format for display
        $m['monthly_fee'] = number_format((float)$m['monthly_fee'], 2);
        $m['valid_until'] = $m['valid_until'] ?: '';
        $m['valid_from'] = $m['valid_from'] ?: '';
    }
    unset($m);

    echo json_encode([
        'ok' => true,
        'members' => $members
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage()
    ]);
}
