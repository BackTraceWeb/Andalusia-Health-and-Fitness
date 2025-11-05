<?php
/**
 * AxTrax Callback Endpoint
 *
 * This endpoint is called by AxTrax after it updates its database following a successful payment.
 *
 * Expected flow:
 * 1. Authorize.Net sends webhook to authorize-success.php
 * 2. authorize-success.php calls AxTrax REST API with member_id and valid_until
 * 3. AxTrax updates its door access database
 * 4. AxTrax calls THIS endpoint to update our member database
 * 5. This endpoint updates member status and marks invoice as paid
 */
declare(strict_types=1);

require_once __DIR__ . '/../../_bootstrap.php';

// ----------------------------------------------------------------------
// Authentication - Verify bearer token
// ----------------------------------------------------------------------
$headers = array_change_key_case(function_exists('getallheaders') ? getallheaders() : [], CASE_UPPER);
$authHeader = $headers['AUTHORIZATION'] ?? '';

// Extract bearer token
$expectedToken = config('AXTRAX_CALLBACK_TOKEN', '9f8942431246fd7490b35fb27dfeb15edb7c68b01c3cc34e967ef43c8478113f');

if (!preg_match('/^Bearer\s+(.+)$/i', $authHeader, $matches)) {
    http_response_code(401);
    error_log("AxTrax callback: Missing or invalid Authorization header");
    exit(json_encode(['error' => 'Unauthorized']));
}

$providedToken = $matches[1];
if (!hash_equals($expectedToken, $providedToken)) {
    http_response_code(401);
    error_log("AxTrax callback: Invalid bearer token");
    exit(json_encode(['error' => 'Unauthorized']));
}

// ----------------------------------------------------------------------
// Parse request body
// ----------------------------------------------------------------------
$raw = file_get_contents('php://input');
$body = json_decode($raw, true);

if (!is_array($body)) {
    http_response_code(400);
    error_log("AxTrax callback: Invalid JSON body");
    exit(json_encode(['error' => 'Invalid JSON']));
}

// Log incoming request
error_log("AxTrax callback received: " . json_encode($body));

// Extract required fields
$memberId = (int)($body['member_id'] ?? 0);
$validUntil = (string)($body['valid_until'] ?? '');
$invoiceId = isset($body['invoice_id']) ? (int)$body['invoice_id'] : null;

if ($memberId <= 0 || $validUntil === '') {
    http_response_code(400);
    error_log("AxTrax callback: Missing required fields (member_id or valid_until)");
    exit(json_encode(['error' => 'Missing required fields: member_id, valid_until']));
}

// ----------------------------------------------------------------------
// Update our database
// ----------------------------------------------------------------------
try {
    $pdo = pdo();

    // Update member record
    $stmt = $pdo->prepare("
        UPDATE members
        SET valid_until = :valid_until,
            status = 'current',
            updated_at = NOW()
        WHERE id = :member_id
    ");

    $stmt->execute([
        ':valid_until' => $validUntil,
        ':member_id' => $memberId
    ]);

    $memberRowsUpdated = $stmt->rowCount();
    error_log("AxTrax callback: Updated member #$memberId, valid_until=$validUntil (rows: $memberRowsUpdated)");

    // If invoice_id provided, mark invoice as paid
    $invoiceRowsUpdated = 0;
    if ($invoiceId !== null && $invoiceId > 0) {
        $stmt2 = $pdo->prepare("
            UPDATE dues
            SET status = 'paid',
                paid_at = NOW()
            WHERE id = :invoice_id AND status IN ('due', 'failed')
        ");

        $stmt2->execute([':invoice_id' => $invoiceId]);
        $invoiceRowsUpdated = $stmt2->rowCount();
        error_log("AxTrax callback: Marked invoice #$invoiceId as paid (rows: $invoiceRowsUpdated)");
    }

    // Return success response
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'member_id' => $memberId,
        'member_updated' => $memberRowsUpdated > 0,
        'invoice_updated' => $invoiceRowsUpdated > 0
    ]);

} catch (Throwable $e) {
    error_log("AxTrax callback error: " . $e->getMessage());
    http_response_code(500);
    exit(json_encode(['error' => 'Database error']));
}
