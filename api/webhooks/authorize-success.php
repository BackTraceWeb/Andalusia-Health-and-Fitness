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

// Extract MemberId from invoice like "QP<batch>M<memberId>"
$memberId = '';
if (preg_match('/^QP(\d+)M(\d+)$/', $iid, $m)) {
  $memberId = $m[2];
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
// UPDATE MEMBER RECORD: Set valid_until to +30 days from today
// ----------------------------------------------------------------------
try {
  require_once __DIR__ . '/../../_bootstrap.php';
  $pdo = pdo();

  // Calculate new valid_until date (30 days from today)
  $newValidUntil = date('Y-m-d', strtotime('+30 days'));

  // Update member record
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

  $rowsUpdated = $stmt->rowCount();
  error_log("Payment webhook: Updated member #$memberId valid_until to $newValidUntil (rows: $rowsUpdated)");

  // ----------------------------------------------------------------------
  // TRIGGER AXTRAX SYNC (staged - will work when REST API creds are ready)
  // ----------------------------------------------------------------------
  try {
    $webhookUrl = 'https://andalusiahealthandfitness.com/api/webhooks/payments-feed.php';
    $bearerToken = config('WEBHOOK_BEARER_TOKEN', '9f8942431246fd7490b35fb27dfeb15edb7c68b01c3cc34e967ef43c8478113f');

    $payload = json_encode([
      'member_id' => (int)$memberId,
      'valid_until' => $newValidUntil,
      'payment_id' => $pid,
      'amount' => $amt
    ]);

    $ch = curl_init($webhookUrl);
    curl_setopt_array($ch, [
      CURLOPT_POST => true,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        "Authorization: Bearer $bearerToken"
      ],
      CURLOPT_POSTFIELDS => $payload,
      CURLOPT_TIMEOUT => 5
    ]);

    $axtraxResp = curl_exec($ch);
    $axtraxHttp = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Log AxTrax sync attempt (may fail with 501 until REST API is ready)
    if ($axtraxHttp === 501) {
      error_log("AxTrax sync staged for member #$memberId - REST API not ready yet");
    } elseif ($axtraxHttp === 200) {
      error_log("AxTrax sync successful for member #$memberId: $axtraxResp");
    } else {
      error_log("AxTrax sync HTTP $axtraxHttp for member #$memberId: $axtraxResp");
    }

  } catch (Throwable $axErr) {
    error_log("AxTrax sync error for member #$memberId: " . $axErr->getMessage());
    // Don't fail the payment webhook if AxTrax sync fails
  }

} catch (Throwable $e) {
  error_log("Member update error: " . $e->getMessage());
  // Log error but don't fail the webhook
}

http_response_code(202);
echo "ok";

