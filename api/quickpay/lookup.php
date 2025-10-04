<?php
require __DIR__ . '/../_bootstrap.php';
$pdo = pdo();
 $dbName = $pdo->query("SELECT DATABASE()")->fetchColumn();
echo json_encode(['debug_db' => $dbName]); exit;


header('Content-Type: application/json');
ini_set('display_errors', 0);

function norm($s){ return preg_replace('/\s+/', ' ', strtolower(trim($s ?? ''))); }

$logFile = __DIR__ . '/../../logs/lookup-debug.log';
if (!is_dir(dirname($logFile))) {
    mkdir(dirname($logFile), 0775, true);
}
file_put_contents($logFile, date('c') . " --- Lookup start ---\n", FILE_APPEND);

try {
  // ✅ Confirm DB connection
  if (!isset($pdo) || !$pdo instanceof PDO) {
    throw new Exception('Database connection not initialized');
  }

  $dbName = $pdo->query("SELECT DATABASE()")->fetchColumn();
  file_put_contents($logFile, "Connected to DB: {$dbName}\n", FILE_APPEND);

  $first = norm($_GET['first'] ?? '');
  $last  = norm($_GET['last'] ?? '');
  if ($first === '' || $last === '') {
    http_response_code(400);
    echo json_encode(['error'=>'first_last_required']);
    exit;
  }

  // 🧠 Query members
  $sql = "
    SELECT id, first_name, last_name, email, zip, updated_at,
           department_name, card_number, valid_from, valid_until, monthly_fee
    FROM members
    WHERE LOWER(TRIM(first_name)) = :fn
      AND LOWER(TRIM(last_name))  = :ln
    ORDER BY COALESCE(updated_at,'1970-01-01') DESC, id DESC
    LIMIT 5
  ";
  file_put_contents($logFile, "Running query for: {$first} {$last}\n", FILE_APPEND);

  $stmt = $pdo->prepare($sql);
  $stmt->execute([':fn'=>$first, ':ln'=>$last]);
  $candidates = $stmt->fetchAll();

  if (!$candidates) {
    file_put_contents($logFile, "No members found\n", FILE_APPEND);
    echo json_encode(['status'=>'not_found','member'=>null,'invoice'=>null,'last_paid'=>null]);
    exit;
  }

  $member = $candidates[0];
  file_put_contents($logFile, "Found member ID {$member['id']}\n", FILE_APPEND);

  // 🧾 Invoice lookups
  $inv = $pdo->prepare("
    SELECT id, member_id, period_start, period_end, amount_cents, currency, status, updated_at
    FROM dues
    WHERE member_id = :mid AND status = 'due'
    ORDER BY period_end DESC, id DESC
    LIMIT 1
  ");
  $inv->execute([':mid'=>$member['id']]);
  $invoice = $inv->fetch();

  $paid = $pdo->prepare("
    SELECT id, member_id, period_start, period_end, amount_cents, currency, status, updated_at
    FROM dues
    WHERE member_id = :mid AND status = 'paid'
    ORDER BY period_end DESC, id DESC
    LIMIT 1
  ");
  $paid->execute([':mid'=>$member['id']]);
  $lastPaid = $paid->fetch();

  $amountCents = $invoice ? (int)$invoice['amount_cents'] : (int)($member['monthly_fee'] * 100);

  file_put_contents($logFile, "Returning member + invoice\n", FILE_APPEND);

  echo json_encode([
    'status'    => $invoice ? 'due' : 'clear',
    'member'    => [
      'id'             => (int)$member['id'],
      'first_name'     => $member['first_name'],
      'last_name'      => $member['last_name'],
      'email'          => $member['email'],
      'zip'            => $member['zip'],
      'department'     => $member['department_name'],
      'card_number'    => $member['card_number'],
      'valid_from'     => $member['valid_from'],
      'valid_until'    => $member['valid_until'],
      'monthly_fee'    => $member['monthly_fee'],
      'active_amount'  => $amountCents / 100,
      'updated_at'     => $member['updated_at'],
    ],
    'invoice'   => $invoice ?: null,
    'last_paid' => $lastPaid ?: null
  ]);

} catch (Throwable $e) {
  file_put_contents($logFile, "ERROR: " . $e->getMessage() . "\n", FILE_APPEND);
  http_response_code(500);
  echo json_encode(['error'=>'server_error','detail'=>$e->getMessage()]);
}
