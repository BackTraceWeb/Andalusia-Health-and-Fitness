<?php
/**
 * AHF System Health Check & Monitoring
 *
 * Monitors:
 * - Tailscale connectivity to AxTrax Pro
 * - AxTrax Pro API availability
 * - Database connectivity
 * - Webhook/API failure logs
 *
 * Run via cron every 15 minutes:
 * Example crontab entry:
 * - - /15 * * * * php /var/www/andalusiahealthandfitness/api/monitoring/health-check.php
 */

declare(strict_types=1);

require __DIR__ . '/../../_bootstrap.php';
require __DIR__ . '/../axtrax-config.php';
require __DIR__ . '/../axtrax-helpers.php';

// Configuration
const ALERT_EMAIL = 'brady@back-trace.com';
const STATE_FILE = __DIR__ . '/health-state.json';
const ALERT_COOLDOWN = 3600; // Don't spam alerts - 1 hour between duplicate alerts

// Load previous state
function loadState(): array {
    if (!file_exists(STATE_FILE)) {
        return [
            'last_alerts' => [],
            'consecutive_failures' => []
        ];
    }
    return json_decode(file_get_contents(STATE_FILE), true) ?: [];
}

// Save state
function saveState(array $state): void {
    file_put_contents(STATE_FILE, json_encode($state, JSON_PRETTY_PRINT));
}

// Check if we should send alert (cooldown logic)
function shouldAlert(string $alertKey, array &$state): bool {
    $now = time();
    $lastAlert = $state['last_alerts'][$alertKey] ?? 0;

    if ($now - $lastAlert < ALERT_COOLDOWN) {
        return false; // Still in cooldown
    }

    $state['last_alerts'][$alertKey] = $now;
    return true;
}

// Send email alert using Microsoft Graph
function sendAlert(string $subject, string $body): bool {
    try {
        require __DIR__ . '/../../_bootstrap.php';
        require __DIR__ . '/../../vendor/autoload.php';

        $graphEmail = config('GRAPH_EMAIL_FROM');
        $graphTenantId = config('GRAPH_TENANT_ID');
        $graphClientId = config('GRAPH_CLIENT_ID');
        $graphClientSecret = config('GRAPH_CLIENT_SECRET');

        if (!$graphEmail || !$graphTenantId || !$graphClientId || !$graphClientSecret) {
            error_log("Monitoring alert: Email not configured");
            return false;
        }

        // Get access token
        $tokenUrl = "https://login.microsoftonline.com/{$graphTenantId}/oauth2/v2.0/token";
        $tokenData = http_build_query([
            'client_id' => $graphClientId,
            'client_secret' => $graphClientSecret,
            'scope' => 'https://graph.microsoft.com/.default',
            'grant_type' => 'client_credentials'
        ]);

        $ch = curl_init($tokenUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $tokenData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            error_log("Monitoring: Failed to get Graph token: HTTP {$httpCode}");
            return false;
        }

        $tokenResponse = json_decode($response, true);
        $accessToken = $tokenResponse['access_token'] ?? null;

        if (!$accessToken) {
            error_log("Monitoring: No access token in response");
            return false;
        }

        // Send email via Graph API
        $emailData = [
            'message' => [
                'subject' => $subject,
                'body' => [
                    'contentType' => 'HTML',
                    'content' => $body
                ],
                'toRecipients' => [
                    ['emailAddress' => ['address' => ALERT_EMAIL]]
                ]
            ],
            'saveToSentItems' => false
        ];

        $ch = curl_init("https://graph.microsoft.com/v1.0/users/{$graphEmail}/sendMail");
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($emailData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json'
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 202) {
            error_log("Monitoring: Failed to send email: HTTP {$httpCode} - {$response}");
            return false;
        }

        return true;

    } catch (Exception $e) {
        error_log("Monitoring: Email exception - " . $e->getMessage());
        return false;
    }
}

