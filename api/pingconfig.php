<?php
header('Content-Type: application/json');
$config = require __DIR__ . '/../config/payments.php';
echo json_encode([
  'epn_account'      => $config['epn_account'],
  'restrict_key_set' => (bool)$config['restrict_key'],
  'ts'               => gmdate('c'),
]);
