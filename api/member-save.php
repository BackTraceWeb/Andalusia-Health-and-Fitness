<?php
require __DIR__ . '/../_bootstrap.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

$pdo = pdo();
$logFile = __DIR__ . '/../logs/member-save-debug.log';

// Safe logger
function logDebug($msg) {
    global $logFile;
    @file_put_contents($logFile, date('c') . " - $msg\n", FILE_APPEND);
}

header('Content-Type: application/json; charset=utf-8');

logDebug("member-save invoked with POST: " . json_encode($_POST));

try {
    // Collect & sanitize
    $id             = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    $first_name     = isset($_POST['first_name']) ? trim($_POST['first_name']) : '';
    $last_name      = isset($_POST['last_name']) ? trim($_POST['last_name']) : '';
    $company_name   = isset($_POST['company_name']) ? trim($_POST['company_name']) : '';
    $department     = isset($_POST['department_name']) ? trim($_POST['department_name']) : '';
    $payment_type   = isset($_POST['payment_type']) ? trim($_POST['payment_type']) : 'card';
    $status         = isset($_POST['status']) ? trim($_POST['status']) : 'current';
    $monthly_fee    = isset($_POST['monthly_fee']) ? (float)$_POST['monthly_fee'] : 0.00;
    $valid_from     = trim($_POST['valid_from'] ?? '');
    $valid_until    = trim($_POST['valid_until'] ?? '');

    if ($id <= 0) {
        http_response_code(400);
        echo json_encode(['ok' => false, 'error' => 'invalid_id']);
        exit;
    }

    // Explicitly ensure no nulls for name fields
    if ($first_name === null || $first_name === 'null') $first_name = '';
    if ($last_name === null || $last_name === 'null') $last_name = '';

    // Update SQL
    $sql = "
        UPDATE members
        SET
          first_name      = :first_name,
          last_name       = :last_name,
          company_name    = :company_name,
          department_name = :department_name,
          payment_type    = :payment_type,
          status          = :status,
          monthly_fee     = :monthly_fee,
          valid_from      = :valid_from,
          valid_until     = :valid_until,
          updated_at      = NOW()
        WHERE id = :id
    ";

    $stmt = $pdo->prepare($sql);

    $params = [
        ':first_name'      => (string)$first_name,
        ':last_name'       => (string)$last_name,
        ':company_name'    => $company_name ?: null,
        ':department_name' => $department ?: null,
        ':payment_type'    => $payment_type,
        ':status'          => $status,
        ':monthly_fee'     => $monthly_fee,
        ':valid_from'      => $valid_from ?: null,
        ':valid_until'     => $valid_until ?: null,
        ':id'              => $id
    ];

    $stmt->execute($params);

    logDebug("Update succeeded for ID $id with params: " . json_encode($params));
    echo json_encode(['ok' => true, 'message' => 'Member updated successfully']);

} catch (Throwable $e) {
    logDebug("Error for ID $id: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'server_error',
        'detail' => $e->getMessage()
    ]);
}
