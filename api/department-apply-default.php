<?php
require __DIR__ . '/../_bootstrap.php';
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$id = isset($data['department_id']) ? (int)$data['department_id'] : 0;
if ($id <= 0) {
  http_response_code(400);
  echo json_encode(['ok'=>false,'error'=>'invalid_department']);
  exit;
}

try {
  // Fetch department info
  $dept = $pdo->prepare("SELECT department_name, base_price FROM department_pricing WHERE dept_price_id=?");
  $dept->execute([$id]);
  $row = $dept->fetch(PDO::FETCH_ASSOC);
  if (!$row) throw new Exception('Department not found');

  // Update members who belong to this department
  $stmt = $pdo->prepare("
    UPDATE members 
    SET monthly_fee = :fee, updated_at = NOW()
    WHERE department_name = :dept
  ");
  $stmt->execute([':fee'=>$row['base_price'], ':dept'=>$row['department_name']]);
  $count = $stmt->rowCount();

  echo json_encode(['ok'=>true,'updated'=>$count]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
