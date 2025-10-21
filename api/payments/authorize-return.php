<?php
declare(strict_types=1);

/**
 * Authorize.net Return Handler (QuickPay flow)
 * --------------------------------------------
 * Called automatically by Authorize.net after a successful hosted payment.
 * - Marks dues record as paid
 * - Calls internal webhook (authorize-success.php) to trigger NinjaOne → AxTrax update
 * - Displays confirmation message to member
 */

require __DIR__ . '/../_bootstrap.php';
header('Content-Type: text/html; charset=utf-8');

// ----------------------------------------------------------------------
// Capture return parameters
// ----------------------------------------------------------------------
$invoiceId = $_GET['invoice_id'] ?? $_POST['invoice_id'] ?? null;
$memberId  = $_GET['member_id'] ?? $_POST['member_id'] ?? null;

// Create log directory if missing
$logDir = __DIR__ . '/../../logs';
if (!is_dir($logDir)) mkdir($logDir, 0755, true);
$logFile = "$logDir/authorize-return.log";

// ----------------------------------------------------------------------
// Basic validation
// ----------------------------------------------------------------------
if (!$invoiceId || !$memberId) {
    file_put_contents($logFile, date('c') . " Missing invoiceId or memberId\n", FILE_APPEND);
    echo "<h3>Payment received, but we could not identify your record.</h3>";
    exit;
}

// ----------------------------------------------------------------------
// Update database
// ----------------------------------------------------------------------
try {
    $pdo = pdo();
    $stmt = $pdo->prepare("
        UPDATE dues
           SET status='paid',
               paid_at=NOW()
         WHERE id=? AND status IN('due','failed')
    ");
    $stmt->execute([$invoiceId]);
    $updated = $stmt->rowCount();

    file_put_contents($logFile, date('c') . " Updated invoice #$invoiceId (member #$memberId) rows:$updated\n", FILE_APPEND);
} catch (Throwable $e) {
    file_put_contents($logFile, date('c') . " DB Error: " . $e->getMessage() . "\n", FILE_APPEND);
}

// ----------------------------------------------------------------------
// Trigger internal webhook (fires NinjaOne → AxTrax)
// ----------------------------------------------------------------------
try {
    $webhookUrl = 'https://andalusiahealthandfitness.com/api/webhooks/authorize-success.php';

    $payload = [
        "eventType" => "net.authorize.payment.authcapture.created",
        "payload" => [
            "invoiceNumber" => $invoiceId,
            "memberId"      => $memberId,
            "authAmount"    => 0
        ]
    ];

    $ch = curl_init($webhookUrl);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => 5
    ]);
    $resp = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    file_put_contents($logFile, date('c') . " Webhook HTTP:$http\nResponse:$resp\n\n", FILE_APPEND);
} catch (Throwable $e) {
    file_put_contents($logFile, date('c') . " Webhook Error: " . $e->getMessage() . "\n", FILE_APPEND);
}

// ----------------------------------------------------------------------
// Display confirmation page
// ----------------------------------------------------------------------
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Payment Complete - Andalusia Health & Fitness</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
    body {
      background: #000;
      color: #fff;
      font-family: Arial, sans-serif;
      text-align: center;
      padding-top: 100px;
    }
    h1 {
      color: #ff0066;
    }
    a {
      display: inline-block;
      margin-top: 20px;
      color: #ff0066;
      text-decoration: none;
      font-weight: bold;
    }
    .card {
      background: #111;
      border-radius: 20px;
      padding: 40px;
      display: inline-block;
      box-shadow: 0 0 30px rgba(255, 0, 80, 0.4);
    }
  </style>
</head>
<body>
  <div class="card">
    <h1>✅ Payment Successful!</h1>
    <p>Your payment has been received and recorded for invoice <strong>#<?= htmlspecialchars($invoiceId) ?></strong>.</p>
    <p>Your access card will automatically be reactivated within a few moments.</p>
    <a href="/quickpay/">Return to QuickPay Portal</a>
  </div>
</body>
</html>
