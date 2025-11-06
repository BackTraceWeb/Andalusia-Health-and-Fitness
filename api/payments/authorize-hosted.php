<?php
declare(strict_types=1);

// --- bootstrap guard ---------------------------------------------------------
$bootTried = [];
foreach ([
  __DIR__ . '/../../_bootstrap.php',
  dirname(__DIR__, 2) . '/_bootstrap.php',
  '/var/www/andalusiahealthandfitness/_bootstrap.php',
] as $cand) {
  if (is_file($cand)) { require_once $cand; $bootTried[] = $cand; break; }
}
if (!function_exists('cfg')) {
  http_response_code(500);
  header('Content-Type: text/plain');
  echo "Bootstrap not loaded.\nTried:\n - " . implode("\n - ", $bootTried ?: ['(no candidates)']);
  exit;
}
// ----------------------------------------------------------------------------- 

function h(?string $s): string { return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }

$memberId  = isset($_GET['memberId'])  ? (int)$_GET['memberId']  : 0;
$invoiceId = isset($_GET['invoiceId']) ? (int)$_GET['invoiceId'] : 0;

if ($memberId <= 0 || $invoiceId <= 0) {
  http_response_code(400);
  echo "<h2>Bad Request</h2><p>Missing or invalid memberId / invoiceId.</p>";
  exit;
}

// 1) Look up amount and meta strictly from DB (never trust client)
try {
  $pdo = pdo();

  // Example: dues table holds the amount in cents and links to member
  $row = $pdo->prepare("SELECT d.id AS invoice_id, d.amount_cents, m.id AS member_id, m.email, m.first_name, m.last_name
                        FROM dues d
                        JOIN members m ON m.id = d.member_id
                        WHERE d.id = :iid AND m.id = :mid
                        LIMIT 1");
  $row->execute([':iid'=>$invoiceId, ':mid'=>$memberId]);
  $inv = $row->fetch();

  if (!$inv) {
    http_response_code(404);
    echo "<h2>Not Found</h2><p>Invoice/member combination not found.</p>";
    exit;
  }

  $amount = number_format(((int)$inv['amount_cents'])/100, 2, '.', '');
  $email  = $inv['email'] ?: 'noemail@example.com';
  $custName = trim(($inv['first_name'] ?? '').' '.($inv['last_name'] ?? ''));
  if ($custName === '') $custName = "Member #{$memberId}";

} catch (Throwable $e) {
  http_response_code(500);
  echo "<h2>DB Error</h2><pre>".h($e->getMessage())."</pre>";
  exit;
}

// 2) Build getHostedPaymentPageRequest
$cfg = cfg();
$payload = [
  'getHostedPaymentPageRequest' => [
    'merchantAuthentication' => [
      'name'           => $cfg['AUTHNET_API_LOGIN_ID'],
      'transactionKey' => $cfg['AUTHNET_TRANSACTION_KEY'],
    ],
    'transactionRequest' => [
      'transactionType' => 'authCaptureTransaction',
      'amount'          => $amount,
      'order' => [
        'invoiceNumber' => (string)$invoiceId,
        'description'   => "Quick Pay Invoice #{$invoiceId}",
      ],
      'customer' => [
        'email' => $email,
      ],
      'billTo' => [
        'firstName' => $inv['first_name'] ?? '',
        'lastName'  => $inv['last_name']  ?? '',
      ],
    ],
    'hostedPaymentSettings' => [
      // Let AuthNet show receipt and then bounce back to our thank-you (optional today)
      'setting' => [
        [
          'settingName'  => 'hostedPaymentReturnOptions',
          'settingValue' => json_encode([
            'showReceipt' => true, // simplest: AuthNet handles receipt
            'url'         => rtrim($cfg['BASE_URL'],'/')."/quickpay/thank-you.html",
            'urlText'     => 'Return to site',
            'cancelUrl'   => rtrim($cfg['BASE_URL'],'/')."/quickpay/cancelled.html",
            'cancelUrlText'=> 'Cancel and return',
          ]),
        ],
        // Basic styling/branding toggles you can tweak later:
        [
          'settingName'  => 'hostedPaymentPaymentOptions',
          'settingValue' => json_encode(['cardCodeRequired'=>true]),
        ],
        [
          'settingName'  => 'hostedPaymentOrderOptions',
          'settingValue' => json_encode(['show'=>true]),
        ],
      ],
    ],
  ],
];

try {
  $ch = curl_init(envUrl());
  curl_setopt_array($ch, [
    CURLOPT_POST            => true,
    CURLOPT_HTTPHEADER      => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS      => json_encode($payload, JSON_UNESCAPED_SLASHES),
    CURLOPT_RETURNTRANSFER  => true,
    CURLOPT_TIMEOUT         => 20,
  ]);
  $res = curl_exec($ch);
  $err = curl_error($ch);
  curl_close($ch);

  if ($err) {
    echo "<h2>Authorize.Net Error</h2><pre>".h($err)."</pre>";
    exit;
  }

  $j = json_decode((string)$res, true);
  if (!isset($j['token'])) {
    echo "<h2>Authorize.Net Error</h2><pre>".h($res)."</pre>";
    exit;
  }

  $token = $j['token'];

} catch (Throwable $e) {
  echo "<h2>Authorize.Net Error</h2><pre>".h($e->getMessage())."</pre>";
  exit;
}

// 3) Render a super-simple Accept Hosted iframe launcher
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Quick Pay</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
  body { font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif; margin: 0; background:#111; color:#fff; }
  .wrap { max-width: 900px; margin: 0 auto; padding: 24px; }
  .card { background:#1c1c1c; border-radius:16px; padding:24px; box-shadow: 0 10px 30px rgba(0,0,0,.4); }
  h1 { margin:0 0 8px; font-size: 1.4rem; }
  .muted { color:#aaa; margin: 0 0 16px; }
  iframe { width:100%; height: 720px; border:0; border-radius: 12px; background:#fff; }
  .pill { display:inline-block; padding:6px 10px; border-radius:999px; background:#282828; color:#bbb; font-size:.85rem; }
</style>
</head>
<body>
<div class="wrap">
  <div class="card">
    <h1>Pay Invoice #<?=h((string)$invoiceId)?></h1>
    <p class="muted">
      <?=h($custName)?> • <span class="pill">Amount: $<?=h($amount)?></span>
    </p>
    <iframe src="https://accept.authorize.net/payment/payment?token=<?=h($token)?>" allowpaymentrequest></iframe>
  </div>
</div>
</body>
</html>
