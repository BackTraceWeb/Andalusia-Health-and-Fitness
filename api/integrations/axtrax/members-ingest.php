<?php
require __DIR__ . '/../../_bootstrap.php';
header('Content-Type: application/json');

/* Auth: same shared key as dues-ingest (reads config/bridge.key if not in payments.php) */
if (!isset($config['bridge']['shared_key'])) {
  $kf = dirname(__DIR__, 3) . '/config/bridge.key';
  if (is_readable($kf)) {
    $config['bridge']['shared_key'] = trim(file_get_contents($kf));
  }
}
$hdr = $_SERVER['HTTP_X_AHF_BRIDGE_KEY'] ?? '';
if ($hdr !== ($config['bridge']['shared_key'] ?? '')) {
  http_response_code(401);
  echo '{"error":"unauthorized"}';
  exit;
}

/* Parse payload */
$raw = file_get_contents('php://input');
$body = json_decode($raw, true);
if (!is_array($body) || !isset($body['members']) || !is_array($body['members'])) {
  http_response_code(400);
  echo '{"error":"bad_payload"}';
  exit;
}

/* Upsert members */
$pdo->beginTransaction();
try {
  $up = $pdo->prepare("
    INSERT INTO members (id, first_name, last_name, email, zip, updated_at)
    VALUES (:id, :fn, :ln, :em, :zip, :upd)
    ON DUPLICATE KEY UPDATE
      first_name = VALUES(first_name),
      last_name  = VALUES(last_name),
      email      = VALUES(email),
      zip        = VALUES(zip),
      updated_at = VALUES(updated_at)
  ");

  $ok = 0; $skipped = 0;
  foreach ($body['members'] as $m) {
    $id  = isset($m['id']) ? (int)$m['id'] : 0;
    $fn  = trim($m['first_name'] ?? '');
    $ln  = trim($m['last_name']  ?? '');
    $em  = (string)($m['email'] ?? '');
    $zip = isset($m['zip']) ? (string)$m['zip'] : null;
    $upd = $m['updated_at'] ?? date('Y-m-d H:i:s');

    if ($id <= 0 || $fn === '' || $ln === '') { $skipped++; continue; }

    $up->execute([
      ':id'  => $id,
      ':fn'  => $fn,
      ':ln'  => $ln,
      ':em'  => $em,
      ':zip' => ($zip !== '') ? $zip : null,
      ':upd' => $upd,
    ]);
    $ok += $up->rowCount();
  }

  $pdo->commit();
  echo json_encode(['ok' => true, 'upserts' => $ok, 'skipped' => $skipped]);
} catch (Throwable $e) {
  $pdo->rollBack();
  http_response_code(500);
  echo json_encode(['error' => 'server_error']);
}
