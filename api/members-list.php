<?php
require __DIR__ . '/../_bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

$logFile = __DIR__ . '/../logs/members-list-debug.log';

try {
    $pdo = pdo();

    // --- Fetch members (no last_updated column)
    $sql = "SELECT 
                id,
                first_name,
                last_name,
                department_name,
                payment_type,
                monthly_fee,
                valid_from,
                valid_until
            FROM members
            ORDER BY id DESC";
    $stmt = $pdo->query($sql);
    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);

    file_put_contents($logFile, date('c') . " - fetched " . count($members) . " members\n", FILE_APPEND);

    $today = new DateTime('today');

    foreach ($members as &$m) {
        $validUntil = !empty($m['valid_until']) ? new DateTime($m['valid_until']) : null;

        // Always current if payment type is draft
        if (strtolower(trim($m['payment_type'])) === 'draft') {
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

        // Format values
        $m['monthly_fee'] = number_format((float)$m['monthly_fee'], 2);
        $m['valid_from'] = $m['valid_from'] ?: '';
        $m['valid_until'] = $m['valid_until'] ?: '';
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
