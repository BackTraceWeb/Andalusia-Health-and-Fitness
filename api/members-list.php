<?php
require __DIR__ . '/../_bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = pdo();

    // Fetch members with department name
    $stmt = $pdo->query("
        SELECT 
            m.id,
            m.first_name,
            m.last_name,
            m.department_name,
            m.payment_type,
            m.monthly_fee,
            m.valid_from,
            m.valid_until,
            m.last_updated
        FROM members m
        ORDER BY m.id DESC
    ");
    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // --- Determine current/due status ---
    $today = new DateTime('today');

    foreach ($members as &$m) {
        $validUntil = !empty($m['valid_until']) ? new DateTime($m['valid_until']) : null;

        // Always current if payment type is draft
        if (strtolower($m['payment_type']) === 'draft') {
            $m['status'] = 'current';
        }
        // Otherwise current if still valid
        elseif ($validUntil && $validUntil >= $today) {
            $m['status'] = 'current';
        }
        // Otherwise due
        else {
            $m['status'] = 'due';
        }

        // Format display values for frontend
        $m['monthly_fee'] = number_format((float)$m['monthly_fee'], 2);
        $m['valid_until'] = $m['valid_until'] ?: '';
        $m['valid_from'] = $m['valid_from'] ?: '';
    }
    unset($m);

    echo json_encode([
        'ok' => true,
        'members' => $members
    ], JSON_PRETTY_PRINT);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage()
    ]);
}
