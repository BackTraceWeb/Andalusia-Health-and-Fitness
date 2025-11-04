<?php
/**
 * Environment Configuration Template for Andalusia Health & Fitness
 *
 * SETUP INSTRUCTIONS:
 * 1. Copy this file to .env.php in the same directory
 * 2. Fill in your actual credentials in .env.php
 * 3. Never commit .env.php to git (it's already in .gitignore)
 */

return [
    // Database Configuration
    'DB_DSN'  => 'mysql:host=127.0.0.1;dbname=ahf;charset=utf8mb4',
    'DB_USER' => 'your_db_username',
    'DB_PASS' => 'your_db_password',

    // Admin Authentication
    // Generate password hash: php -r "echo password_hash('your_password', PASSWORD_BCRYPT);"
    'ADMIN_USER' => 'admin',
    'ADMIN_PASS_HASH' => 'your_bcrypt_hashed_password',

    // Authorize.Net Configuration
    'AUTH_ENV' => 'SANDBOX', // or 'PROD'
    'AUTH_API_URL' => 'https://apitest.authorize.net/xml/v1/request.api', // or production URL
    'AUTH_LOGIN_ID' => 'your_authorize_net_login_id',
    'AUTH_TRANSACTION_KEY' => 'your_authorize_net_transaction_key',
    'AUTH_SIGNATURE_KEY_HEX' => 'your_128_character_hex_signature_key',

    // Site Configuration
    'SITE_BASE_URL' => 'https://yourdomain.com',

    // Webhook Authentication
    'WEBHOOK_BEARER_TOKEN' => 'generate_a_secure_random_token_here',

    // AxTrax Bridge Authentication
    'AXTRAX_BRIDGE_KEY' => 'your_axtrax_bridge_key',

    // Session Security
    'SESSION_LIFETIME' => 7200, // 2 hours in seconds
    'SESSION_COOKIE_SECURE' => true, // Set to true in production with HTTPS
    'SESSION_COOKIE_HTTPONLY' => true,
    'SESSION_COOKIE_SAMESITE' => 'Strict',

    // Security
    'DISPLAY_ERRORS' => false, // Set to false in production
    'ERROR_REPORTING' => E_ALL,
    'RATE_LIMIT_ENABLED' => true,
    'RATE_LIMIT_REQUESTS' => 60, // requests per window
    'RATE_LIMIT_WINDOW' => 60, // seconds
];\n
