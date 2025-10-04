<?php
declare(strict_types=1);

function pdo(): PDO {
  static $pdo;
  if ($pdo) return $pdo;
$dsn  = "mysql:host=127.0.0.1;dbname=ahf;charset=utf8mb4";
$user = "ahf_app";
$pass = "AhfApp@2024!";

  $pdo = new PDO($dsn, $user, $pass, [
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
  ]);
  return $pdo;
}

function respond(array $arr, int $code=200): void {
  http_response_code($code);
  header("Content-Type: application/json");
  echo json_encode($arr);
  exit;
}

function now_utc(): string { return gmdate("c"); }
