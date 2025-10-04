<?php
require __DIR__ . '/../../_bootstrap.php';
header('Content-Type: application/json');

/* Load shared key (same as members-ingest) */
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

/* Parse & validate JSON */
$raw  = file_get_contents('php://input');
$body = json_decode($raw, true);
if (!is_array($body) || !isset($body['dues']) || !is_array($body['dues'])) {
  http_response_code(400);
  echo '{"error":"bad_payload"}';
  exit;
}

$periodStart = $body['period_start'] ?? null;
$periodEnd   = $body['period_end']   ?? null;
$fullRefresh = !empty($body['full_refresh']);
if ($fullRefresh && (!$periodStart || !$periodEnd)) {
  http_response_code(400);
  echo '{"error":"need_period_for_full_refresh"}';
  exit;
}

/* Ensure unique key on (member_id, period_start, period_end) if missing */
try {
  $chk = $pdo->prepare("
    SELECT COUNT(*) FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name   = 'dues'
      AND index_name   = 'uq_member_period'
  ");
  $chk->execute();
  if ((int)$chk->fetchColumn() === 0) {
    $pdo->exec("ALTER TABLE dues ADD UNIQUE KEY uq_member_period (member_id, period_start, period_end)");
  }
} catch (Throwable $e) { /* continue */ }

$pdo->beginTransaction();
try {
  /* If full_refresh: void any existing 'due' rows for the period NOT present in this feed */
  if ($fullRefresh) {
    $incomingIds = array_unique(array_map(fn($d)=> (int)($d['member_id'] ?? 0), $body['dues']));
    if (count($incomingIds) > 0) {
      $placeholders = implode(',', array_fill(0, count($incomingIds), '?'));
      $stmtVoid = $pdo->prepare("
        UPDATE dues SET status='void'
        WHERE period_start = ? AND period_end = ? AND status='due'
          AND member_id NOT IN ($placeholders)
      ");
      $stmtVoid->execute(array_merge([$periodStart, $periodEnd], $incomingIds));
    } else {
      $stmtVoidAll = $pdo->prepare("
        UPDATE dues SET status='void'
        WHERE period_start = ? AND period_end = ? AND status='due'
      ");
      $stmtVoidAll->execute([$periodStart, $periodEnd]);
    }
  }

  /* Upsert each due row */
  $up = $pdo->prepare("
    INSERT INTO dues (member_id, period_start, period_end, amount_cents, currency, status)
    VALUES (:mid, :ps, :pe, :amt, :cur, :st)
    ON DUPLICATE KEY UPDATE
      amount_cents = VALUES(amount_cents),
      currency     = VALUES(currency),
      status       = VALUES(status)
  ");

  $ok = 0; $skipped = 0;
  foreach ($body['dues'] as $d) {
    $mid = (int)($d['member_id'] ?? 0);
    $ps  = $d['period_start'] ?? $periodStart;
    $pe  = $d['period_end']   ?? $periodEnd;
    $cur = $d['currency']     ?? 'USD';
    $st  = $d['status']       ?? 'due';

    if ($mid <= 0 || !$ps || !$pe) { 
      $skipped++; 
      continue; 
    }

    // 🔹 Always fetch the member's monthly_fee
    $stmtFee = $pdo->prepare("SELECT monthly_fee FROM members WHERE id = :mid LIMIT 1");
    $stmtFee->execute([':mid' => $mid]);
    $feeRow = $stmtFee->fetch();
    $amt = $feeRow ? intval($feeRow['monthly_fee'] * 100) : 0; // fallback to 0 if not found

    $up->execute([
      ':mid'=>$mid, ':ps'=>$ps, ':pe'=>$pe,
      ':amt'=>$amt, ':cur'=>$cur, ':st'=>$st
    ]);
    $ok += $up->rowCount();
  }

  $pdo->commit();
  echo json_encode(['ok'=>true,'upserts'=>$ok,'skipped'=>$skipped,'full_refresh'=>$fullRefresh]);
} catch (Throwable $e) {
  $pdo->rollBack();
  http_response_code(500);
  echo json_encode(['error'=>'server_error']);
}





