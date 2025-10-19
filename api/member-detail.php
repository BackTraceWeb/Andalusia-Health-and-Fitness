<?php
header('Content-Type: application/json');

// ------------------------------------------------------------------
// Load global bootstrap for database connection
// ------------------------------------------------------------------
require_once __DIR__ . '/../_bootstrap.php';
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
  // LOOKUP MEMBER
  // ----------------------------------------------------------------
  if ($id > 0) {
    // Direct ID lookup
    $stmt = $pdo->prepare("SELECT * FROM members WHERE id = ?");
    $stmt->execute([$id]);
  } else {
    // Split the search into first and last name parts
    $parts = preg_split('/\s+/', $search);
    $first = $parts[0] ?? '';
    $last  = $parts[1] ?? '';

    if ($first && $last) {
      // Require both first and last name for accurate match
      $stmt = $pdo->prepare("
        SELECT * FROM members
        WHERE LOWER(first_name) = LOWER(?) 
          AND LOWER(last_name) = LOWER(?)
        ORDER BY id DESC
        LIMIT 1
      ");
      $stmt->execute([$first, $last]);
    } else {
      // Fallback: partial search (for admin or debugging)
      $stmt = $pdo->prepare("
        SELECT * FROM members
        WHERE LOWER(first_name) LIKE LOWER(?) 
           OR LOWER(last_name) LIKE LOWER(?)
        ORDER BY id DESC
        LIMIT 1
      ");
      $stmt->execute(["%$search%", "%$search%"]);
    }
  }

  $member = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$member) {
    echo json_encode(['ok' => false, 'error' => 'member_not_found']);
    exit;
  }

  // ----------------------------------------------------------------
  // GET MOST RECENT DUES RECORD
  // ----------------------------------------------------------------
  $due = null;
  $dueCheck = $pdo->prepare("
    SELECT *
    FROM dues
    WHERE member_id = ?
    ORDER BY period_end DESC
    LIMIT 1
  ");
  $dueCheck->execute([$member['id']]);
  $due = $dueCheck->fetch(PDO::FETCH_ASSOC);

  // ----------------------------------------------------------------
  // DETERMINE STATUS
  // ----------------------------------------------------------------
  $status = $member['status'] ?? 'current';
  $now = time();

  $validUntil = !empty($member['valid_until']) ? strtotime($member['valid_until']) : 0;

  // Logic: mark as "due" if payment_type is card AND (expired OR unpaid)
  if (
    strtolower($member['payment_type']) === 'card' &&
    (
      ($validUntil && $validUntil < $now) ||
      ($due && isset($due['is_paid']) && !$due['is_paid'])
    )
  ) {
    $status = 'due';
  }

    // ----------------------------------------------------------------
  // OUTPUT JSON
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
      'status'          => $status,
    ],
    // ✅ rename dues → invoice so JS reads it properly
    'invoice' => $due ? [
      'id'            => (int)$due['id'],
      'period_start'  => $due['period_start'] ?? '',
      'period_end'    => $due['period_end'] ?? '',
      'amount_cents'  => isset($due['amount_cents']) ? (int)$due['amount_cents'] : 0,
      'is_paid'       => (bool)($due['is_paid'] ?? 0)
    ] : null,
    'status' => $status
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

