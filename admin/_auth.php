<?php
/**
 * Admin Authentication Helper
 * Include this at the top of any admin page that requires authentication
 */

// Load configuration
require_once __DIR__ . '/../_bootstrap.php';

// Add security headers
require_once __DIR__ . '/../_security_headers.php';

// Configure secure session settings
ini_set('session.cookie_httponly', config('SESSION_COOKIE_HTTPONLY', 1));
ini_set('session.cookie_secure', config('SESSION_COOKIE_SECURE', 0)); // Set to 1 for HTTPS
ini_set('session.cookie_samesite', config('SESSION_COOKIE_SAMESITE', 'Strict'));
ini_set('session.use_strict_mode', 1);
ini_set('session.gc_maxlifetime', config('SESSION_LIFETIME', 7200));

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Session timeout check
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > config('SESSION_LIFETIME', 7200))) {
    session_unset();
    session_destroy();
    header('Location: index.php?timeout=1');
    exit;
}
$_SESSION['last_activity'] = time();

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: index.php');
    exit;
}

// CSRF Token Generation
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/**
 * Generate CSRF token field for forms
 */
function csrf_field() {
    $token = $_SESSION['csrf_token'] ?? '';
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Verify CSRF token from POST request
 */
function verify_csrf() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? '';
        if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
            http_response_code(403);
            die('CSRF token validation failed');
        }
    }
}
