<?php
/**
 * Add is_draft and notes fields to members table
 */
declare(strict_types=1);

require_once __DIR__ . '/../_bootstrap.php';

$pdo = pdo();
if (!$pdo) {
    die("ERROR: Database connection failed\n");
}

echo "Checking members table structure...\n";

// Check if fields already exist
$stmt = $pdo->query("SHOW COLUMNS FROM members LIKE 'is_draft'");
$hasDraftField = $stmt->rowCount() > 0;

$stmt = $pdo->query("SHOW COLUMNS FROM members LIKE 'notes'");
$hasNotesField = $stmt->rowCount() > 0;

if ($hasDraftField && $hasNotesField) {
    echo "✓ Both is_draft and notes fields already exist\n";
    exit(0);
}

// Add is_draft field
if (!$hasDraftField) {
    echo "Adding is_draft field...\n";
    $pdo->exec("
        ALTER TABLE members
        ADD COLUMN is_draft TINYINT(1) DEFAULT 0
        COMMENT 'Whether member is on bank draft (automatic payment)'
    ");
    echo "✓ Added is_draft field\n";
} else {
    echo "✓ is_draft field already exists\n";
}

// Add notes field
if (!$hasNotesField) {
    echo "Adding notes field...\n";
    $pdo->exec("
        ALTER TABLE members
        ADD COLUMN notes TEXT NULL
        COMMENT 'Notes from AxTrax (tNotes field)'
    ");
    echo "✓ Added notes field\n";
} else {
    echo "✓ notes field already exists\n";
}

echo "\nDone! Fields added successfully.\n";
