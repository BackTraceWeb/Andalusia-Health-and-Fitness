<?php
/**
 * Make a user's membership DUE (for testing)
 * Sets dtStopDate to yesterday
 */

declare(strict_types=1);

require __DIR__ . '/axtrax-config.php';
require __DIR__ . '/axtrax-helpers.php';

$config = require __DIR__ . '/axtrax-config.php';

$firstName = $argv[1] ?? 'Brady';
$lastName = $argv[2] ?? 'Raines';

echo "Finding user: {$firstName} {$lastName}\n";

// Find user
$user = axtraxFindUser('', $firstName, $lastName, $config);

if (!$user) {
    die("ERROR: User not found\n");
}

echo "✓ Found user: {$user['tFirstName']} {$user['tLastName']} (ID: {$user['ID']})\n";
echo "  Current stop date: " . ($user['dtStopDate'] ?? 'None') . "\n\n";

// Set to yesterday
$yesterday = new DateTime('yesterday');
$yesterday->setTime(23, 59, 0);
$user['dtStopDate'] = $yesterday->format('Y-m-d\TH:i:s');

echo "Setting stop date to: {$user['dtStopDate']}\n";

// Update
$response = axtraxApiRequest('PUT', '/api/User/UpdateUser', $config, $user);

if ($response === null) {
    die("ERROR: Failed to update\n");
}

if (isset($response['Errors']) && !empty($response['Errors'])) {
    echo "Errors: " . json_encode($response['Errors']) . "\n";
    die("ERROR: Update failed\n");
}

echo "✓ SUCCESS: Membership is now DUE (expired yesterday)\n";
echo "  Card access should be disabled now\n";
