<?php
/**
 * AxTrax Pro API Configuration
 *
 * SECURITY: This file contains sensitive credentials.
 * DO NOT commit this file to git.
 *
 * To set up:
 * 1. SSH to server: ssh -i ~/.ssh/BTS1.pem ubuntu@3.12.72.81
 * 2. Edit this file: sudo nano /var/www/andalusiahealthandfitness/api/axtrax-config.php
 * 3. Replace PLACEHOLDER values with real credentials
 * 4. Save and set permissions: sudo chmod 600 /var/www/andalusiahealthandfitness/api/axtrax-config.php
 */

return [
    // AxTrax Pro REST API base URL (including port)
    'base_url' => 'http://192.168.1.128:8080',

    // OAuth2 credentials for API authentication
    'oauth_username' => 'ahf@ahf.com',
    'oauth_password' => 'Fit2025!',

    // API timeout in seconds
    'timeout' => 30,

    // Enable debug logging (disable in production)
    'debug' => false,

    // Log file location
    'log_file' => '/var/log/axtrax-sync.log',
];
