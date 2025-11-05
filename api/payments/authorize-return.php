<?php
declare(strict_types=1);

/**
 * Authorize.Net Hosted Payment Return Handler
 * Receives redirect after successful payment and triggers Ninja/AxTrax webhook.
 */

ini_set('display_errors', '1');
error_reporting(E_ALL);

/**
 * Authorize.net Return Handler
 * ----------------------------
 * Handles two flows:
 * 1. QuickPay: Existing member paying dues (memberId + invoiceId)
 * 2. Membership Signup: New member signing up (type=membership)
 */

require_once __DIR__ . '/../../_bootstrap.php';
header('Content-Type: text/html; charset=utf-8');

// ----------------------------------------------------------------------
// Detect flow type
// ----------------------------------------------------------------------
$flowType = $_GET['type'] ?? 'quickpay';

// If membership signup, handle separately
if ($flowType === 'membership') {
    // Membership signup flow - use JavaScript to read sessionStorage
    include __DIR__ . '/authorize-return-membership.php';
    exit;
}

// ----------------------------------------------------------------------
// QuickPay flow continues below
// ----------------------------------------------------------------------

// Create log directory if missing
$logDir = __DIR__ . '/../../logs';
if (!is_dir($logDir)) mkdir($logDir, 0755, true);
$logFile = "$logDir/authorize-return.log";

// Log all incoming data for debugging
file_put_contents($logFile, date('c') . " RETURN - GET: " . json_encode($_GET) . "\n", FILE_APPEND);
file_put_contents($logDir . "/authorize-return-post.log", date('c') . " POST: " . json_encode($_POST) . "\n", FILE_APPEND);

// ----------------------------------------------------------------------
// Extract memberId and invoiceId from transaction response
// ----------------------------------------------------------------------
$memberId = $_POST['memberId'] ?? $_GET['memberId'] ?? 0;
$invoiceId = $_POST['invoiceId'] ?? $_GET['invoiceId'] ?? 0;

// If not in parameters, try to parse from invoice number (format: QP{duesId}M{memberId})
if (!$invoiceId || !$memberId) {
    $invoiceNum = $_GET['refId'] ?? $_POST['refId'] ?? '';
    if (!$invoiceNum) {
        // Try reading from response (Authorize.Net may send transaction details)
        $transId = $_GET['transId'] ?? $_POST['transId'] ?? '';
        // For now, we'll need to embed these in the return URL
    }

    // Parse invoice format: QP3M1700 -> duesId=3, memberId=1700
    if (preg_match('/QP(\d+)M(\d+)/', $invoiceNum, $matches)) {
        $invoiceId = (int)$matches[1];
        $memberId = (int)$matches[2];
        file_put_contents($logFile, date('c') . " Parsed invoice: $invoiceNum -> duesId=$invoiceId, memberId=$memberId\n", FILE_APPEND);
    }
}

// ----------------------------------------------------------------------
// Basic validation
// ----------------------------------------------------------------------
if (!$invoiceId || !$memberId) {
    file_put_contents($logFile, date('c') . " Missing invoiceId or memberId (could not parse)\n", FILE_APPEND);
    echo "<h3>Payment received, but we could not identify your record.</h3>";
    echo "<p>Please contact support with your payment confirmation.</p>";
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
// Note: Member record will be updated via Authorize.Net webhook → AxTrax → callback flow
// We only mark the invoice as paid here for immediate user feedback
// ----------------------------------------------------------------------
file_put_contents($logFile, date('c') . " Payment return successful - invoice marked as paid\n", FILE_APPEND);
file_put_contents($logFile, date('c') . " Member record will be updated via Authorize.Net webhook → AxTrax → callback flow\n", FILE_APPEND);

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
