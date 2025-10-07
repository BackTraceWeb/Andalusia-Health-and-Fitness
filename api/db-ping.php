<?php
require __DIR__.'/_bootstrap.php';
header('Content-Type: application/json');
try {
  pdo()->query('SELECT 1');
  echo json_encode(['db' => 'ok', 'ts' => gmdate('c')]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['db' => 'fail', 'err' => $e->getMessage()]);
}
