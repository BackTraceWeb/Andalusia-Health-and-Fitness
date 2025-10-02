<?php
declare(strict_types=1);

/**
 * Robust ePN postback handler
 * - Supports Extended TDBE (normal key/value POST)
 * - Supports "Applications" semicolon payload: a single POST key whose value is "Key=Val;Key=Val;..."
 * - Updates DB when we can determine approval/decline
 * - Emits CSV ONLY when we have a real, interpretable result; otherwise returns empty 200 to let caller poll.
 */

header('Content-Type: text/plain');
http_response_code(200);

// 1) Normalize into $norm (lowercased keys)
$norm = [];
foreach ($_POST as $k => $v) {
  $norm[strtolower($k)] = is_string($v) ? trim($v) : $v;
}

// 2) If it looks like the "Applications" semicolon blob, parse it
if (count($norm) === 1) {
  $onlyVal = reset($norm);
  if (is_string($onlyVal) && str_contains($onlyVal, ';')) {
    // Split "Key=Val;Key=Val" into pairs
    $pairs = explode(';', $onlyVal);
    foreach ($pairs as $pair) {
      $pair = trim($pair);
      if ($pair === '' || !str_contains($pair, '=')) continue;
      [$k, $v] = array_map('trim', explode('=', $pair, 2));
      if ($k !== '') $norm[strtolower($k)] = $v;
    }
  }
}

// 3) Extract useful fields (support multiple spellings)
$pbid    = $norm['postbackid'] ?? $norm['pbid'] ?? null;
$resp    = $norm['response'] ?? $norm['fullresponse'] ?? null;  // e.g. "YAUTH/TKT 1234" or "N Declined" or "U ..."
$isStr   = $norm['isapproved'] ?? null;                          // "Y"/"N"/"U" when Extended postback
$ticket  = $norm['ticket'] ?? $norm['tkt'] ?? null;
$avs     = $norm['avsmsg'] ?? $norm['avs'] ?? '';
$cvv2msg = $norm['cvv2msg'] ?? $norm['cvv2'] ?? '';
$authmsg = $norm['authresp'] ?? $norm['message'] ?? $norm['errno'] ?? '';

// 4) Decide status Y/N/U from the best available hint
$status = null;
if (is_string($isStr) && $isStr !== '') {
  $status = strtoupper($isStr[0]); // Y/N/U
} elseif (is_string($resp) && $resp !== '') {
  $c = strtoupper($resp[0]);       // first letter of Response/FullResponse
  if (in_array($c, ['Y','N','U'], true)) $status = $c;
}

// If we STILL can't tell, it's not a real postback we can act on → return empty so caller will poll
if ($status === null) {
  exit; // 200 OK, empty body
}

// 5) If we have a PBID, try to update DB
try {
  require __DIR__ . '/../_bootstrap.php';
  $pdo = pdo();

  // optional raw audit
  $pdo->exec("CREATE TABLE IF NOT EXISTS epn_raw_postbacks (
    pbid CHAR(32) PRIMARY KEY,
    payload JSON,
    ts DATETIME NOT NULL
  )");

  if ($pbid) {
    $stmt = $pdo->prepare("INSERT INTO epn_raw_postbacks (pbid,payload,ts)
                           VALUES (?,?,NOW())
                           ON DUPLICATE KEY UPDATE payload=VALUES(payload), ts=VALUES(ts)");
    $stmt->execute([$pbid, json_encode($norm, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)]);
  }

  if ($pbid) {
    $st = $pdo->prepare("SELECT invoice_id FROM quickpay_postbacks WHERE pbid=? LIMIT 1");
    $st->execute([$pbid]);
    if ($row = $st->fetch()) {
      if ($status === 'Y') {
        // Try to infer ticket from Response if not provided
        if (!$ticket && is_string($resp) && preg_match('/YAUTH\/TKT\s+(\d+)/i', $resp, $mm)) {
          $ticket = $mm[1];
        }
        $pdo->prepare("UPDATE dues SET status='paid', epn_ticket=?, paid_at=NOW()
                       WHERE id=? AND status IN('due','failed')")
            ->execute([$ticket, $row['invoice_id']]);
      } elseif ($status === 'N') {
        $pdo->prepare("UPDATE dues SET status='failed'
                       WHERE id=? AND status='due'")
            ->execute([$row['invoice_id']]);
      }
      $pdo->prepare("UPDATE quickpay_postbacks SET handled=1 WHERE pbid=?")->execute([$pbid]);
    }
  }
} catch (Throwable $e) {
  // swallow errors; still send CSV below
}

// 6) Emit CSV ONLY for real, interpretable postbacks
$authLine = '';
if ($status === 'Y') {
  $authLine = $ticket ? "YAUTH/TKT $ticket" : (is_string($resp) && stripos($resp,'Y')===0 ? $resp : 'YAPPROVED');
} elseif ($status === 'N') {
  $authLine = is_string($resp) && stripos($resp,'N')===0 ? $resp : 'N DECLINED';
} else { // U
  $authLine = is_string($resp) && stripos($resp,'U')===0 ? $resp : 'U ERROR';
}

// CSV sanitize
$avs      = str_replace('"', "'", (string)$avs);
$cvv2msg  = str_replace('"', "'", (string)$cvv2msg);
$authLine = str_replace('"', "'", (string)$authLine);

echo "\"$authLine\",\"$avs\",\"$cvv2msg\"";