// Main monitoring checks
function runHealthChecks(): array {
    $results = [];
    $config = require __DIR__ . '/../axtrax-config.php';

    // 1. Check Tailscale connectivity to AxTrax Pro
    echo "[" . date('Y-m-d H:i:s') . "] Checking Tailscale connectivity to AxTrax Pro...\n";

    $axtraxHost = parse_url($config['base_url'], PHP_URL_HOST);
    $axtraxPort = parse_url($config['base_url'], PHP_URL_PORT) ?: 8080;

    // Test TCP connection
    $connection = @fsockopen($axtraxHost, $axtraxPort, $errno, $errstr, 5);

    if ($connection === false) {
        $results['tailscale'] = [
            'status' => 'FAIL',
            'message' => "Cannot connect to AxTrax Pro at {$axtraxHost}:{$axtraxPort} via Tailscale",
            'error' => "{$errno}: {$errstr}"
        ];
    } else {
        fclose($connection);
        $results['tailscale'] = [
            'status' => 'OK',
            'message' => "Tailscale connection to AxTrax Pro is active"
        ];
    }

    // 2. Check AxTrax Pro API authentication
    echo "[" . date('Y-m-d H:i:s') . "] Checking AxTrax Pro API...\n";

    if ($results['tailscale']['status'] === 'OK') {
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
            CURLOPT_TIMEOUT => 10,
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

        if ($error) {
            $results['axtrax_api'] = [
                'status' => 'FAIL',
                'message' => "AxTrax Pro API authentication failed",
                'error' => $error
            ];
        } elseif ($httpCode !== 200) {
            $results['axtrax_api'] = [
                'status' => 'FAIL',
                'message' => "AxTrax Pro API returned HTTP {$httpCode}",
                'response' => substr($response, 0, 200)
            ];
        } else {
            $data = json_decode($response, true);
            if (isset($data['access_token'])) {
                $results['axtrax_api'] = [
                    'status' => 'OK',
                    'message' => "AxTrax Pro API is responding correctly"
                ];
            } else {
                $results['axtrax_api'] = [
                    'status' => 'FAIL',
                    'message' => "AxTrax Pro API authentication failed - no token received"
                ];
            }
        }
    } else {
        $results['axtrax_api'] = [
            'status' => 'SKIP',
            'message' => "Skipped due to Tailscale connection failure"
        ];
    }

    // 3. Check database connectivity
    echo "[" . date('Y-m-d H:i:s') . "] Checking database connectivity...\n";

    try {
        $pdo = pdo();
        $stmt = $pdo->query("SELECT 1");
        $stmt->fetch();

        $results['database'] = [
            'status' => 'OK',
            'message' => "Database connection is healthy"
        ];
    } catch (Exception $e) {
        $results['database'] = [
            'status' => 'FAIL',
            'message' => "Database connection failed",
            'error' => $e->getMessage()
        ];
    }

    // 4. Check for recent webhook/API failures in logs
    echo "[" . date('Y-m-d H:i:s') . "] Checking for recent errors...\n";

    $logFile = '/var/log/apache2/error.log';
    if (file_exists($logFile)) {
        $recentErrors = shell_exec("tail -n 500 {$logFile} | grep -i 'axtrax\\|webhook\\|quickpay' | grep -i 'error\\|fail' | tail -n 10") ?? '';

        if (!empty(trim($recentErrors))) {
            $results['recent_errors'] = [
                'status' => 'WARN',
                'message' => "Recent errors detected in logs",
                'errors' => trim($recentErrors)
            ];
        } else {
            $results['recent_errors'] = [
                'status' => 'OK',
                'message' => "No recent errors detected"
            ];
        }
    } else {
        $results['recent_errors'] = [
            'status' => 'SKIP',
            'message' => "Log file not accessible"
        ];
    }

    // 5. Check for stale payment intents (QuickPay monitoring)
    echo "[" . date('Y-m-d H:i:s') . "] Checking for stale payment intents...\n";

    try {
        $pdo = pdo();
        $stmt = $pdo->query("
            SELECT COUNT(*) as count
            FROM payment_intents
            WHERE processed_at IS NULL
            AND created_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)
            AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $staleCount = (int)$result['count'];

        if ($staleCount === 0) {
            $results['payment_intents'] = [
                'status' => 'OK',
                'message' => "No stale payment intents"
            ];
        } else if ($staleCount <= 3) {
            $results['payment_intents'] = [
                'status' => 'WARN',
                'message' => "{$staleCount} stale payment intent(s) detected"
            ];
        } else {
            $results['payment_intents'] = [
                'status' => 'FAIL',
                'message' => "{$staleCount} stale payment intents - possible payment processing issue"
            ];
        }
    } catch (Exception $e) {
        $results['payment_intents'] = [
            'status' => 'FAIL',
            'message' => "Failed to check payment intents",
            'error' => $e->getMessage()
        ];
    }

    // 6. Check QuickPay processing errors
    echo "[" . date('Y-m-d H:i:s') . "] Checking QuickPay processing errors...\n";

    if (file_exists($logFile)) {
        $quickpayErrors = shell_exec("tail -n 1000 {$logFile} | grep 'QuickPay' | grep -i 'error\\|failed' | wc -l") ?? '0';
        $errorCount = (int)trim($quickpayErrors);

        if ($errorCount === 0) {
            $results['quickpay_errors'] = [
                'status' => 'OK',
                'message' => "No QuickPay errors detected"
            ];
        } else if ($errorCount <= 5) {
            $results['quickpay_errors'] = [
                'status' => 'WARN',
                'message' => "{$errorCount} QuickPay error(s) in recent logs"
            ];
        } else {
            $results['quickpay_errors'] = [
                'status' => 'FAIL',
                'message' => "{$errorCount} QuickPay errors - critical payment processing issue"
            ];
        }
    } else {
        $results['quickpay_errors'] = [
            'status' => 'SKIP',
            'message' => "Log file not accessible"
        ];
    }

    return $results;
}

