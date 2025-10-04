<?php
require __DIR__ . '/../_bootstrap.php';
header('Content-Type: application/json');
ini_set('display_errors', 0);
error_reporting(E_ALL);

function norm($s){ 
  return preg_replace('/\s+/', ' ', strtolower(trim($s ?? ''))); 
}

try {
  $first = norm($_GET['first'] ?? '');
  $last  = norm($_GET['last'] ?? '');
  if ($first === '' || $last === '') {
    http_response_code(400);
    echo json_encode(['error'=>'first_last_required']);
    exit;
  }

  // --- Member lookup ---
  $stmt = $pdo->prepare("
    SELECT id, first_name, last_name, email, zip, updated_at,
           COALESCE(department_name,'') AS department_name,
           COALESCE(card_number,'') AS card_number,
           COALESCE(valid_from,'') AS valid_from,
           COALESCE(valid_until,'') AS valid_until,
           COALESCE(monthly_fee,0) AS monthly_fee
    FROM members
    WHERE LOWER(TRIM(first_name)) = :fn
      AND LOWER(TRIM(last_name))  = :ln
    ORDER BY COALESCE(updated_at,'1970-01-01') DESC, id DESC
    LIMIT 5
  ");
  $stmt->execute([':fn'=>$first, ':ln'=>$last]);
  $candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);

  if (!$candidates) {
    echo json_encode([
      'status'    => 'not_found',
      'member'    => null,
      'invoice'   => null,
      'last_paid' => null
    ]);
    exit;
  }

  $member = $candidates[0];

  // --- Current due invoice ---
  $inv = $pdo->prepare("
    SELECT id, member_id, period_start, period_end, amount_cents, currency, status, updated_at
    FROM dues
    WHERE member_id = :mid AND status = 'due'
    ORDER BY period_end DESC, id DESC
    LIMIT 1
  ");
  $inv->execute([':mid'=>$member['id']]);
  $invoice = $inv->fetch(PDO::FETCH_ASSOC) ?: null;

  // --- Last paid invoice ---
  $paid = $pdo->prepare("
    SELECT id, member_id, period_start, period_end, amount_cents, currency, status, updated_at
    FROM dues
    WHERE member_id = :mid AND status = 'paid'
    ORDER BY period_end DESC, id DESC
    LIMIT 1
  ");
  $paid->execute([':mid'=>$member['id']]);
  $lastPaid = $paid->fetch(PDO::FETCH_ASSOC) ?: null;

  // --- Active amount (either current invoice or member monthly fee) ---
  $amountCents = $invoice 
    ? (int)$invoice['amount_cents'] 
    : (int)($member['monthly_fee'] * 100);

  echo json_encode([
    'status'    => $invoice ? 'due' : 'clear',
    'member'    => [
      'id'            => (int)$member['id'],
      'first_name'    => $member['first_name'],
      'last_name'     => $member['last_name'],
      'email'         => $member['email'],
      'zip'           => $member['zip'],
      'department'    => $member['department_name'],
      'card_number'   => $member['card_number'],
      'valid_from'    => $member['valid_from'],
      'valid_until'   => $member['valid_until'],
      'monthly_fee'   => (float)$member['monthly_fee'],
      'active_amount' => $amountCents / 100,  // dollars
      'updated_at'    => $member['updated_at'],
    ],
    'invoice'   => $invoice,
    'last_paid' => $lastPaid
  ]);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode([
    'error'  => 'server_error',
    'detail' => $e->getMessage()
  ]);
}

