<?php
ini_set('display_errors', 1);
ini_set('log_errors', 1);
error_reporting(E_ALL);
error_log("members-ingest.php triggered at " . date('c'));

require __DIR__ . '/../../_bootstrap.php';
header('Content-Type: application/json');

/* --- Auth (shared bridge key) --- */
if (!isset($config['bridge']['shared_key'])) {
  $kf = dirname(__DIR__, 3) . '/config/bridge.key';
  if (is_readable($kf)) {
    $config['bridge']['shared_key'] = trim(file_get_contents($kf));
  }
}
$hdr = $_SERVER['HTTP_X_AHF_BRIDGE_KEY'] ?? '';
if ($hdr !== ($config['bridge']['shared_key'] ?? '')) {
  http_response_code(401);
  echo json_encode(['error' => 'unauthorized']);
  exit;
}

/* --- Parse Payload --- */
$raw  = file_get_contents('php://input');
$body = json_decode($raw, true);

if (!is_array($body) || !isset($body['members']) || !is_array($body['members'])) {
  http_response_code(400);
  echo json_encode(['error' => 'bad_payload']);
  exit;
}

/* --- Helper to calculate dues status --- */
$today = new DateTimeImmutable('today');
function getStatus(?string $validUntil, DateTimeImmutable $today): string {
  if (!$validUntil) return 'due';
  try {
    $until = new DateTimeImmutable($validUntil);
    return ($until >= $today) ? 'current' : 'due';
  } catch (Throwable $e) {
    return 'due';
  }
}

/* --- Insert or update members with dues status --- */
$pdo->beginTransaction();
try {
  $up = $pdo->prepare("
    INSERT INTO members (
      id, first_name, last_name,
      department_id, department_name, card_number,
      valid_from, valid_until, status, updated_at
    ) VALUES (
      :id, :fn, :ln, :dept_id, :dept_name,
      :card, :vfrom, :vuntil, :status, :upd
    )
    ON DUPLICATE KEY UPDATE
      first_name = VALUES(first_name),
      last_name = VALUES(last_name),
      department_id = VALUES(department_id),
      department_name = VALUES(department_name),
      card_number = VALUES(card_number),
      valid_from = VALUES(valid_from),
      valid_until = VALUES(valid_until),
      status = VALUES(status),
      updated_at = VALUES(updated_at)
  ");

  $ok = 0; $skipped = 0;
  foreach ($body['members'] as $m) {
    $id   = isset($m['user_id']) ? (int)$m['user_id'] : 0;
    $fn   = trim($m['first_name'] ?? '');
    $ln   = trim($m['last_name'] ?? '');
    $dept_id   = isset($m['department_id']) ? (int)$m['department_id'] : null;
    $dept_name = trim($m['department_name'] ?? '');
    $card      = trim($m['card_number'] ?? '');
    $vfrom     = $m['valid_from']  ?? null;
    $vuntil    = $m['valid_until'] ?? null;
    $upd       = $m['updated_at']  ?? date('Y-m-d H:i:s');
    $status    = getStatus($vuntil, $today);

    // Skip invalid records (no ID or missing both first+last)
    if ($id <= 0 || ($fn === '' && $ln === '')) {
      $skipped++;
      continue;
    }

    $up->execute([
      ':id'        => $id,
      ':fn'        => $fn,
      ':ln'        => $ln,
      ':dept_id'   => $dept_id,
      ':dept_name' => $dept_name,
      ':card'      => $card ?: null,
      ':vfrom'     => $vfrom ?: null,
      ':vuntil'    => $vuntil ?: null,
      ':status'    => $status,
      ':upd'       => $upd,
    ]);
    $ok++;
  }

  $pdo->commit();
  echo json_encode(['ok' => true, 'upserts' => $ok, 'skipped' => $skipped]);
} catch (Throwable $e) {
  $pdo->rollBack();
  http_response_code(500);
  echo json_encode(['error' => 'server_error', 'detail' => $e->getMessage()]);
}
