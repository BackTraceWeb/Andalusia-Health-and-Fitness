// api/webhooks/payments-feed.php
<?php
declare(strict_types=1);

require __DIR__ . '/../../_bootstrap.php';
require_once __DIR__ . '/../integrations/axtrax/client.php';

$csvPath      = __DIR__ . '/payments.csv';
$requireHttps = true;
$token        = '9f8942431246fd7490b35fb27dfeb15edb7c68b01c3cc34e967ef43c8478113f';  // php -r "echo bin2hex(random_bytes(32)).PHP_EOL;"

if ($requireHttps && (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off')) {
  http_response_code(400);
  exit('HTTPS required');
}

$auth = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
if (!preg_match('/^Bearer\s+(.+)$/i', $auth, $m) || !hash_equals($token, $m[1])) {
  http_response_code(401);
  header('Content-Type: text/plain');
  exit('unauthorized');
}

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

try {
  if ($method === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, max-age=0');
    processReactivateRequest($pdo, $csvPath);
  } else {
    header('Content-Type: text/csv; charset=utf-8');
    header('Cache-Control: no-store, max-age=0');
    header('X-Content-Type-Options: nosniff');
    streamLegacyCsv($csvPath);
  }
} catch (LogicException $e) {
  http_response_code(501);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode(['ok' => false, 'error' => 'axtrax_not_ready', 'detail' => $e->getMessage()]);
} catch (Throwable $e) {
  http_response_code(500);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode(['ok' => false, 'error' => 'server_error', 'detail' => $e->getMessage()]);
}

/**
 * Handles POST /payments-feed payloads to trigger AxTrax reactivation.
 */
function processReactivateRequest(PDO $pdo, string $csvPath): void
{
  $raw = file_get_contents('php://input') ?: '';
  $body = json_decode($raw, true);

  if (!is_array($body)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'invalid_json']);
    return;
  }

  $memberId  = isset($body['member_id']) ? (int)$body['member_id'] : 0;
  $invoiceId = isset($body['invoice_id']) ? (int)$body['invoice_id'] : 0;

  if ($memberId <= 0 && $invoiceId > 0) {
    $stmt = $pdo->prepare('SELECT member_id FROM dues WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $invoiceId]);
    $memberId = (int)$stmt->fetchColumn();
  }

  if ($memberId <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'member_id_required']);
    return;
  }

  $validUntil = isset($body['valid_until']) ? trim((string)$body['valid_until']) : '';
  if ($validUntil === '') {
    $stmt = $pdo->prepare('SELECT valid_until FROM members WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $memberId]);
    $col = $stmt->fetchColumn();
    if ($col === false || $col === null || $col === '') {
      http_response_code(404);
      echo json_encode(['ok' => false, 'error' => 'member_valid_until_missing']);
      return;
    }
    $validUntil = (string)$col;
  }

  $client = AxtraxClient::buildFromConfig();

  // Stage until vendor provides REST contract; the call will throw LogicException.
  $axtraxResponse = $client->updateMemberValidity($memberId, $validUntil);

  echo json_encode([
    'ok'           => true,
    'member_id'    => $memberId,
    'valid_until'  => $validUntil,
    'axtrax'       => $axtraxResponse,
  ]);
}

/**
 * Keeps legacy CSV feed available while AxTrax integration is finalized.
 */
function streamLegacyCsv(string $csvPath): void
{
  if (!is_file($csvPath)) {
    http_response_code(204);
    return;
  }

  $fh = fopen($csvPath, 'rb');
  if (!$fh) {
    http_response_code(500);
    exit('cannot open');
  }

  flock($fh, LOCK_SH);
  fpassthru($fh);
  flock($fh, LOCK_UN);
  fclose($fh);
}
