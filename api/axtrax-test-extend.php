<?php
/**
 * Test script to extend a membership in AxTrax Pro
 *
 * Usage: php axtrax-test-extend.php email@example.com [days]
 */

declare(strict_types=1);

// Load configuration
$configFile = __DIR__ . '/axtrax-config.php';
if (!file_exists($configFile)) {
    die("ERROR: Config file not found at {$configFile}\n");
}

$config = require $configFile;

// Load helper functions
require __DIR__ . '/axtrax-helpers.php';

// Get command line arguments
$email = $argv[1] ?? null;
$days = isset($argv[2]) ? (int)$argv[2] : 30;

if (!$email) {
    die("Usage: php axtrax-test-extend.php email@example.com [days]\n");
}

echo "Testing membership extension for: {$email}\n";
echo "Extending by: {$days} days\n\n";

// Find user first
echo "Step 1: Finding user in AxTrax Pro...\n";
$user = axtraxFindUser($email, null, null, $config);

if ($user === null) {
    die("ERROR: User not found with email: {$email}\n");
}

echo "✓ Found user: {$user['tFirstName']} {$user['tLastName']} (ID: {$user['ID']})\n";
echo "  Current stop date: " . ($user['dtStopDate'] ?? 'None') . "\n\n";

// Extend membership
echo "Step 2: Extending membership by {$days} days...\n";
$success = axtraxExtendMembership($email, null, null, $days, $config);

if (!$success) {
    die("ERROR: Failed to extend membership\n");
}

echo "✓ SUCCESS: Membership extended!\n\n";

// Verify the update
echo "Step 3: Verifying update...\n";
$updatedUser = axtraxFindUser($email, null, null, $config);

if ($updatedUser) {
    echo "✓ New stop date: {$updatedUser['dtStopDate']}\n";
    echo "\n✅ Test completed successfully!\n";
} else {
    echo "⚠ Could not verify update (but request succeeded)\n";
}
