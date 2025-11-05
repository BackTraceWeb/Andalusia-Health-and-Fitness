<?php
/**
 * Admin Password Reset & Diagnostic Tool
 * Upload this to your server and visit it directly to reset admin password
 * DELETE THIS FILE after use for security
 */

require_once __DIR__ . '/../_bootstrap.php';

$message = '';
$currentHash = config('ADMIN_PASS_HASH', '');
$configFile = __DIR__ . '/../config/.env.php';

// Test common passwords against current hash
$testPasswords = ['fit2025!', 'admin', 'password', 'fit2024!'];
$matchedPassword = null;

foreach ($testPasswords as $testPass) {
    if (password_verify($testPass, $currentHash)) {
        $matchedPassword = $testPass;
        break;
    }
}

// Handle password reset
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newPassword = $_POST['new_password'] ?? '';

    if (strlen($newPassword) >= 6) {
        $newHash = password_hash($newPassword, PASSWORD_BCRYPT);

        // Show the hash to manually update config file
        $message = "✅ New password hash generated!\n\n";
        $message .= "Password: " . htmlspecialchars($newPassword) . "\n\n";
        $message .= "Copy this hash and update config/.env.php:\n\n";
        $message .= "'ADMIN_PASS_HASH' => '$newHash',\n\n";
        $message .= "Then delete this file (reset-admin-password.php) for security.";
    } else {
        $message = "❌ Password must be at least 6 characters";
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Admin Password Reset</title>
<style>
body {
    background: #000; color: #fff; font-family: monospace;
    padding: 2rem; max-width: 800px; margin: 0 auto;
}
h1 { color: #d81b60; }
.info-box {
    background: #1a1a1a; border: 1px solid #333;
    padding: 1.5rem; border-radius: 8px; margin: 1rem 0;
}
.success { border-color: #4caf50; background: #1b4d1b; }
.warning { border-color: #ff9800; background: #4d3b1b; }
.error { border-color: #f44336; background: #4d1b1b; }
input, button {
    display: block; width: 100%; padding: 0.75rem;
    margin: 0.5rem 0; border-radius: 6px; border: 1px solid #333;
    background: #0a0a0a; color: #fff; font-size: 1rem;
}
button {
    background: #d81b60; border-color: #d81b60;
    cursor: pointer; font-weight: 600;
}
button:hover { background: #e33d7d; }
pre { background: #0a0a0a; padding: 1rem; border-radius: 4px; overflow-x: auto; }
</style>
</head>
<body>

<h1>🔐 Admin Password Diagnostic & Reset</h1>

<div class="info-box warning">
    <strong>⚠️ SECURITY WARNING</strong><br>
    Delete this file after use! It exposes password information.
</div>

<div class="info-box">
    <h3>Current Configuration</h3>
    <p><strong>Username:</strong> <?= htmlspecialchars(config('ADMIN_USER', 'admin')) ?></p>
    <p><strong>Password Hash:</strong> <code><?= htmlspecialchars(substr($currentHash, 0, 30)) ?>...</code></p>

    <?php if ($matchedPassword): ?>
        <div class="info-box success">
            <strong>✅ Current password detected:</strong> <?= htmlspecialchars($matchedPassword) ?>
        </div>
    <?php else: ?>
        <div class="info-box error">
            <strong>❌ Current password unknown</strong><br>
            None of the common passwords match the hash in config.<br>
            Use the form below to set a new password.
        </div>
    <?php endif; ?>
</div>

<?php if ($message): ?>
    <div class="info-box success">
        <pre><?= htmlspecialchars($message) ?></pre>
    </div>
<?php endif; ?>

<div class="info-box">
    <h3>Reset Admin Password</h3>
    <form method="POST">
        <label><strong>New Password:</strong></label>
        <input type="text" name="new_password" placeholder="Enter new password" required minlength="6">
        <button type="submit">Generate New Hash</button>
    </form>
</div>

<div class="info-box">
    <h3>Debugging Info</h3>
    <p><strong>Config file:</strong> <?= $configFile ?></p>
    <p><strong>File exists:</strong> <?= file_exists($configFile) ? '✅ Yes' : '❌ No' ?></p>
    <p><strong>File readable:</strong> <?= is_readable($configFile) ? '✅ Yes' : '❌ No' ?></p>
</div>

<div class="info-box warning">
    <strong>🗑️ Remember to DELETE this file after fixing the password!</strong>
</div>

</body>
</html>
