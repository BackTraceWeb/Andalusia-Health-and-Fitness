<?php
/**
 * System Status API - Returns current health in JSON format
 */
declare(strict_types=1);
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../../_bootstrap.php';
require_once __DIR__ . '/../axtrax-config.php';

$checks = [];
$overallHealthy = true;

// 1. Database
try {
    $pdo = pdo();
    $pdo->query('SELECT 1');
    $checks['database'] = ['status' => 'healthy', 'message' => 'Connected'];
} catch (Exception $e) {
    $checks['database'] = ['status' => 'critical', 'message' => $e->getMessage()];
    $overallHealthy = false;
}

// 2. AxTrax API
try {
    $config = require __DIR__ . '/../axtrax-config.php';
    $ch = curl_init(rtrim($config['base_url'], '/') . '/token');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_TIMEOUT => 5,
        CURLOPT_POSTFIELDS => http_build_query([
            'grant_type' => 'password',
            'username' => $config['oauth_username'],
            'password' => $config['oauth_password']
        ])
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200 && $response) {
        $token_data = json_decode($response, true);
        $checks['axtrax_api'] = isset($token_data['access_token']) 
            ? ['status' => 'healthy', 'message' => 'API responding']
            : ['status' => 'warning', 'message' => 'Invalid response'];
    } else {
        $checks['axtrax_api'] = ['status' => 'critical', 'message' => "HTTP $httpCode"];
        $overallHealthy = false;
    }
} catch (Exception $e) {
    $checks['axtrax_api'] = ['status' => 'critical', 'message' => $e->getMessage()];
    $overallHealthy = false;
}

// 3. Stale Payment Intents
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
        $checks['payment_intents'] = ['status' => 'healthy', 'message' => 'No stale intents'];
    } else if ($staleCount <= 3) {
        $checks['payment_intents'] = ['status' => 'warning', 'message' => "$staleCount stale intent(s)"];
    } else {
        $checks['payment_intents'] = ['status' => 'critical', 'message' => "$staleCount stale intents"];
        $overallHealthy = false;
    }
} catch (Exception $e) {
    $checks['payment_intents'] = ['status' => 'critical', 'message' => $e->getMessage()];
    $overallHealthy = false;
}

// 4. Recent QuickPay Payments (last 24 hours)
try {
    $pdo = pdo();
    $stmt = $pdo->query("
        SELECT COUNT(*) as count
        FROM payment_intents
        WHERE processed_at IS NOT NULL
        AND processed_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $count24h = (int)$result['count'];

    $checks['recent_quickpays'] = [
        'status' => 'info',
        'message' => "$count24h QuickPay payment(s) in last 24 hours"
    ];
} catch (Exception $e) {
    $checks['recent_quickpays'] = ['status' => 'warning', 'message' => 'Unable to fetch'];
}

// 5. Recent New Signups (last 24 hours)
try {
    $pdo = pdo();
    $stmt = $pdo->query("
        SELECT COUNT(*) as count
        FROM pending_signups
        WHERE status = 'completed'
        AND completed_at IS NOT NULL
        AND completed_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $signupCount = (int)$result['count'];

    $checks['recent_signups'] = [
        'status' => 'info',
        'message' => "$signupCount new signup(s) in last 24 hours"
    ];
} catch (Exception $e) {
    $checks['recent_signups'] = ['status' => 'warning', 'message' => 'Unable to fetch'];
}

// 6. Abandoned Signups (pending for > 15 minutes)
try {
    $pdo = pdo();
    $stmt = $pdo->query("
        SELECT COUNT(*) as count
        FROM pending_signups
        WHERE status = 'pending'
        AND created_at < DATE_SUB(NOW(), INTERVAL 15 MINUTE)
        AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $abandonedCount = (int)$result['count'];

    if ($abandonedCount === 0) {
        $checks['abandoned_signups'] = [
            'status' => 'healthy',
            'message' => 'No abandoned signups'
        ];
    } else {
        $checks['abandoned_signups'] = [
            'status' => 'warning',
            'message' => "$abandonedCount abandoned signup(s) - check admin panel"
        ];
    }
} catch (Exception $e) {
    $checks['abandoned_signups'] = ['status' => 'warning', 'message' => 'Unable to fetch'];
}

// 7. AxTrax Sync Status
try {
    $pdo = pdo();
    $stmt = $pdo->query("
        SELECT MAX(updated_at) as last_sync
        FROM members
        WHERE updated_at IS NOT NULL
    ");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result['last_sync']) {
        $lastSync = strtotime($result['last_sync']);
        $minutesAgo = (time() - $lastSync) / 60;

        if ($minutesAgo <= 20) {
            $checks['axtrax_sync'] = ['status' => 'healthy', 'message' => round($minutesAgo) . ' min ago'];
        } else if ($minutesAgo <= 60) {
            $checks['axtrax_sync'] = ['status' => 'warning', 'message' => round($minutesAgo) . ' min ago'];
        } else {
            $checks['axtrax_sync'] = ['status' => 'critical', 'message' => round($minutesAgo / 60, 1) . ' hours ago'];
            $overallHealthy = false;
        }
    } else {
        $checks['axtrax_sync'] = ['status' => 'warning', 'message' => 'No recent syncs'];
    }
} catch (Exception $e) {
    $checks['axtrax_sync'] = ['status' => 'critical', 'message' => $e->getMessage()];
    $overallHealthy = false;
}

$response = [
    'status' => $overallHealthy ? 'healthy' : 'unhealthy',
    'timestamp' => date('c'),
    'checks' => $checks
];

http_response_code($overallHealthy ? 200 : 503);
echo json_encode($response, JSON_PRETTY_PRINT);
