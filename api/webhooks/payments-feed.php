<?php
// api/webhooks/payments-feed.php (READ)
declare(strict_types=1);

// CONFIG
$csvPath = 'C:\\AxTrax\\payments.csv';
$requireHttps = true;
$token = '9f8942431246fd7490b35fb27dfeb15edb7c68b01c3cc34e967ef43c8478113f';   // generate 64 hex chars: php -r "echo bin2hex(random_bytes(32)).PHP_EOL;"

if ($requireHttps && (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off')) {
  http_response_code(400); exit('HTTPS required');
}

$auth = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
if (!preg_match('/^Bearer\s+(.+)$/i', $auth, $m) || !hash_equals($token, $m[1])) {
  http_response_code(401); header('Content-Type: text/plain'); exit('unauthorized');
}

header('Content-Type: text/csv; charset=utf-8');
header('Cache-Control: no-store, max-age=0');
header('X-Content-Type-Options: nosniff');

if (!is_file($csvPath)) { exit; } // empty if no data yet

$fh = @fopen($csvPath, 'rb');
if ($fh === false) { http_response_code(500); exit('cannot open'); }
@flock($fh, LOCK_SH);
fpassthru($fh);
@flock($fh, LOCK_UN);
fclose($fh);
