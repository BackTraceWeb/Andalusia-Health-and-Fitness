<?php
/**
 * AxTrax Pro Database Sync
 *
 * Syncs member data from AxTrax Pro REST API to AHF database.
 * AxTrax Pro is the source of truth for member information.
 *
 * Usage:
 *   php axtrax-sync.php                    # Full sync
 *   php axtrax-sync.php --test             # Test connection only
 *   php axtrax-sync.php --discover         # Discover available endpoints
 *   php axtrax-sync.php --dry-run          # Preview changes without saving
 */

declare(strict_types=1);

// Load configuration
$configFile = __DIR__ . '/axtrax-config.php';
if (!file_exists($configFile)) {
    die("ERROR: Config file not found at {$configFile}\n");
}

$config = require $configFile;

// Validate configuration
if (strpos($config['base_url'], 'PLACEHOLDER') !== false ||
    strpos($config['oauth_username'], 'PLACEHOLDER') !== false ||
    strpos($config['oauth_password'], 'PLACEHOLDER') !== false) {
    die("ERROR: Please configure real credentials in {$configFile}\n");
}

// Command line options
$options = getopt('', ['test', 'discover', 'dry-run']);
$testMode = isset($options['test']);
$discoverMode = isset($options['discover']);
$dryRun = isset($options['dry-run']);

// Database connection
require __DIR__ . '/../_bootstrap.php';

// Global token cache
$accessToken = null;

/**
 * Authenticate with AxTrax Pro OAuth2 and get access token
 */
function authenticateAxTrax(array $config): ?string {
    global $accessToken;

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

    if ($config['debug']) {
        logMessage("OAuth2 Request: {$url}");
        logMessage("HTTP Code: {$httpCode}");
    }

    if ($error) {
        logMessage("ERROR: cURL error during auth - {$error}", true);
        return null;
    }

    if ($httpCode !== 200) {
        logMessage("ERROR: Auth failed HTTP {$httpCode} - {$response}", true);
        return null;
    }

    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        logMessage("ERROR: Invalid JSON in auth response - " . json_last_error_msg(), true);
        return null;
    }

    if (!isset($data['access_token'])) {
        logMessage("ERROR: No access_token in response: " . json_encode($data), true);
        return null;
    }

    $accessToken = $data['access_token'];

    if ($config['debug']) {
        logMessage("Successfully authenticated. Token expires in: " . ($data['expires_in'] ?? 'unknown'));
    }

    return $accessToken;
}

/**
 * Make authenticated API request to AxTrax Pro
 */
function axtraxRequest(string $endpoint, array $config): ?array {
    $token = authenticateAxTrax($config);
    if ($token === null) {
        logMessage("ERROR: Cannot make request - authentication failed", true);
        return null;
    }

    $url = rtrim($config['base_url'], '/') . '/' . ltrim($endpoint, '/');

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $config['timeout'],
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Accept: application/json',
            'Content-Type: application/json',
        ],
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($config['debug']) {
        logMessage("API Request: {$url}");
        logMessage("HTTP Code: {$httpCode}");
    }

    if ($error) {
        logMessage("ERROR: cURL error - {$error}", true);
        return null;
    }

    if ($httpCode !== 200) {
        logMessage("ERROR: HTTP {$httpCode} - {$response}", true);
        return null;
    }

    $data = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        logMessage("ERROR: Invalid JSON response - " . json_last_error_msg(), true);
        return null;
    }

    return $data;
}

/**
 * Log message to console and optionally to file
 */
function logMessage(string $message, bool $isError = false): void {
    global $config;

    $timestamp = date('Y-m-d H:i:s');
    $logLine = "[{$timestamp}] {$message}\n";

    echo $logLine;

    if ($config['log_file'] && is_writable(dirname($config['log_file']))) {
        file_put_contents($config['log_file'], $logLine, FILE_APPEND);
    }
}

/**
 * Test API connection and authentication
 */
