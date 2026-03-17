<?php
/**
 * Extend membership by name
 */

declare(strict_types=1);

require __DIR__ . '/axtrax-config.php';
require __DIR__ . '/axtrax-helpers.php';

$config = require __DIR__ . '/axtrax-config.php';

$firstName = $argv[1] ?? 'Brady';
$lastName = $argv[2] ?? 'Raines';
$days = isset($argv[3]) ? (int)$argv[3] : 30;

echo "Extending membership for: {$firstName} {$lastName}\n";
echo "Extension: {$days} days\n\n";

$success = axtraxExtendMembership('', $firstName, $lastName, $days, $config);

if ($success) {
    echo "\n✅ SUCCESS! Membership extended by {$days} days\n";
    echo "Card access should be ACTIVE now. Try your fob!\n";
} else {
    echo "\n❌ FAILED to extend membership\n";
}
