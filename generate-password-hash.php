<?php
/**
 * Password Hash Generator
 *
 * Usage:
 *   php generate-password-hash.php your_password_here
 *
 * Or run interactively:
 *   php generate-password-hash.php
 */

// Check if password provided as argument
if (isset($argv[1])) {
    $password = $argv[1];
} else {
    // Interactive mode
    echo "===========================================\n";
    echo "  Admin Password Hash Generator\n";
    echo "===========================================\n\n";
    echo "Enter password to hash: ";
    $password = trim(fgets(STDIN));
}

if (empty($password)) {
    echo "Error: Password cannot be empty\n";
    exit(1);
}

// Generate hash
$hash = password_hash($password, PASSWORD_BCRYPT);

echo "\n===========================================\n";
echo "  Your Password Hash\n";
echo "===========================================\n\n";
echo "Password: " . str_repeat('*', strlen($password)) . "\n";
echo "Hash:     $hash\n\n";
echo "===========================================\n";
echo "  Next Steps\n";
echo "===========================================\n";
echo "1. Copy the hash above\n";
echo "2. Edit config/.env.php\n";
echo "3. Update ADMIN_PASS_HASH with this value:\n\n";
echo "   'ADMIN_PASS_HASH' => '$hash',\n\n";
echo "4. Save the file\n";
echo "5. Test login with your new password\n";
echo "===========================================\n\n";

// Verify the hash works
if (password_verify($password, $hash)) {
    echo "✓ Hash verification successful!\n";
} else {
    echo "✗ Warning: Hash verification failed!\n";
}

echo "\n";
