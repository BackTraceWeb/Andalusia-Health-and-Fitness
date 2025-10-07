<?php
require __DIR__ . '/../_bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

try {
  // Sanitize and collect input
  $id             = isset($_POST['id']) ? (int)$_POST['id'] : 0;
  $first_name     = trim($_POST['first_name'] ?? '');
  $last_name      = trim($_POST['last_name'] ?? '');
  $department     = trim($_POST['department_name'] ?? '');
  $payment_type   = trim($_POST['payment_type'] ?? 'card');
  $status         = trim($_POST['status'] ?? 'current');
  $monthly_fee    = isset($_POST['monthly_fee']) ? (float)$_POST['monthly_fee'] : 0.00;
  $valid_from     = trim($_POST['valid_from'] ?? '');
  $valid_until    = trim($_POST['valid_until'] ?? '');

  if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid_id']);
    exit;
  }

  // Validate required fields
  if ($first_name === '' || $last_name === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'missing_name']);
    exit;
  }

  // Update query
  $stmt = $pdo->prepare("
    UPDATE members
    SET
      first_name = :first_name,
      last_name = :last_name,
      department_name = :department_name,
      payment_type = :payment_type,
      status = :status,
      monthly_fee = :monthly_fee,
      valid_from = :valid_from,
      valid_until = :valid_until,
      updated_at = NOW()
    WHERE id = :id
  ");

  $stmt->execute([
    ':first_name'      => $first_name,
    ':last_name'       => $last_name,
    ':department_name' => $department,
    ':payment_type'    => $payment_type,
    ':status'          => $status,
    ':monthly_fee'     => $monthly_fee,
    ':valid_from'      => $valid_from ?: null,
    ':valid_until'     => $valid_until ?: null,
    ':id'              => $id
  ]);

  echo json_encode(['ok' => true, 'message' => 'Member updated successfully']);

} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode([
    'ok' => false,
    'error' => 'server_error',
    'detail' => $e->getMessage()
  ]);
