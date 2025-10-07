<?php
require __DIR__ . '/../_bootstrap.php';
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
if (!$data) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>'bad_request']);
  exit;
}

$id   = isset($data['id']) ? (int)$data['id'] : 0;
$name = trim($data['department_name'] ?? '');
$base = (float)($data['base_price'] ?? 0);
$tan  = (float)($data['tanning_price'] ?? 0);

if ($name === '') {
  echo json_encode(['ok'=>false,'error'=>'missing_name']);
  exit;
}

try {
  if ($id > 0) {
    $stmt = $pdo->prepare("UPDATE department_pricing SET department_name=?, base_price=?, tanning_price=? WHERE dept_price_id=?");
    $ok = $stmt->execute([$name, $base, $tan, $id]);
  } else {
    $stmt = $pdo->prepare("INSERT INTO department_pricing (department_name, base_price, tanning_price) VALUES (?, ?, ?)");
    $ok = $stmt->execute([$name, $base, $tan]);
  }
  echo json_encode(['ok'=>$ok]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
