<?php
require __DIR__ . '/../_bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

$logFile = __DIR__ . '/../logs/member-detail-debug.log';

try {
    $pdo = pdo();

    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'invalid_id']);
        exit;
    }

    // Fetch main member info
    $stmt = $pdo->prepare("SELECT * FROM members WHERE id = ?");
    $stmt->execute([$id]);
    $member = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$member) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'member_not_found']);
        exit;
    }

    // Determine status (drafts always current)
    $today = new DateTime('today');
    $validUntil = !empty($member['valid_until']) ? new DateTime($member['valid_until']) : null;

    if (strtolower(trim($member['payment_type'])) === 'draft') {
        $member['status'] = 'current';
    } elseif ($validUntil && $validUntil >= $today) {
        $member['status'] = 'current';
    } else {
        $member['status'] = 'due';
    }

    // Format monetary and date fields
    $member['monthly_fee'] = number_format((float)$member['monthly_fee'], 2);
    $member['valid_from'] = $member['valid_from'] ?: '';
    $member['valid_until'] = $member['valid_until'] ?: '';

    // Fetch dues (if applicable)
    $duesStmt = $pdo->prepare("
        SELECT id, period_start, period_end, amount_cents, status
        FROM dues
        WHERE member_id = ?
        ORDER BY id DESC
        LIMIT 12
    ");
    $duesStmt->execute([$id]);
    $dues = $duesStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($dues as &$d) {
        $d['amount'] = '$' . number_format(((int)$d['amount_cents']) / 100, 2);
        $d['status'] = $d['status'] ?: 'due';
    }
    unset($d);

    // Logging
    file_put_contents(
        $logFile,
        date('c') . " - member #{$id} fetched; status={$member['status']}\n",
        FILE_APPEND
    );

    echo json_encode([
        'ok' => true,
        'member' => $member,
        'dues' => $dues
    ], JSON_PRETTY_PRINT);

} catch (Throwable $e) {
    file_put_contents(
        $logFile,
        date('c') . " - error for member #{$id}: {$e->getMessage()}\n",
        FILE_APPEND
    );
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'server_error',
        'detail' => $e->getMessage()
    ]);
}
