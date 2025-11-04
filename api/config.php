<?php
// Authorize.Net Configuration
// Load from centralized config file

require_once __DIR__ . '/../_bootstrap.php';

// Define constants from config for backward compatibility
define('AUTH_ENV', config('AUTH_ENV', 'SANDBOX'));
define('AUTH_API_URL', config('AUTH_API_URL', 'https://apitest.authorize.net/xml/v1/request.api'));
define('AUTH_LOGIN_ID', config('AUTH_LOGIN_ID'));
define('AUTH_TRANSACTION_KEY', config('AUTH_TRANSACTION_KEY'));
define('AUTH_SIGNATURE_KEY_HEX', config('AUTH_SIGNATURE_KEY_HEX'));
define('SITE_BASE_URL', config('SITE_BASE_URL', 'https://andalusiahealthandfitness.com'));

// Validate required config
if (!AUTH_LOGIN_ID || !AUTH_TRANSACTION_KEY) {
    error_log("CRITICAL: Authorize.Net credentials not configured in config/.env.php");
    http_response_code(500);
    echo json_encode(["error" => "configuration_error"]);
    exit;
}


