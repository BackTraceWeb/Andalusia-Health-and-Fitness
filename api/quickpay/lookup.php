<?php
require __DIR__ . '/../_bootstrap.php';
header('Content-Type: application/json');
ini_set('display_errors', 0);

function norm($s){ return preg_replace('/\s+/', ' ', strtolower(trim($s ?? ''))); }

try {
  $first = norm($_GET['first'] ?? '');
  $last  = norm($_GET['last'] ?? '');
  if ($first === '' || $last === '') {
    http_response_code(400);
    echo json_encode(['error'=>'first_last_required']);
    exit;
  }

  // Exact match first/last; pick most recently updated
  $stmt = $pdo->prepare("
    SELECT id, first_name, last_name, email, zip, updated_at
    FROM members
    WHERE LOWER(TRIM(first_name)) = :fn
      AND LOWER(TRIM(last_name))  = :ln
    ORDER BY COALESCE(updated_at,'1970-01-01') DESC, id DESC
    LIMIT 5
  ");
  $stmt->execute([':fn'=>$first, ':ln'=>$last]);
  $candidates = $stmt->fetchAll();

  if (!$candidates) {
    echo json_encode(['status'=>'not_found','member'=>null,'invoice'=>null]);
    exit;
  }

  $member = $candidates[0];

  // Latest due invoice
  $inv = $pdo->prepare("
    SELECT id, member_id, period_start, period_end, amount_cents, currency, status
    FROM dues
    WHERE member_id = :mid AND status = 'due'
    ORDER BY period_end DESC, id DESC
    LIMIT 1
  ");
  $inv->execute([':mid'=>$member['id']]);
  $invoice = $inv->fetch();

  echo json_encode([
    'status'  => $invoice ? 'due' : 'clear',
    'member'  => [
      'id'         => (int)$member['id'],
      'first_name' => $member['first_name'],
      'last_name'  => $member['last_name'],
      'email'      => $member['email'],
      'zip'        => $member['zip'],
      'updated_at' => $member['updated_at'],
    ],
    'invoice' => $invoice ?: null
  ]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['error'=>'server_error']);
}
