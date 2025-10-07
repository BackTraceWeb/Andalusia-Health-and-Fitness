<?php
// Database configuration
$DB_HOST = 'localhost';
$DB_USER = 'ahf_web';
$DB_PASS = 'AhfWeb@2024!';
$DB_NAME = 'ahf';

// Create connection
$conn = new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME);
if ($conn->connect_error) {
  die("Database connection failed: " . $conn->connect_error);
}
?>
