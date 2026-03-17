<?php
/**
 * Add allow_quickpay field to members table
 * This allows staff to enable manual QuickPay for draft members when automatic payment fails
 */
declare(strict_types=1);

require_once __DIR__ . '/../_bootstrap.php';

$pdo = pdo();
if (!$pdo) {
    die("ERROR: Database connection failed\n");
}

echo "Checking members table structure...\n";

// Check if field already exists
$stmt = $pdo->query("SHOW COLUMNS FROM members LIKE 'allow_quickpay'");
$hasField = $stmt->rowCount() > 0;

if ($hasField) {
    echo "✓ allow_quickpay field already exists\n";
    exit(0);
}

// Add allow_quickpay field
echo "Adding allow_quickpay field...\n";
$pdo->exec("
    ALTER TABLE members
    ADD COLUMN allow_quickpay TINYINT(1) DEFAULT 0
    COMMENT 'Allow QuickPay for draft members when automatic payment fails (staff toggle)'
");
echo "✓ Added allow_quickpay field\n";

echo "\nDone! Staff can now enable QuickPay for draft members when needed.\n";
