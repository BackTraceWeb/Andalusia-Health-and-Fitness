<?php
/**
 * AxTrax Pro API Helper Functions
 *
 * Shared functions for interacting with AxTrax Pro API
 * Used by sync script and QuickPay integration
 */

declare(strict_types=1);

/**
 * Make authenticated API request to AxTrax Pro (supports GET, POST, PUT)
 */
function axtraxApiRequest(string $method, string $endpoint, array $config, ?array $data = null): ?array {
    // Authenticate
    $token = authenticateAxTrax($config);
    if ($token === null) {
        error_log("AxTrax API: Cannot make request - authentication failed");
        return null;
    }

    $url = rtrim($config['base_url'], '/') . '/' . ltrim($endpoint, '/');

    $ch = curl_init();
    $curlOpts = [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $config['timeout'],
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Accept: application/json',
            'Content-Type: application/json',
        ],
    ];

    // Set method and data
    if ($method === 'POST') {
        $curlOpts[CURLOPT_POST] = true;
        if ($data !== null) {
            $curlOpts[CURLOPT_POSTFIELDS] = json_encode($data);
        }
    } elseif ($method === 'PUT') {
        $curlOpts[CURLOPT_CUSTOMREQUEST] = 'PUT';
        if ($data !== null) {
            $curlOpts[CURLOPT_POSTFIELDS] = json_encode($data);
        }
    }

    curl_setopt_array($ch, $curlOpts);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($config['debug'] ?? false) {
        error_log("AxTrax API: {$method} {$url} - HTTP {$httpCode}");
    }

    if ($error) {
        error_log("AxTrax API: cURL error - {$error}");
        return null;
    }

    if ($httpCode !== 200) {
        error_log("AxTrax API: HTTP {$httpCode} - {$response}");
        return null;
    }

    $result = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log("AxTrax API: Invalid JSON response - " . json_last_error_msg());
        return null;
    }

    return $result;
}

/**
 * Find user in AxTrax Pro by email or name
 */
function axtraxFindUser(string $email, ?string $firstName = null, ?string $lastName = null, array $config): ?array {
    // Get all users
    $response = axtraxApiRequest('GET', '/api/User/GetUsers', $config);

    if ($response === null || !isset($response['Data'])) {
        return null;
    }

    $users = $response['Data'];

    // Try to match by email first
    if (!empty($email)) {
        foreach ($users as $user) {
            $userEmail = trim($user['tEmail'] ?? '');
            if (!empty($userEmail) && strcasecmp($userEmail, $email) === 0) {
                return $user;
            }
        }
    }

    // Try to match by first + last name
    if (!empty($firstName) && !empty($lastName)) {
        foreach ($users as $user) {
            $userFirst = trim($user['tFirstName'] ?? '');
            $userLast = trim($user['tLastName'] ?? '');

            if (strcasecmp($userFirst, $firstName) === 0 && strcasecmp($userLast, $lastName) === 0) {
                return $user;
            }
        }
    }

    return null;
}

/**
 * Extend user's membership in AxTrax Pro (update dtStopDate)
 *
 * @param string $email User's email
 * @param string|null $firstName User's first name (used if email not found)
 * @param string|null $lastName User's last name (used if email not found)
 * @param int $days Number of days to extend (default: 30)
 * @param array $config AxTrax config
 * @return bool Success
 */
function axtraxExtendMembership(string $email, ?string $firstName, ?string $lastName, int $days, array $config): bool {
    // Find user
    $user = axtraxFindUser($email, $firstName, $lastName, $config);

    if ($user === null) {
        error_log("AxTrax: User not found - email: {$email}, name: {$firstName} {$lastName}");
        return false;
    }

    // Calculate new dtStopDate
    $currentStopDate = $user['dtStopDate'] ?? null;

    if ($currentStopDate) {
        // Extend from current stop date
        $stopDateTime = new DateTime($currentStopDate);

        // FIX: If stop date is in the past, start from today instead
        // This prevents members who pay late from getting zero days of access
        $today = new DateTime();
        if ($stopDateTime < $today) {
            error_log("AxTrax: Member stop date ({$currentStopDate}) is expired, extending from today instead");
            $stopDateTime = $today;
        }
    } else {
        // No stop date, start from today
        $stopDateTime = new DateTime();
    }

    // Add days
    $stopDateTime->modify("+{$days} days");

    // Set to end of day (23:59:59)
    $stopDateTime->setTime(23, 59, 59);

    // Update user object
    $user['dtStopDate'] = $stopDateTime->format('Y-m-d\TH:i:s');

    // Send PUT request to update user
    $response = axtraxApiRequest('PUT', '/api/User/UpdateUser', $config, $user);

    if ($response === null) {
        error_log("AxTrax: Failed to update user #{$user['ID']} - {$firstName} {$lastName}");
        return false;
    }

    // Check for errors in response
    if (isset($response['Errors']) && !empty($response['Errors'])) {
        error_log("AxTrax: Update errors: " . json_encode($response['Errors']));
        return false;
    }

    error_log("AxTrax: Extended membership for user #{$user['ID']} - {$firstName} {$lastName} until " . $user['dtStopDate']);
    return true;
}

/**
 * Authenticate with AxTrax Pro OAuth2 and get access token
 */
function authenticateAxTrax(array $config): ?string {
    static $accessToken = null;

    if ($accessToken !== null) {
        return $accessToken;
    }

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
    $error = curl_error($ch);
    curl_close($ch);

    if ($error || $httpCode !== 200) {
        error_log("AxTrax OAuth: Authentication failed - HTTP {$httpCode}: {$error}");
        return null;
    }

    $data = json_decode($response, true);
    if (!isset($data['access_token'])) {
        error_log("AxTrax OAuth: No access_token in response");
        return null;
    }

    $accessToken = $data['access_token'];
    return $accessToken;
}
