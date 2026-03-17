<?php
/**
 * AxTrax Add User API
 * Create new member in AxTrax access control system
 */
declare(strict_types=1);
header('Content-Type: application/json');

require_once __DIR__ . '/axtrax-config.php';

session_start();
if (empty($_SESSION['logged_in'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'error' => 'unauthorized']);
    exit;
}

$config = require __DIR__ . '/axtrax-config.php';

function authenticateAxTrax(array $config): ?string {
    $url = rtrim($config['base_url'], '/') . '/token';
    $postData = http_build_query([
        'grant_type' => 'password',
        'username' => $config['oauth_username'],
        'password' => $config['oauth_password'],
    ]);

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $config['timeout'],
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $postData,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/x-www-form-urlencoded',
            'Accept: application/json',
        ],
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) return null;

    $data = json_decode($response, true);
    return $data['access_token'] ?? null;
}

function addUserToAxTrax(array $config, string $token, array $userData): ?array {
    $url = rtrim($config['base_url'], '/') . '/api/User/AddUser';

    // Build the user object for AxTrax
    $axtraxUser = [
        'tFirstName' => $userData['first_name'] ?? '',
        'tLastName' => $userData['last_name'] ?? '',
        'tEmail' => $userData['email'] ?? '',
        'tTel' => $userData['phone'] ?? '',
        'tAddress' => $userData['address'] ?? '',
        'dtStartDate' => $userData['start_date'] ?? date('Y-m-d\TH:i:s'),
        'dtStopDate' => $userData['stop_date'] ?? null,
        'tNotes' => $userData['notes'] ?? '',
        'bEnabled' => true,
        'wStatus' => 1,
        'tDesc' => trim(($userData['first_name'] ?? '') . ' ' . ($userData['last_name'] ?? '')),

        // Set department if provided
        'UserDepartment' => isset($userData['department_id']) ? [
            'ID' => (int)$userData['department_id'],
            'bEnabled' => true
        ] : null,

        // Empty arrays for optional data
        'UserCards' => [],
        'TimezoneReaders' => [],
        'AdditionalAccessGroups' => [],
        'AdditionalReaders' => [],
    ];

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $config['timeout'],
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($axtraxUser),
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
            'Accept: application/json',
        ],
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        return ['success' => false, 'code' => $httpCode, 'response' => $response];
    }

    return ['success' => true, 'code' => $httpCode, 'data' => json_decode($response, true)];
}

try {
    // Get request data
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (!$data) {
        throw new Exception('Invalid request data');
    }

    // Validate required fields
    if (empty($data['first_name']) || empty($data['last_name'])) {
        throw new Exception('First name and last name are required');
    }

    $token = authenticateAxTrax($config);
    if (!$token) {
        throw new Exception('Failed to authenticate with AxTrax');
    }

    $result = addUserToAxTrax($config, $token, $data);

    if (!$result['success']) {
        throw new Exception('Failed to add user to AxTrax: HTTP ' . $result['code']);
    }

    echo json_encode([
        'ok' => true,
        'message' => 'User added to AxTrax successfully',
        'axtrax_data' => $result['data']
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage()
    ]);
}
