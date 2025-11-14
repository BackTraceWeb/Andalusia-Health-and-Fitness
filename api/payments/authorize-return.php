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
// Note: Authorize.Net doesn't send any data back to the return URL
// The webhook (authorize-success.php) handles all payment processing
// This page just shows a generic success message to the user
// ----------------------------------------------------------------------
file_put_contents($logFile, date('c') . " Payment return page loaded - webhook handles all processing\n", FILE_APPEND);

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
    #processingMessage {
      color: #ffaa00;
      margin-top: 10px;
    }
  </style>
</head>
<body>
  <div class="card">
    <h1>✅ Payment Successful!</h1>
    <p>Your payment has been received and is being processed.</p>
    <p id="processingMessage">⏳ Activating your access card...</p>
    <p style="margin-top: 20px; color: #aaa; font-size: 14px;">You will receive a confirmation email shortly with your receipt.</p>
    <a href="/quickpay/">Return to QuickPay Portal</a>
  </div>
  <script>
    // Process payment immediately using sessionStorage data
    (async function() {
      const memberId = sessionStorage.getItem('quickpay_memberId');
      const invoiceId = sessionStorage.getItem('quickpay_invoiceId');

      if (!memberId || !invoiceId) {
        console.log('No payment data in session');
        document.getElementById('processingMessage').textContent = 'Your access will be activated within a few moments.';
        return;
      }

      try {
        // Call backend to process payment (extend AxTrax + update DB)
        const response = await fetch('/api/payments/process-payment.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ memberId, invoiceId })
        });

        const result = await response.json();

        if (result.ok) {
          document.getElementById('processingMessage').innerHTML = '✓ Your access card has been activated!';
        } else {
          document.getElementById('processingMessage').innerHTML = '⚠ Your payment was received. Access will be activated shortly.';
        }
      } catch (err) {
        console.error('Processing error:', err);
        document.getElementById('processingMessage').innerHTML = 'Your payment was received. Access will be activated shortly.';
      } finally {
        // Clear session data
        sessionStorage.removeItem('quickpay_memberId');
        sessionStorage.removeItem('quickpay_invoiceId');
      }
    })();
  </script>
</body>
</html>
