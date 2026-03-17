<?php
/**
 * System Status Details API - Returns detailed info for specific checks
 */
declare(strict_types=1);
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/../../_bootstrap.php';
require_once __DIR__ . '/../axtrax-config.php';

$check = $_GET['check'] ?? '';

function getPaymentIntentsDetails() {
    $pdo = pdo();
    
    // Get stale payment intents
    $stmt = $pdo->query("
        SELECT 
            pi.id,
            pi.member_id,
            pi.invoice_id,
            pi.created_at,
            pi.processed_at,
            m.first_name,
            m.last_name,
            m.email,
            d.period_start,
            d.period_end,
            d.amount_cents
        FROM payment_intents pi
        LEFT JOIN members m ON pi.member_id = m.id
        LEFT JOIN dues d ON pi.invoice_id = d.id
        WHERE pi.processed_at IS NULL
        AND pi.created_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)
        AND pi.created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ORDER BY pi.created_at DESC
    ");
    
    $staleIntents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return [
        'title' => 'Stale Payment Intents',
        'description' => 'Payment intents created >1 hour ago but not yet processed',
        'count' => count($staleIntents),
        'items' => array_map(function($intent) {
            return [
                'id' => $intent['id'],
                'member' => $intent['first_name'] . ' ' . $intent['last_name'],
                'email' => $intent['email'],
                'amount' => '$' . number_format(($intent['amount_cents'] ?? 0) / 100, 2),
                'created' => $intent['created_at'],
                'age_hours' => round((strtotime('now') - strtotime($intent['created_at'])) / 3600, 1),
                'period' => $intent['period_start'] . ' – ' . $intent['period_end']
            ];
        }, $staleIntents)
    ];
}