// Main execution
echo "=== AHF System Health Check - " . date('Y-m-d H:i:s') . " ===\n\n";

$state = loadState();
$results = runHealthChecks();

// Process results and send alerts
$failures = [];
foreach ($results as $check => $result) {
    echo "\n{$check}: {$result['status']} - {$result['message']}\n";

    if (isset($result['error'])) {
        echo "  Error: {$result['error']}\n";
    }

    if ($result['status'] === 'FAIL') {
        $failures[] = [
            'check' => $check,
            'message' => $result['message'],
            'error' => $result['error'] ?? 'N/A'
        ];
    }
}

// Send alert if there are failures
if (!empty($failures)) {
    $alertKey = md5(json_encode(array_column($failures, 'check')));

    if (shouldAlert($alertKey, $state)) {
        $subject = "🚨 AHF System Alert - " . count($failures) . " Critical Issue(s) Detected";

        $body = "<h2 style='color: #d81b60;'>Andalusia Health & Fitness - System Alert</h2>";
        $body .= "<p><strong>Time:</strong> " . date('Y-m-d H:i:s T') . "</p>";
        $body .= "<p><strong>Critical issues detected:</strong></p>";
        $body .= "<ul>";

        foreach ($failures as $failure) {
            $body .= "<li><strong>{$failure['check']}:</strong> {$failure['message']}<br>";
            $body .= "<small style='color: #666;'>Error: {$failure['error']}</small></li>";
        }

        $body .= "</ul>";
        $body .= "<hr>";
        $body .= "<h3>All Check Results:</h3><ul>";

        foreach ($results as $check => $result) {
            $icon = $result['status'] === 'OK' ? '✅' : ($result['status'] === 'FAIL' ? '❌' : '⚠️');
            $body .= "<li>{$icon} <strong>{$check}:</strong> {$result['message']}</li>";
        }

        $body .= "</ul>";
        $body .= "<p><small>This is an automated alert from the AHF monitoring system.</small></p>";

        echo "\n\n🚨 SENDING ALERT EMAIL...\n";

        if (sendAlert($subject, $body)) {
            echo "✅ Alert email sent successfully to " . ALERT_EMAIL . "\n";
        } else {
            echo "❌ Failed to send alert email\n";
        }
    } else {
        echo "\n⏳ Alert suppressed (cooldown period active)\n";
    }
}

// Save state
saveState($state);

echo "\n=== Health Check Complete ===\n";

// Exit with status code
exit(empty($failures) ? 0 : 1);
