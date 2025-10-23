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

http_response_code(202);
echo "ok";