function getRecentPaymentsDetails() {
    $pdo = pdo();
    
    $stmt = $pdo->query("
        SELECT 
            pi.id,
            pi.member_id,
            pi.invoice_id,
            pi.created_at,
            pi.processed_at,
            m.first_name,
            m.last_name,
            m.email,
            d.period_start,
            d.period_end,
            d.amount_cents
        FROM payment_intents pi
        LEFT JOIN members m ON pi.member_id = m.id
        LEFT JOIN dues d ON pi.invoice_id = d.id
        WHERE pi.processed_at IS NOT NULL
        AND pi.processed_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ORDER BY pi.processed_at DESC
    ");
    
    $recentPayments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return [
        'title' => 'Recent Payments (Last 24 Hours)',
        'description' => 'Successfully processed payments in the last 24 hours',
        'count' => count($recentPayments),
        'items' => array_map(function($payment) {
            return [
                'id' => $payment['id'],
                'member' => $payment['first_name'] . ' ' . $payment['last_name'],
                'email' => $payment['email'],
                'amount' => '$' . number_format(($payment['amount_cents'] ?? 0) / 100, 2),
                'created' => $payment['created_at'],
                'processed' => $payment['processed_at'],
                'processing_time' => round((strtotime($payment['processed_at']) - strtotime($payment['created_at'])) / 60, 1) . ' min',
                'period' => $payment['period_start'] . ' – ' . $payment['period_end']
            ];
        }, $recentPayments)
    ];
}

function getDatabaseDetails() {
    $pdo = pdo();
    
    // Get database stats
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    $stats = [];
    
    foreach ($tables as $table) {
        $count = $pdo->query("SELECT COUNT(*) FROM $table")->fetchColumn();
        $stats[] = [
            'table' => $table,
            'rows' => number_format($count)
        ];
    }
    
    return [
        'title' => 'Database Connection',
        'description' => 'MySQL database connection status and statistics',
        'status' => 'Connected',
        'tables' => $stats
    ];
}

function getAxTraxApiDetails() {
    $config = require __DIR__ . '/../axtrax-config.php';
    $tokenUrl = rtrim($config['base_url'], '/') . '/token';
    
    $start = microtime(true);
    $ch = curl_init($tokenUrl);
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
    $responseTime = round((microtime(true) - $start) * 1000, 2);
    curl_close($ch);
    
    $tokenData = json_decode($response, true);
    
    return [
        'title' => 'AxTrax API Connection',
        'description' => 'AxTrax Pro API authentication status',
        'endpoint' => $tokenUrl,
        'http_code' => $httpCode,
        'response_time' => $responseTime . ' ms',
        'authenticated' => isset($tokenData['access_token']) ? 'Yes' : 'No',
        'token_type' => $tokenData['token_type'] ?? 'N/A',
        'expires_in' => isset($tokenData['expires_in']) ? $tokenData['expires_in'] . ' seconds' : 'N/A'
    ];
}

function getAxTraxSyncDetails() {
    $pdo = pdo();
    
    // Get recent member updates
    $stmt = $pdo->query("
        SELECT 
            id,
            first_name,
            last_name,
            email,
            status,
            valid_until,
            updated_at
        FROM members
        WHERE updated_at IS NOT NULL
        ORDER BY updated_at DESC
        LIMIT 20
    ");
    
    $recentUpdates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $lastSync = $recentUpdates[0]['updated_at'] ?? null;
    $minutesAgo = $lastSync ? round((time() - strtotime($lastSync)) / 60, 1) : null;
    
    return [
        'title' => 'AxTrax Sync Status',
        'description' => 'Recent member data synchronization with AxTrax Pro',
        'last_sync' => $lastSync,
        'minutes_ago' => $minutesAgo,
        'recent_updates' => array_map(function($member) {
            return [
                'id' => $member['id'],
                'name' => $member['first_name'] . ' ' . $member['last_name'],
                'email' => $member['email'],
                'status' => $member['status'],
                'valid_until' => $member['valid_until'],
                'updated_at' => $member['updated_at']
            ];
        }, $recentUpdates)
    ];
}


function getTailscaleDetails() {
    $config = require __DIR__ . "/../axtrax-config.php";
    $axtraxHost = parse_url($config["base_url"], PHP_URL_HOST);
    
    // Get full Tailscale status
    $tsStatus = shell_exec("sudo tailscale status 2>&1");
    
    // Parse status output into peers
    $lines = explode("\n", trim($tsStatus));
    $peers = [];
    
    foreach ($lines as $line) {
        if (empty($line)) continue;
        
        // Parse line format: IP   hostname   user   os   status
        $parts = preg_split("/\s+/", $line);
        if (count($parts) >= 2) {
            $peers[] = [
                "ip" => $parts[0],
                "hostname" => $parts[1] ?? "Unknown",
                "user" => $parts[2] ?? "",
                "os" => $parts[3] ?? "",
                "status" => implode(" ", array_slice($parts, 4))
            ];
        }
    }
    
    // Test connectivity to AxTrax
    $pingResult = shell_exec("ping -c 3 -W 2 $axtraxHost 2>&1");
    $pingLines = explode("\n", $pingResult);
    $pingStats = "";
    foreach ($pingLines as $line) {
        if (strpos($line, "rtt") !== false || strpos($line, "packets transmitted") !== false) {
            $pingStats .= $line . " | ";
        }
    }
    
    return [
        "title" => "Tailscale VPN Status",
        "description" => "Tailscale mesh VPN connectivity to AxTrax Pro system",
        "axtraxHost" => $axtraxHost,
        "pingTest" => rtrim($pingStats, " | "),
        "peerCount" => count($peers),
        "peers" => $peers
    ];
}

try {
    $details = match($check) {
        'payment_intents' => getPaymentIntentsDetails(),
        'recent_payments' => getRecentPaymentsDetails(),
        'database' => getDatabaseDetails(),
        'axtrax_api' => getAxTraxApiDetails(),
        'axtrax_sync' => getAxTraxSyncDetails(),
        'tailscale' => getTailscaleDetails(),
        default => ['error' => 'Invalid check type']
    };
    
    echo json_encode($details, JSON_PRETTY_PRINT);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()], JSON_PRETTY_PRINT);
}
