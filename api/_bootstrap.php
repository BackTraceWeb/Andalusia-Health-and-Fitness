<?php
// /api/_bootstrap.php
declare(strict_types=1);

if (!defined('AHF_BOOTSTRAP')) {
  define('AHF_BOOTSTRAP', true);
}

// Load config
$config = [
  'db' => [
    'dsn'  => getenv('DB_DSN')  ?: 'mysql:host=127.0.0.1;dbname=ahf;charset=utf8mb4',
    'user' => getenv('DB_USER') ?: 'ahf_app',
    'pass' => getenv('DB_PASS') ?: '*116B825C596B0DD18D3A6F7D5DF0E66BF3A250AE', // update to your real password
  ]
];

// Create PDO connection globally
try {
  $pdo = new PDO(
    $config['db']['dsn'],
    $config['db']['user'],
    $config['db']['pass'],
    [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::ATTR_EMULATE_PREPARES => false,
    ]
  );
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['error'=>'db_connect_failed','detail'=>$e->getMessage()]);
  exit;
}

// Make PDO available to other includes
return ['pdo' => $pdo];