function testConnection(array $config): bool {
    echo "Testing connection to AxTrax Pro...\n";
    echo "Base URL: {$config['base_url']}\n\n";

    // Test authentication
    echo "Step 1: Testing OAuth2 authentication... ";
    $token = authenticateAxTrax($config);

    if ($token === null) {
        echo "✗ FAILED\n";
        return false;
    }

    echo "✓ SUCCESS\n";
    echo "Access token received (length: " . strlen($token) . ")\n\n";

    // Test data endpoint
    echo "Step 2: Testing data endpoint /api/User/GetUsers... ";
    $result = axtraxRequest('/api/User/GetUsers', $config);

    if ($result !== null) {
        echo "✓ SUCCESS\n";
        echo "Response structure: " . json_encode(array_keys($result), JSON_PRETTY_PRINT) . "\n";

        if (is_array($result) && !empty($result)) {
            $first = is_array($result) && isset($result[0]) ? $result[0] : reset($result);
            if (is_array($first)) {
                echo "User fields: " . implode(', ', array_keys($first)) . "\n";
            }
        }

        return true;
    }

    echo "✗ FAILED\n";
    return false;
}

/**
 * Discover available API endpoints
 */
function discoverEndpoints(array $config): void {
    echo "Discovering AxTrax Pro API endpoints...\n\n";

    // First authenticate
    echo "Authenticating... ";
    $token = authenticateAxTrax($config);
    if ($token === null) {
        echo "✗ FAILED - Cannot discover endpoints without authentication\n";
        return;
    }
    echo "✓\n\n";

    $commonEndpoints = [
        '/api/User/GetUsers',
        '/api/User/GetUser',
        '/api/Member/GetMembers',
        '/api/Member/GetMember',
        '/api/Customer/GetCustomers',
        '/api/Account/GetAccounts',
        '/api/Membership/GetMemberships',
        '/api/Contract/GetContracts',
        '/api/Payment/GetPayments',
    ];

    $found = [];

    foreach ($commonEndpoints as $endpoint) {
        echo "Trying {$endpoint}... ";
        $result = axtraxRequest($endpoint, $config);

        if ($result !== null) {
            echo "✓ FOUND\n";
            $found[$endpoint] = $result;

            // Show structure
            if (is_array($result)) {
                if (isset($result[0]) && is_array($result[0])) {
                    echo "  Fields: " . implode(', ', array_keys($result[0])) . "\n";
                    echo "  Count: " . count($result) . " items\n";
                } else {
                    $keys = array_keys($result);
                    echo "  Keys: " . implode(', ', $keys) . "\n";
                }
            }
            echo "\n";
        } else {
            echo "✗\n";
        }
    }

    if (empty($found)) {
        echo "\nNo endpoints discovered.\n";
    } else {
        echo "\n=== DISCOVERED ENDPOINTS ===\n";
        foreach ($found as $endpoint => $data) {
            echo "  {$endpoint}\n";
        }
    }
}

/**
 * Map AxTrax department name to monthly fee
 * AxTrax Pro has departments but no pricing, so we map them to AHF pricing
 */
function getDepartmentFee(string $departmentName): float {
    // Normalize department name (case-insensitive, trim spaces)
    $dept = strtolower(trim($departmentName));

    // Department → Monthly Fee mapping
    if (str_contains($dept, 'single')) {
        return 35.00;
    }
    if (str_contains($dept, 'couple') || str_contains($dept, 'married')) {
        return 55.00;
    }
    if (str_contains($dept, 'family')) {
        return 65.00;
    }
    if (str_contains($dept, 'senior') || str_contains($dept, '65+') || str_contains($dept, '65 +')) {
        return 25.00;
    }
    if (str_contains($dept, 'student')) {
        return 30.00;
    }

    // Default fallback for unknown departments
    return 35.00;
}

/**
 * Sync members from AxTrax Pro to AHF database
 */
