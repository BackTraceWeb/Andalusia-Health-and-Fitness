<?php
header('Content-Type: application/json');

// ------------------------------------------------------------------
// Load global bootstrap (provides pdo() function and DB connection)
// ------------------------------------------------------------------
require_once __DIR__ . '/../_bootstrap.php';   // go up one level from /api/
$pdo = pdo();

if (!$pdo) {
  echo json_encode(['ok' => false, 'error' => 'db_not_connected']);
  exit;
}

// ------------------------------------------------------------------
// INPUT HANDLING
// ------------------------------------------------------------------
$id     = isset($_GET['id']) ? intval($_GET['id']) : 0;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

if ($id <= 0 && $search === '') {
  echo json_encode(['ok' => false, 'error' => 'missing_parameters']);
  exit;
}

try {
  // ----------------------------------------------------------------
  // LOOKUP BY ID OR NAME
  // ----------------------------------------------------------------
  if ($id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM members WHERE id = ?");
    $stmt->execute([$id]);
  } else {
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
    echo json_encode(['ok' => false, 'error' => 'member_not_found']);
    exit;
  }

  // ----------------------------------------------------------------
  // GET INVOICE IF ANY DUE
  // ----------------------------------------------------------------
  $invoice = null;
  $dueCheck = $pdo->prepare("
    SELECT * FROM invoices
    WHERE member_id = ? AND status = 'due'
    ORDER BY id DESC
    LIMIT 1
  ");
  $dueCheck->execute([$member['id']]);
  $invoice = $dueCheck->fetch(PDO::FETCH_ASSOC);

  // ----------------------------------------------------------------
  // FORMAT RESPONSE
  // ----------------------------------------------------------------
  echo json_encode([
    'ok' => true,
    'member' => [
      'id'              => (int)$member['id'],
      'first_name'      => $member['first_name'],
      'last_name'       => $member['last_name'],
      'department_name' => $member['department_name'] ?? '',
      'payment_type'    => $member['payment_type'] ?? '',
      'monthly_fee'     => $member['monthly_fee'] ?? '0.00',
      'valid_from'      => $member['valid_from'] ?? null,
      'valid_until'     => $member['valid_until'] ?? null,
      'status'          => $member['status'] ?? '',
    ],
    'invoice' => $invoice ? [
      'id'            => (int)$invoice['id'],
      'amount_cents'  => (int)($invoice['amount_cents'] ?? 0),
      'period_start'  => $invoice['period_start'] ?? '',
      'period_end'    => $invoice['period_end'] ?? '',
      'status'        => $invoice['status'] ?? ''
    ] : null
  ]);

} catch (Exception $e) {
  http_response_code(500);
  echo json_encode([
    'ok' => false,
    'error' => 'server_error',
    'message' => $e->getMessage()
  ]);
  exit;
}
