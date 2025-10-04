<?php
// /api/_bootstrap.php

error_reporting(E_ALL);
ini_set('display_errors', 1);

function pdo(): PDO {
  static $pdo;
  if ($pdo) return $pdo;

  $dsn  = getenv('DB_DSN') ?: 'mysql:host=127.0.0.1;dbname=ahf;charset=utf8mb4';
  $user = getenv('DB_USER') ?: 'ahf_app';
  $pass = getenv('DB_PASS') ?: 'AhfApp@2024!'; // ✅ ensure this matches your MariaDB password exactly

  try {
    $pdo = new PDO($dsn, $user, $pass, [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
  } catch (PDOException $e) {
    // 🔍 Add debug to a local log file so we can confirm what PHP is using
    $log = __DIR__ . '/../../logs/db-connect-debug.log';
    file_put_contents($log, date('c') . " - DB connect failed\nUser: $user\nPass: $pass\nDSN: $dsn\nError: " . $e->getMessage() . "\n\n", FILE_APPEND);
    throw $e;
  }

  return $pdo;
}