function syncMembers(array $config, bool $dryRun = false): void {
    logMessage("Starting member sync from AxTrax Pro...");

    // Authenticate first
    $token = authenticateAxTrax($config);
    if ($token === null) {
        logMessage("ERROR: Authentication failed, cannot sync", true);
        return;
    }

    // Fetch users from AxTrax Pro
    logMessage("Fetching users from /api/User/GetUsers");
    $users = axtraxRequest('/api/User/GetUsers', $config);

    if ($users === null) {
        logMessage("ERROR: Could not fetch users from AxTrax Pro", true);
        return;
    }

    // Handle different response structures
    $usersList = [];
    if (isset($users['Data']) && is_array($users['Data'])) {
        $usersList = $users['Data'];
    } elseif (isset($users['data']) && is_array($users['data'])) {
        $usersList = $users['data'];
    } elseif (is_array($users) && isset($users[0])) {
        $usersList = $users;
    } else {
        $usersList = [$users];
    }

    if (empty($usersList)) {
        logMessage("WARNING: No users found in AxTrax Pro");
        return;
    }

    logMessage("Found " . count($usersList) . " users in AxTrax Pro");

    if ($dryRun) {
        echo "\n=== DRY RUN MODE - Preview of first user ===\n";
        echo json_encode($usersList[0], JSON_PRETTY_PRINT) . "\n";
        echo "\n=== Available fields ===\n";
        echo implode(', ', array_keys($usersList[0])) . "\n";
        return;
    }

    // Get database connection
    $pdo = pdo();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $synced = 0;
    $updated = 0;
    $inserted = 0;
    $skipped = 0;
    $errors = 0;

    foreach ($usersList as $user) {
        try {
            // Extract fields from AxTrax Pro
            $firstName = trim($user['tFirstName'] ?? '');
            $lastName = trim($user['tLastName'] ?? '');
            $email = trim($user['tEmail'] ?? '');
            $validFrom = $user['dtStartDate'] ?? null;
            $validUntil = $user['dtStopDate'] ?? null;
            $departmentName = $user['UserDepartment']['tDesc'] ?? null;
            $notes = trim($user['tNotes'] ?? '');
            $address = trim($user['tAddress'] ?? '');

            // Check if member is on bank draft (automatic payment)
            // Check both tNotes and tAddress fields for "draft"
            $isDraft = (stripos($notes, 'draft') !== false || stripos($address, 'draft') !== false) ? 1 : 0;

            // Skip users without name
            if (empty($firstName) && empty($lastName)) {
                $skipped++;
                continue;
            }

            // Calculate status based on department and valid_until date
            // Check if member is in Inactive department first
            if ($departmentName && stripos($departmentName, 'inactive') !== false) {
                $status = 'inactive';
            } elseif ($isDraft) {
                // Draft members are always current (automatic payment)
                $status = 'current';
            } else {
                $status = 'current';
                if ($validUntil) {
                    $stopDate = new DateTime($validUntil);
                    $today = new DateTime('today');
                    if ($stopDate < $today) {
                        $status = 'due';
                    }
                }
            }

            // Format dates for MySQL
            $validFromFormatted = null;
            if ($validFrom) {
                try {
                    $validFromFormatted = (new DateTime($validFrom))->format('Y-m-d');
                } catch (Exception $e) {
                    // Invalid date, leave null
                }
            }

            $validUntilFormatted = null;
            if ($validUntil) {
                try {
                    $validUntilFormatted = (new DateTime($validUntil))->format('Y-m-d');
                } catch (Exception $e) {
                    // Invalid date, leave null
                }
            }

            // Try to find existing member in AHF
            $existingMember = null;

            // Match by email first (if email exists and valid)
            if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $stmt = $pdo->prepare("SELECT * FROM members WHERE LOWER(email) = LOWER(?) LIMIT 1");
                $stmt->execute([$email]);
                $existingMember = $stmt->fetch(PDO::FETCH_ASSOC);
            }

            // If no email match, try first + last name (both present)
            if (!$existingMember && !empty($firstName) && !empty($lastName)) {
                $stmt = $pdo->prepare("
                    SELECT * FROM members
                    WHERE LOWER(first_name) = LOWER(?)
                      AND LOWER(last_name) = LOWER(?)
                    ORDER BY id DESC
                    LIMIT 1
                ");
                $stmt->execute([$firstName, $lastName]);
                $existingMember = $stmt->fetch(PDO::FETCH_ASSOC);
            }

            // If no match yet, try matching by department + single name (for incomplete records)
            if (!$existingMember && !empty($departmentName)) {
                if (!empty($firstName) && empty($lastName)) {
                    // Only first name + department
                    $stmt = $pdo->prepare("
                        SELECT * FROM members
                        WHERE LOWER(first_name) = LOWER(?)
                          AND LOWER(last_name) = ''
                          AND LOWER(department_name) = LOWER(?)
                        ORDER BY id DESC
                        LIMIT 1
                    ");
                    $stmt->execute([$firstName, $departmentName]);
                    $existingMember = $stmt->fetch(PDO::FETCH_ASSOC);
                } elseif (empty($firstName) && !empty($lastName)) {
                    // Only last name + department
                    $stmt = $pdo->prepare("
                        SELECT * FROM members
                        WHERE LOWER(first_name) = ''
                          AND LOWER(last_name) = LOWER(?)
                          AND LOWER(department_name) = LOWER(?)
                        ORDER BY id DESC
                        LIMIT 1
                    ");
                    $stmt->execute([$lastName, $departmentName]);
                    $existingMember = $stmt->fetch(PDO::FETCH_ASSOC);
                }
            }

            if ($existingMember) {
                // UPDATE existing member (preserve monthly_fee and payment_type)
                $stmt = $pdo->prepare("
                    UPDATE members SET
                        first_name = ?,
                        last_name = ?,
                        email = ?,
                        department_name = ?,
                        valid_from = ?,
                        valid_until = ?,
                        status = ?,
                        notes = ?,
                        is_draft = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $firstName,
                    $lastName,
                    $email,
                    $departmentName,
                    $validFromFormatted,
                    $validUntilFormatted,
                    $status,
                    $notes,
                    $isDraft,
                    $existingMember['id']
                ]);

                if ($config['debug']) {
                    logMessage("Updated member #{$existingMember['id']}: {$firstName} {$lastName}");
                }
                $updated++;
            } else {
                // INSERT new member with department-based pricing
                $monthlyFee = getDepartmentFee($departmentName ?? '');

                $stmt = $pdo->prepare("
                    INSERT INTO members (
                        first_name,
                        last_name,
                        email,
                        department_name,
                        monthly_fee,
                        payment_type,
                        valid_from,
                        valid_until,
                        status,
                        notes,
                        is_draft
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $firstName,
                    $lastName,
                    $email,
                    $departmentName,
                    $monthlyFee,  // Map from department name
                    'card',  // Default payment type (manual payment)
                    $validFromFormatted,
                    $validUntilFormatted,
                    $status,
                    $notes,
                    $isDraft
                ]);

                $newId = $pdo->lastInsertId();
                if ($config['debug']) {
                    logMessage("Inserted new member #{$newId}: {$firstName} {$lastName} [{$departmentName}] @ \${$monthlyFee}/mo");
                }
                $inserted++;
            }

            $synced++;

        } catch (Exception $e) {
            logMessage("ERROR syncing user {$firstName} {$lastName}: " . $e->getMessage(), true);
            $errors++;
        }
    }

    logMessage("Sync complete: {$synced} processed, {$updated} updated, {$inserted} inserted, {$skipped} skipped, {$errors} errors");
}

// ===== MAIN EXECUTION =====

try {
    if ($testMode) {
        $success = testConnection($config);
        exit($success ? 0 : 1);
    }

    if ($discoverMode) {
        discoverEndpoints($config);
        exit(0);
    }

    // Full sync
    syncMembers($config, $dryRun);

} catch (Exception $e) {
    logMessage("FATAL ERROR: " . $e->getMessage(), true);
    exit(1);
}
