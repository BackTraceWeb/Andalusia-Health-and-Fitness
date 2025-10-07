<?php
// /api/payments/epn-test.php
declare(strict_types=1);

$config = require __DIR__ . '/../../config/payments.php';
$epnHosted = 'https://www.eprocessingnetwork.com/cgi-bin/dbe/transact.pl';

// test amount & description
$amount = "1.00";
$desc   = "Test Payment AHF";

$fields = [
  'ePNAccount'        => $config['epn_account'],
  'RestrictKey'       => $config['restrict_key'],
  'Amount'            => $amount,
  'Description'       => $desc,
  'Name'              => 'Asia Pierce', // test member
  'Email'             => 'test@example.com',
  'ZIP'               => '36420',
  'HTML'              => 'Yes',
  'Redirect'          => '1',
  'ReturnApprovedURL' => 'https://andalusiahealthandfitness.com/quickpay/return-approved.html',
  'ReturnDeclinedURL' => 'https://andalusiahealthandfitness.com/quickpay/return-declined.html'
];

// ---- LOGGING ----
$logFile = __DIR__ . '/../../logs/epn-test.log';
if (!is_dir(dirname($logFile))) {
    mkdir(dirname($logFile), 0775, true);
}
$logMsg = "Outgoing EPN Test @ " . date('c') . "\n" .
          print_r($fields, true) . "\n";
file_put_contents($logFile, $logMsg, FILE_APPEND);

// ---- Render POST form ----
?>
<!DOCTYPE html>
<html>
<head><title>EPN Test</title></head>
<body>
  <h2>Redirecting to EPN…</h2>
  <form id="epnForm" method="post" action="<?php echo htmlspecialchars($epnHosted); ?>">
    <?php foreach ($fields as $k=>$v): ?>
      <input type="hidden" name="<?php echo htmlspecialchars($k) ?>" value="<?php echo htmlspecialchars($v) ?>">
    <?php endforeach; ?>
    <button type="submit">Continue to Pay</button>
  </form>
  <script>document.getElementById('epnForm').submit();</script>
</body>
</html>
