<?php
header('Content-Type: application/json');

// Standard database include — same as in member-save.php
require_once __DIR__ . '/db.php';

try {
    $search = isset($_GET['search']) ? trim($_GET['search']) : '';
    $id = isset($_GET['id']) ? intval($_GET['id']) : 0;

    if (!$search && !$id) {
        echo json_encode(['ok' => false, 'error' => 'missing_parameter']);
        exit;
    }

    if ($id > 0) {
        $stmt = $pdo->prepare("SELECT * FROM members WHERE id = ?");
        $stmt->execute([$id]);
    } else {
        // Allow partial name matches, case insensitive
        $stmt = $pdo->prepare("
            SELECT * FROM members 
            WHERE first_name LIKE ? OR last_name LIKE ?
            ORDER BY id DESC
            LIMIT 1
        ");
        $stmt->execute(["%$search%", "%$search%"]);
    }

    $member = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$member) {
        echo json_encode(['ok' => false, 'error' => 'not_found']);
        exit;
    }

    // Normalize and calculate
    $status = strtolower(trim($member['status'] ?? ''));
    $monthly_fee = floatval($member['monthly_fee'] ?? 0);
    $amount_cents = intval($monthly_fee * 100);

    // Simulated invoice object (for payment gateway)
    $invoice = [
        'id' => 'INV-' . $member['id'] . '-' . date('Ym'),
        'amount_cents' => $amount_cents,
        'period_start' => date('Y-m-01'),
        'period_end' => date('Y-m-t')
    ];

    echo json_encode([
        'ok' => true,
        'member' => [
            'id' => $member['id'],
            'first_name' => $member['first_name'],
            'last_name' => $member['last_name'],
            'department_name' => $member['department_name'] ?? '',
            'payment_type' => $member['payment_type'] ?? '',
            'monthly_fee' => number_format($monthly_fee, 2),
            'status' => $status,
            'valid_from' => $member['valid_from'] ?? '',
            'valid_until' => $member['valid_until'] ?? '',
        ],
        'invoice' => $invoice,
        'status' => $status
    ]);
} catch (Throwable $e) {
    echo json_encode([
        'ok' => false,
        'error' => 'server_error',
        'details' => $e->getMessage()
    ]);
}
