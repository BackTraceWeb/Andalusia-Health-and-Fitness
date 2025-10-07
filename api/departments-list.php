<?php
require __DIR__ . '/../_bootstrap.php';
header('Content-Type: application/json');

try {
  $rows = $pdo->query("SELECT * FROM department_pricing ORDER BY department_name")->fetchAll(PDO::FETCH_ASSOC);
  echo json_encode(['ok'=>true,'departments'=>$rows]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
}
