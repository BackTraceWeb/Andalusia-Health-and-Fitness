<?php
header('Content-Type: application/json');
require_once __DIR__ . '/db.php'; // adjust if your DB include path differs

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

try {
  if (!$search && !$id) {
    echo json_encode(['ok' => false, 'error' => 'missing_parameter']);
    exit;
  }

  if ($id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM members WHERE id = ?");
    $stmt->execute([$id]);
  } else {
    // support searching by name
    $stmt = $pdo->prepare("SELECT * FROM members WHERE first_name LIKE ? OR last_name LIKE ? LIMIT 1");
    $stmt->execute(["%$search%", "%$search%"]);
  }

  $member = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$member) {
    echo json_encode(['ok' => false, 'error' => 'not_found']);
    exit;
  }

  // determine payment status
  $status = strtolower(trim($member['status'] ?? ''));
  $amount_cents = isset($member['monthly_fee']) ? intval(floatval($member['monthly_fee']) * 100) : 0;

  // build simulated invoice
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
      'monthly_fee' => $member['monthly_fee'] ?? '0.00',
      'status' => $status,
      'valid_from' => $member['valid_from'] ?? '',
      'valid_until' => $member['valid_until'] ?? ''
    ],
    'invoice' => $invoice,
    'status' => $status
  ]);
} catch (Exception $e) {
  echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
