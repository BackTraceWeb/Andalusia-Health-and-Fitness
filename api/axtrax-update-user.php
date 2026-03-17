<?php
/**
 * AxTrax Update User API
 * Update existing member information in AxTrax access control system
 * Uses same pattern as QuickPay (PUT method, full user object)
 */
declare(strict_types=1);
header('Content-Type: application/json');

require_once __DIR__ . '/axtrax-config.php';
require_once __DIR__ . '/axtrax-helpers.php';

session_start();
if (empty($_SESSION['logged_in'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'unauthorized']);
    exit;
}

$config = require __DIR__ . '/axtrax-config.php';

try {
    // Get request data
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (!$data) {
        throw new Exception('Invalid request data');
    }

    if (empty($data['updates']) || !is_array($data['updates'])) {
        throw new Exception('Updates array is required');
    }

    // Find user in AxTrax
    $firstName = $data['first_name'] ?? '';
    $lastName = $data['last_name'] ?? '';

    if (empty($firstName) || empty($lastName)) {
        throw new Exception('First name and last name are required to find user');
    }

    $user = axtraxFindUser('', $firstName, $lastName, $config);

    if ($user === null) {
        throw new Exception("User not found in AxTrax: {$firstName} {$lastName}");
    }

    // Apply updates to user object
    $updates = $data['updates'];

    if (isset($updates['first_name'])) {
        $user['tFirstName'] = $updates['first_name'];
    }

    if (isset($updates['last_name'])) {
        $user['tLastName'] = $updates['last_name'];
    }

    if (isset($updates['email'])) {
        $user['tEmail'] = $updates['email'];
    }

    if (isset($updates['stop_date']) && !empty($updates['stop_date'])) {
        // Convert date to AxTrax format (Y-m-d\TH:i:s)
        $stopDate = new DateTime($updates['stop_date']);
        $stopDate->setTime(23, 59, 59); // End of day
        $user['dtStopDate'] = $stopDate->format('Y-m-d\TH:i:s');
    }

    if (isset($updates['notes'])) {
        $user['tNotes'] = $updates['notes'];
    }

    // Update description if name changed
    if (isset($updates['first_name']) || isset($updates['last_name'])) {
        $user['tDesc'] = trim($user['tFirstName'] . ' ' . $user['tLastName']);
    }

    // Send PUT request (same as QuickPay pattern)
    $response = axtraxApiRequest('PUT', '/api/User/UpdateUser', $config, $user);

    if ($response === null) {
        throw new Exception('Failed to update user in AxTrax');
    }

    // Check for errors in response
    if (isset($response['Errors']) && !empty($response['Errors'])) {
        throw new Exception('AxTrax API errors: ' . json_encode($response['Errors']));
    }

    echo json_encode([
        'ok' => true,
        'message' => 'User updated successfully in AxTrax',
        'user_id' => $user['ID']
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage()
    ]);
}
