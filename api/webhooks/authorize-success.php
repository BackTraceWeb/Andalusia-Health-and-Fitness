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
// CALL AXTRAX REST API TO UPDATE MEMBER VALIDITY
// ----------------------------------------------------------------------
// Correct flow: Authorize.Net → Our webhook → AxTrax REST API → AxTrax updates its DB → AxTrax calls back → We update our DB
try {
  require_once __DIR__ . '/../../_bootstrap.php';
  require_once __DIR__ . '/../integrations/axtrax/client.php';

  // Calculate new valid_until date (30 days from today)
  $newValidUntil = date('Y-m-d', strtotime('+30 days'));

  error_log("Payment webhook: Calling AxTrax REST API for member #$memberId with valid_until=$newValidUntil");

  try {
    // Call AxTrax REST API to update member validity
    $axtrax = AxtraxClient::buildFromConfig();
    $axtraxResponse = $axtrax->updateMemberValidity((int)$memberId, $newValidUntil);

    error_log("AxTrax REST API success for member #$memberId: " . json_encode($axtraxResponse));

  } catch (LogicException $notReady) {
    // AxTrax REST API not configured yet - log and continue
    error_log("AxTrax REST API not configured yet (expected): " . $notReady->getMessage());
    error_log("Once configured, AxTrax will update its database and call back to /api/webhooks/axtrax-callback.php");

    // TEMPORARY: For now, update our database directly until AxTrax is configured
    // This code will be removed once AxTrax callback is working
    error_log("TEMPORARY: Updating our database directly until AxTrax is configured");
    $pdo = pdo();

    // Update member validity
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
    $memberRowsUpdated = $stmt->rowCount();
    error_log("TEMPORARY: Direct DB update for member #$memberId (rows: $memberRowsUpdated)");

    // Update dues to mark as paid
    if (!empty($duesId)) {
      $stmt2 = $pdo->prepare("
        UPDATE dues
        SET status = 'paid',
            paid_at = NOW()
        WHERE id = :dues_id AND status IN ('due', 'failed')
      ");
      $stmt2->execute([':dues_id' => $duesId]);
      $duesRowsUpdated = $stmt2->rowCount();
      error_log("TEMPORARY: Marked dues #$duesId as paid (rows: $duesRowsUpdated)");
    }

  } catch (RuntimeException $axErr) {
    // AxTrax configuration issue or API error
    error_log("AxTrax REST API error for member #$memberId: " . $axErr->getMessage());
    // Don't fail the payment webhook if AxTrax sync fails
  }

} catch (Throwable $e) {
  error_log("Webhook processing error: " . $e->getMessage());
  // Log error but don't fail the webhook
}

http_response_code(202);
echo "ok";

