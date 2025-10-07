<?php
// _bootstrap.php
// Central DB bootstrap for Andalusia Health & Fitness

error_reporting(E_ALL);
ini_set('display_errors', 1);

/**
 * Global PDO helper
 */
function pdo(): PDO {
    static $pdo;
    if ($pdo) return $pdo;

    $dsn  = getenv('DB_DSN') ?: 'mysql:host=127.0.0.1;dbname=ahf;charset=utf8mb4';
    $user = getenv('DB_USER') ?: 'ahf_web';
    $pass = getenv('DB_PASS') ?: 'AhfWeb@2024!';

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
