<?php
// /api/payments/hosted-start.php
declare(strict_types=1);

require __DIR__ . '/../_bootstrap.php';
$c = require __DIR__ . '/../../config/payments.php';

if (empty($c['epn_account']) || empty($c['restrict_key'])) {
  http_response_code(500); echo "Gateway not configured"; exit;
}

$memberId  = (int)($_POST['memberId'] ?? 0);
$invoiceId = (int)($_POST['invoiceId'] ?? 0);
if ($memberId <= 0 || $invoiceId <= 0) { http_response_code(400); echo "Bad request"; exit; }

$pdo = pdo();

// Get amount and member info (must be due)
$st = $pdo->prepare("
  SELECT d.amount_cents, d.currency, m.first_name, m.last_name, m.email
  FROM dues d JOIN members m ON m.id=d.member_id
  WHERE d.id=? AND d.member_id=? AND d.status='due' LIMIT 1
");
$st->execute([$invoiceId, $memberId]);
$row = $st->fetch();
if (!$row) { http_response_code(409); echo "Invoice not due"; exit; }

$amount = number_format($row['amount_cents']/100, 2, '.', '');
$pbid   = bin2hex(random_bytes(16));
$pdo->prepare("INSERT INTO quickpay_postbacks(pbid, member_id, invoice_id, ts, handled)
               VALUES(?,?,?,NOW(),0)")
    ->execute([$pbid, $memberId, $invoiceId]);

$base = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'];
$approvedUrl = $base . "/quickpay/return-approved.html?invoiceId={$invoiceId}";
$declinedUrl = $base . "/quickpay/return-declined.html?invoiceId={$invoiceId}";

// DBE hosted form endpoint (secure page where ePN collects card data)
$epnHosted = 'https://www.eprocessingnetwork.com/cgi-bin/dbe/transact.pl';

$fields = [
  'ePNAccount'        => $c['epn_account'],
  'RestrictKey'       => $c['restrict_key'],
  'HTML'              => 'Yes',
  'Redirect'          => '1',
  'ReturnApprovedURL' => $approvedUrl,
  'ReturnDeclinedURL' => $declinedUrl,

  // Identify the payer for postback & your records
  'YourID'            => (string)$memberId,
  'Invoice'           => (string)$invoiceId,
  'PostbackID'        => $pbid,

  // Lock in the amount
  'Total'             => $amount,
  'Description'       => 'QuickPay Membership Dues',

  // Optional prefill
  'Email'             => $row['email'] ?? '',
  'Name'              => trim(($row['first_name'] ?? '').' '.($row['last_name'] ?? '')),
];

?><!doctype html>
<html><head><meta charset="utf-8"><title>Redirecting…</title>
<style>
  body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,sans-serif;background:#0b0e14;color:#e8eef6;
       display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0}
  .box{background:#131722;padding:24px;border-radius:14px;box-shadow:0 10px 30px rgba(0,0,0,.3);max-width:520px}
  h1{font-size:1.1rem;margin:.2rem 0}.muted{color:#9fb0c2}
</style>
</head>
<body>
  <div class="box">
    <h1>Sending you to the secure payment page…</h1>
    <p class="muted">Please don’t close this tab.</p>
    <form id="f" action="<?php echo htmlspecialchars($epnHosted) ?>" method="post">
      <?php foreach($fields as $k=>$v): ?>
        <input type="hidden" name="<?php echo htmlspecialchars($k) ?>"
               value="<?php echo htmlspecialchars((string)$v) ?>">
      <?php endforeach; ?>
      <noscript><button type="submit">Continue</button></noscript>
    </form>
  </div>
<script>document.getElementById('f').submit();</script>
</body></html>
