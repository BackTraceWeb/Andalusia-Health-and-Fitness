<?php
// C:\AxTrax\webhook.php
// Make sure the folder C:\AxTrax exists and is writable by PHP user.

date_default_timezone_set('UTC'); // keep UTC in the log

$raw = file_get_contents('php://input');
$headers = array_change_key_case(getallheaders(), CASE_UPPER);
$sig = $headers['X-ANET-SIGNATURE'] ?? '';

// ---- HMAC verify (enable in PROD) ----
$prod = true; // set to true in production
$apiSignatureKeyHex = '21C260460E71D8FFC7437BA38D1938A0D8C531810250F8FC36CB06197C93776609F7A6AB937FD53DE0C0E43409B4BF6AC89F7AC1FBA77BDFA4DE9ECC535F3C7C'; // from Authorize.Net (Signature Key, hex)
if ($prod) {
    $calc = 'sha512=' . hash_hmac('sha512', $raw, pack('H*', $apiSignatureKeyHex));
    if (!hash_equals(strtolower($calc), strtolower($sig))) {
        http_response_code(401);
        error_log("ANET HMAC mismatch. Provided=$sig Calc=$calc");
        exit("invalid signature");
    }
} else {
    error_log("⚠ HMAC bypass (sandbox/dev)");
}

// ---- Parse payload ----
$body = json_decode($raw, true);
if (!$body || !isset($body['payload'])) { http_response_code(400); exit("bad payload"); }

$evt  = $body['eventType'] ?? '';
$pl   = $body['payload'];
$resp = $pl['responseCode'] ?? 0;
$iid  = $pl['invoiceNumber'] ?? '';
$pid  = $pl['id'] ?? '';
$amt  = $pl['authAmount'] ?? 0;

if ($evt !== 'net.authorize.payment.authcapture.created' || (int)$resp !== 1) {
    http_response_code(200); // ignore non-success
    exit("ignored");
}

// ---- Extract MemberId from invoiceNumber "QP<batch>M<memberId>" ----
$memberId = null;
if (preg_match('/^QP(\d+)M(\d+)$/', $iid, $m)) {
    $memberId = $m[2];
} else {
    // if you ever change the format, log & bail
    error_log("Invoice parse failed: $iid");
    http_response_code(200);
    exit("ignored");
}

// ---- Append to payments.csv ----
// Columns: When,PaymentId,InvoiceNumber,MemberId,Amount
$line = sprintf("%s,%s,%s,%s,%.2f\n",
    gmdate('c'),           // ISO-8601 UTC
    $pid,
    $iid,
    $memberId,
    $amt
);

$file = 'C:\\AxTrax\\payments.csv';
$ok = file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
if ($ok === false) {
    error_log("Failed to write $file");
    http_response_code(500);
    exit("write failed");
}

http_response_code(200);
echo "ok";
