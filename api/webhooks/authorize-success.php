<?php
// api/webhooks/authorize-success.php
declare(strict_types=1);
date_default_timezone_set('UTC');

// ---- CONFIG ----
require_once __DIR__ . '/../config.php';  // Load AUTH_SIGNATURE_KEY_HEX constant

$csvPath = __DIR__ . '/payments.csv';   // lives beside this PHP file
$prod = true;                            // Enable production mode with HMAC verification
$apiSignatureKeyHex = AUTH_SIGNATURE_KEY_HEX;  // Use production signature key from config
// --------------

// Input + HMAC verify
$raw = file_get_contents('php://input');
$headers = array_change_key_case(function_exists('getallheaders') ? getallheaders() : [], CASE_UPPER);
$sig = $headers['X-ANET-SIGNATURE'] ?? '';

if ($prod) {
  $calc = 'sha512=' . hash_hmac('sha512', $raw, pack('H*', $apiSignatureKeyHex));
  if (!hash_equals(strtolower($calc), strtolower($sig))) {
    http_response_code(401);
    error_log("ANET HMAC mismatch. Provided=$sig Calc=$calc");
    exit("invalid signature");
  }
}

// Parse payload
$body = json_decode($raw, true);
if (!is_array($body) || empty($body['payload'])) { http_response_code(400); exit('bad payload'); }

$evt  = (string)($body['eventType'] ?? '');
$pl   = $body['payload'];
$resp = (int)($pl['responseCode'] ?? 0);
$iid  = (string)($pl['invoiceNumber'] ?? '');
$pid  = (string)($pl['id'] ?? '');
$amt  = (float)($pl['authAmount'] ?? 0);

// Only successful authcapture
if ($evt !== 'net.authorize.payment.authcapture.created' || $resp !== 1) {
  http_response_code(200); exit('ignored');
}

// Extract duesId and memberId from invoice like "QP3M1700"
// Pattern: QP{duesId}M{memberId}
$duesId = '';
$memberId = '';
if (preg_match('/^QP(\d+)M(\d+)$/', $iid, $m)) {
  $duesId = $m[1];      // dues.id (invoice ID)
  $memberId = $m[2];     // members.id
  error_log("Parsed invoice $iid -> duesId=$duesId, memberId=$memberId");
} else {
  error_log("Invoice parse failed: $iid");
  http_response_code(200); exit('ignored');
}

// Ensure directory + header exist
$dir = dirname($csvPath);
if (!is_dir($dir)) { @mkdir($dir, 0775, true); }

if (!file_exists($csvPath)) {
  $header = "When,PaymentId,InvoiceNumber,MemberId,Amount\r\n";
  if (file_put_contents($csvPath, $header, FILE_APPEND | LOCK_EX) === false) {
    http_response_code(500); exit("header write failed");
  }
}

// Append row (sanitize commas in invoice to keep CSV tidy)
$invoiceSafe = str_replace(',', ' ', $iid);
$line = sprintf("%s,%s,%s,%s,%.2f\r\n", gmdate('c'), $pid, $invoiceSafe, $memberId, $amt);

$fh = fopen($csvPath, 'ab');
if ($fh === false) { http_response_code(500); exit("open failed"); }
flock($fh, LOCK_EX);
fwrite($fh, $line);
flock($fh, LOCK_UN);
fclose($fh);

// ----------------------------------------------------------------------
// CALL AXTRAX API TO EXTEND MEMBERSHIP & UPDATE DATABASE
// ----------------------------------------------------------------------
try {
  require_once __DIR__ . '/../../_bootstrap.php';
  require_once __DIR__ . '/../axtrax-config.php';
  require_once __DIR__ . '/../axtrax-helpers.php';

  $pdo = pdo();

  // Check if invoice is already paid (payment intent may have already processed it)
  if (!empty($duesId)) {
    $stmt = $pdo->prepare("SELECT status FROM dues WHERE id = ?");
    $stmt->execute([$duesId]);
    $invoice = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($invoice && $invoice['status'] === 'paid') {
      error_log("Webhook: Invoice #$duesId already paid (likely processed by payment intent), skipping duplicate processing");
      http_response_code(202);
      echo "ok - already processed";
      exit;
    }
  }

  // Get member details from database
  $stmt = $pdo->prepare("SELECT email, first_name, last_name FROM members WHERE id = ?");
  $stmt->execute([$memberId]);
  $member = $stmt->fetch(PDO::FETCH_ASSOC);

  if ($member) {
    // Calculate new valid_until date (30 days from today)
    $newValidUntil = date('Y-m-d', strtotime('+30 days'));

    error_log("Payment webhook: Extending membership for member #$memberId ({$member['first_name']} {$member['last_name']}) until $newValidUntil");

    // Load AxTrax config
    $axtraxConfig = require __DIR__ . '/../axtrax-config.php';

    // Extend membership in AxTrax (30 days)
    $axtraxSuccess = axtraxExtendMembership(
      $member['email'],
      $member['first_name'],
      $member['last_name'],
      30, // days
      $axtraxConfig
    );

    if ($axtraxSuccess) {
      error_log("AxTrax API: Successfully extended membership for member #$memberId");
    } else {
      error_log("AxTrax API: Failed to extend membership for member #$memberId - updating database directly as fallback");
    }

    // Update our database (always do this regardless of AxTrax success)
    $stmt = $pdo->prepare("
      UPDATE members
      SET valid_until = :valid_until,
          status = 'current',
          updated_at = NOW()
      WHERE id = :member_id
    ");
    $stmt->execute([
      ':valid_until' => $newValidUntil,
      ':member_id' => $memberId
    ]);
    error_log("Database: Updated member #$memberId valid_until to $newValidUntil");

    // Mark invoice as paid
    if (!empty($duesId)) {
      $stmt2 = $pdo->prepare("
        UPDATE dues
        SET status = 'paid',
            paid_at = NOW()
        WHERE id = :dues_id AND status IN ('due', 'failed')
      ");
      $stmt2->execute([':dues_id' => $duesId]);
      error_log("Database: Marked dues #$duesId as paid");
    }
  } else {
    error_log("ERROR: Member #$memberId not found in database");
  }

} catch (Throwable $e) {
  error_log("Webhook processing error: " . $e->getMessage());
  // Log error but don't fail the webhook - payment was successful
}

http_response_code(202);
echo "ok";

