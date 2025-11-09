<?php
// api/webhooks/authorize-success.php
declare(strict_types=1);
date_default_timezone_set('UTC');

// ---- CONFIG ----
$csvPath = __DIR__ . '/payments.csv';   // lives beside this PHP file
$prod = false;                                    // true in production
$apiSignatureKeyHex =
 '21C260460E71D8FFC7437BA38D1938A0D8C531810250F8FC36CB06197C93776609F7A6AB937FD53DE0C0E43409B4BF6AC89F7AC1FBA77BDFA4DE9ECC535F3C7C';
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

// Debug file to track execution
file_put_contents(__DIR__ . '/webhook-debug.log', date('c') . " - CSV written, member: $memberId, dues: $duesId\n", FILE_APPEND);

// ----------------------------------------------------------------------
// CALL AXTRAX PRO API TO UPDATE MEMBER VALIDITY (INSTANT DOOR ACCESS)
// ----------------------------------------------------------------------
try {
  error_log("QuickPay webhook: Entering try block");
  require_once __DIR__ . '/../../_bootstrap.php';
  error_log("QuickPay webhook: _bootstrap.php loaded");
  require_once __DIR__ . '/../axtrax-helpers.php';
  error_log("QuickPay webhook: axtrax-helpers.php loaded");
  require_once __DIR__ . '/../axtrax-config.php';
  error_log("QuickPay webhook: axtrax-config.php loaded");

  $axtraxConfig = require __DIR__ . '/../axtrax-config.php';
  $pdo = pdo();
  error_log("QuickPay webhook: PDO connection obtained");

  // Get member info from AHF database
  $stmt = $pdo->prepare("SELECT first_name, last_name, email FROM members WHERE id = ?");
  $stmt->execute([$memberId]);
  $member = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$member) {
    error_log("QuickPay webhook: Member #$memberId not found in AHF database");
    http_response_code(202);
    exit("member not found");
  }

  $firstName = $member['first_name'];
  $lastName = $member['last_name'];
  $email = $member['email'] ?? '';

  error_log("QuickPay webhook: Processing payment for $firstName $lastName (Member #$memberId)");

  // Call AxTrax Pro API to extend membership by 30 days
  // This gives INSTANT door access
  $success = axtraxExtendMembership($email, $firstName, $lastName, 30, $axtraxConfig);

  if ($success) {
    error_log("QuickPay webhook: ✓ AxTrax Pro updated successfully - INSTANT door access enabled for $firstName $lastName");

    // Update dues to mark as paid in AHF
    if (!empty($duesId)) {
      $stmt2 = $pdo->prepare("
        UPDATE dues
        SET status = 'paid',
            paid_at = NOW()
        WHERE id = :dues_id AND status IN ('due', 'failed')
      ");
      $stmt2->execute([':dues_id' => $duesId]);
      $duesRowsUpdated = $stmt2->rowCount();
      error_log("QuickPay webhook: Marked dues #$duesId as paid (rows: $duesRowsUpdated)");
    }

    // Note: AxTrax Pro → AHF sync will update member status to 'current' within 15 minutes

  } else {
    error_log("QuickPay webhook: ✗ Failed to update AxTrax Pro for $firstName $lastName - door access NOT updated");

    // FALLBACK: Update AHF directly (but door won't open until AxTrax Pro is manually updated)
    $newValidUntil = date('Y-m-d', strtotime('+30 days'));
    $stmt = $pdo->prepare("
      UPDATE members
      SET valid_until = :valid_until,
          status = 'current'
      WHERE id = :member_id
    ");
    $stmt->execute([
      ':valid_until' => $newValidUntil,
      ':member_id' => $memberId
    ]);
    error_log("QuickPay webhook: FALLBACK - Updated AHF database only (door access requires manual AxTrax update)");
  }

} catch (Throwable $e) {
  error_log("QuickPay webhook error: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
  // Log error but don't fail the webhook
}

http_response_code(202);
echo "ok";
