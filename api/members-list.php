<?php
require __DIR__ . '/../_bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

try {
  $stmt = $pdo->query("
    SELECT 
      id,
      first_name,
      last_name,
      department_name,
      card_number,
      payment_type,
      monthly_fee,
      valid_from,
      valid_until,
      is_primary,
      primary_member_id
    FROM members
    ORDER BY last_name, first_name
  ");
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

  $now = new DateTime('now');
  $members = [];

  foreach ($rows as $m) {
    // Skip dependents for main table
    if (!empty($m['primary_member_id'])) continue;

    $status = 'unknown';
    $until = $m['valid_until'] ? new DateTime($m['valid_until']) : null;

    // 🔍 Smart status logic
    if ($until) {
      if ($until >= $now) {
        $status = 'current';
      } else {
        $status = 'due';
      }
    } else {
      $status = 'draft';
    }

    $members[] = [
      'id' => (int)$m['id'],
      'first_name' => $m['first_name'],
      'last_name' => $m['last_name'],
      'department_name' => $m['department_name'],
      'card_number' => $m['card_number'],
      'payment_type' => $m['payment_type'],
      'monthly_fee' => (float)$m['monthly_fee'],
      'valid_from' => $m['valid_from'],
      'valid_until' => $m['valid_until'],
      'status' => $status,
    ];
  }

  echo json_encode([
    'ok' => true,
    'count' => count($members),
    'members' => $members
  ], JSON_PRETTY_PRINT);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode([
    'error' => 'server_error',
    'detail' => $e->getMessage()
  ]);
}
