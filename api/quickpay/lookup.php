<?php
ob_clean();
require __DIR__ . '/../../_bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
ini_set('display_errors', 0);

function norm($s) {
  return preg_replace('/\s+/', ' ', strtolower(trim($s ?? '')));
}

$logFile = __DIR__ . '/../../../logs/lookup-debug.log';
if (!is_dir(dirname($logFile))) mkdir(dirname($logFile), 0775, true);
file_put_contents($logFile, date('c') . " --- Lookup start ---\n", FILE_APPEND);

try {
  // Confirm DB
  $dbName = $pdo->query("SELECT DATABASE()")->fetchColumn();
  file_put_contents($logFile, "Connected to DB: {$dbName}\n", FILE_APPEND);

  $first = norm($_GET['first'] ?? '');
  $last  = norm($_GET['last'] ?? '');
  $company = norm($_GET['company'] ?? '');

  if ($first === '' && $last === '' && $company === '') {
    http_response_code(400);
    echo json_encode(['error' => 'first_or_last_or_company_required']);
    exit;
  }

  // --- Lookup member (by name or company) ---
  $stmt = $pdo->prepare("
    SELECT 
      id, first_name, last_name, company_name,
      department_name, card_number, valid_from, valid_until,
      monthly_fee, payment_type, status
    FROM members
    WHERE 
      (LOWER(TRIM(first_name)) = :fn AND LOWER(TRIM(last_name)) = :ln)
      OR LOWER(TRIM(company_name)) = :company
    ORDER BY id DESC
    LIMIT 1
  ");
  $stmt->execute([
    ':fn'      => $first,
    ':ln'      => $last,
    ':company' => $company ?: "$first $last"
  ]);

  $member = $stmt->fetch();

  if (!$member) {
    file_put_contents($logFile, "No member found\n", FILE_APPEND);
    echo json_encode(['status' => 'not_found']);
    flush();
    exit;
  }

  file_put_contents($logFile, "Found member ID {$member['id']}\n", FILE_APPEND);

  // --- Determine current/due status ---
  $today = new DateTimeImmutable('today');
  $validUntil = $member['valid_until'] ? new DateTimeImmutable($member['valid_until']) : null;
  $isCurrent = ($validUntil && $validUntil >= $today);

  if ($member['payment_type'] === 'draft') {
    $isCurrent = true;
  }

  // --- Lookup dues invoice if due ---
  $invoice = null;
  if (!$isCurrent) {
    $inv = $pdo->prepare("
      SELECT id, member_id, period_start, period_end, amount_cents, currency, status
      FROM dues
      WHERE member_id = :mid AND status = 'due'
      ORDER BY period_end DESC, id DESC
      LIMIT 1
    ");
    $inv->execute([':mid' => $member['id']]);
    $invoice = $inv->fetch();
  }

  $amountCents = $invoice ? (int)$invoice['amount_cents'] : (int)($member['monthly_fee'] * 100);

  $result = [
    'status'       => $isCurrent ? 'current' : 'due',
    'member'       => $member,
    'invoice'      => $invoice,
    'amount'       => $amountCents / 100,
    'valid_until'  => $member['valid_until']
  ];

  echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
  flush();

  file_put_contents($logFile, "Returning member {$member['id']} OK\n", FILE_APPEND);

} catch (Throwable $e) {
  file_put_contents($logFile, "ERROR: " . $e->getMessage() . "\n", FILE_APPEND);
  http_response_code(500);
  echo json_encode([
    'error' => 'server_error',
    'detail' => $e->getMessage()
  ]);
  flush();
}
