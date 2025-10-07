<?php
declare(strict_types=1);
header('Content-Type: application/json');

require __DIR__ . '/../_bootstrap.php';
$config = require __DIR__ . '/../../config/payments.php';

function jerr(int $code, string $msg, array $extra = []): void {
  http_response_code($code);
  echo json_encode(array_merge(['ok'=>false,'error'=>$msg], $extra));
  exit;
}

$req = $_POST + $_GET;
foreach (['memberId','invoiceId','cardNo','expMonth','expYear','cvv2','address','zip'] as $f) {
  if (!isset($req[$f]) || $req[$f] === '') { jerr(400, 'missing_'.$f); }
}

$memberId  = (int)$req['memberId'];
$invoiceId = (int)$req['invoiceId'];

try {
  $pdo = pdo();
  $st = $pdo->prepare("SELECT d.amount_cents, d.currency, m.first_name, m.last_name, m.email
                       FROM dues d JOIN members m ON m.id=d.member_id
                       WHERE d.id=? AND d.member_id=? AND d.status='due' LIMIT 1");
  $st->execute([$invoiceId, $memberId]);
  $row = $st->fetch();
  if (!$row) { jerr(409, 'invoice_not_due'); }
} catch (Throwable $e) {
  jerr(500, 'db_error', ['detail'=>$e->getMessage()]);
}

$amount = number_format(((int)$row['amount_cents'])/100, 2, '.', '');
$pbid   = bin2hex(random_bytes(16));

try {
  $pdo->prepare("INSERT INTO quickpay_postbacks(pbid, member_id, invoice_id, ts, handled) VALUES(?,?,?,NOW(),0)")
      ->execute([$pbid, $memberId, $invoiceId]);
} catch (Throwable $e) { /* non-fatal */ }

$fields = [
  'ePNAccount' => $config['epn_account'],
  'RestrictKey'=> $config['restrict_key'] ?? '',
  'HTML'       => 'No',

  // identifiers help with audit/postback
  'YourID'     => (string)$memberId,
  'Invoice'    => (string)$invoiceId,
  'PostbackID' => $pbid,

  // card & amount
  'CardNo'     => preg_replace('/\s+/', '', (string)$req['cardNo']),
  'ExpMonth'   => sprintf('%02d', (int)$req['expMonth']),
  'ExpYear'    => sprintf('%02d', (int)$req['expYear']), // 2 digits
  'Total'      => $amount,

  // AVS/CVV (send both Street/Address to be safe)
  'Street'     => trim((string)$req['address']),
  'Address'    => trim((string)$req['address']),
  'Zip'        => preg_replace('/\D/', '', (string)$req['zip']),
  'CVV2Type'   => '1',
  'CVV2'       => (string)$req['cvv2'],
];

// DEBUG: log outgoing (mask key)
$dbg = $fields; if (isset($dbg['RestrictKey'])) $dbg['RestrictKey'] = '****';
@file_put_contents('/var/log/ahf/charge_debug.log',
  json_encode(['ts'=>gmdate('c'),'phase'=>'out','fields'=>$dbg], JSON_UNESCAPED_SLASHES).PHP_EOL,
  FILE_APPEND | LOCK_EX
);

$ch = curl_init($config['endpoint']);
curl_setopt_array($ch, [
  CURLOPT_POST            => true,
  CURLOPT_POSTFIELDS      => http_build_query($fields),
  CURLOPT_RETURNTRANSFER  => true,
  CURLOPT_TIMEOUT         => 45,
  CURLOPT_SSL_VERIFYPEER  => true,
  CURLOPT_SSL_VERIFYHOST  => 2,
]);
$resp = curl_exec($ch);
$err  = curl_error($ch);
$http = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
curl_close($ch);

// DEBUG: log incoming
@file_put_contents('/var/log/ahf/charge_debug.log',
  json_encode(['ts'=>gmdate('c'),'phase'=>'in','http'=>$http,'err'=>$err,'raw'=>$resp], JSON_UNESCAPED_SLASHES).PHP_EOL,
  FILE_APPEND | LOCK_EX
);

if ($resp === false) { jerr(502, 'gateway_error', ['detail'=>$err]); }

$body = trim((string)$resp);
$body = preg_replace('/^<html><body>/i', '', $body);
$body = preg_replace('@</body></html>$@i', '', $body);

// Expected CSV: "YAUTH/TKT 123456","AVS text","CVV2 text"
if (preg_match('/^"([YNU][^"]*)","([^"]*)","([^"]*)"$/', $body, $m)) {
  $authLine = $m[1];
  $avs      = $m[2];
  $cvv      = $m[3];
  $approved = str_starts_with($authLine, 'Y');

  $ticket = null;
  if (preg_match('/YAUTH\/TKT\s+([0-9]+)/', $authLine, $mm)) { $ticket = $mm[1]; }

  if ($approved) {
    try {
      $pdo->prepare("UPDATE dues SET status='paid', epn_ticket=?, paid_at=NOW()
                     WHERE id=? AND status IN('due','failed')")
          ->execute([$ticket, $invoiceId]);
    } catch (Throwable $e) { /* log later if needed */ }

    echo json_encode(['ok'=>true,'authLine'=>$authLine,'ticket'=>$ticket,'avs'=>$avs,'cvv2'=>$cvv]);
    exit;
  } else {
    echo json_encode(['ok'=>false,'authLine'=>$authLine,'avs'=>$avs,'cvv2'=>$cvv]);
    exit;
  }
}

// Fallback: ePN echoed postback output or sent HTML error
jerr(502, 'unexpected_response', ['raw'=>$body]);
