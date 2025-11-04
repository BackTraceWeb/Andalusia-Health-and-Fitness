<?php
// _bootstrap.php
// Central DB bootstrap for Andalusia Health & Fitness

/**
 * Load environment configuration
 */
function config($key = null, $default = null) {
    static $config;

    if ($config === null) {
        $configFile = __DIR__ . '/config/.env.php';
        if (file_exists($configFile)) {
            $config = require $configFile;
        } else {
            // Fallback to environment variables if config file doesn't exist
            $config = [];
            error_log("Warning: config/.env.php not found. Using environment variables or defaults.");
        }
    }

    if ($key === null) {
        return $config;
    }

    // Check config array first, then environment variables, then default
    if (isset($config[$key])) {
        return $config[$key];
    }

    $envValue = getenv($key);
    return $envValue !== false ? $envValue : $default;
}

// Apply error reporting from config
error_reporting(config('ERROR_REPORTING', E_ALL));
ini_set('display_errors', config('DISPLAY_ERRORS', 0) ? '1' : '0');

/**
 * Global PDO helper
 */
function pdo(): PDO {
    static $pdo;
    if ($pdo) return $pdo;

    $dsn  = config('DB_DSN', 'mysql:host=127.0.0.1;dbname=ahf;charset=utf8mb4');
    $user = config('DB_USER', 'ahf_web');
    $pass = config('DB_PASS');

    if (!$pass) {
        error_log("CRITICAL: Database password not configured in config/.env.php");
        http_response_code(500);
        echo json_encode(["error" => "configuration_error"]);
        exit;
    }

    try {
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    } catch (PDOException $e) {
        error_log("DB connect failed: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(["error" => "db_connection_failed"]);
        exit;
    }

    return $pdo;
}

// Initialize and make $pdo globally available
$pdo = pdo();
