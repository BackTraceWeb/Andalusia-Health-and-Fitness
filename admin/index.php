<?php
// Load configuration
require_once __DIR__ . '/../_bootstrap.php';

// Configure secure session settings
ini_set('session.cookie_httponly', config('SESSION_COOKIE_HTTPONLY', 1));
ini_set('session.cookie_secure', config('SESSION_COOKIE_SECURE', 0)); // Set to 1 for HTTPS
ini_set('session.cookie_samesite', config('SESSION_COOKIE_SAMESITE', 'Strict'));
ini_set('session.use_strict_mode', 1);
ini_set('session.gc_maxlifetime', config('SESSION_LIFETIME', 7200));

session_start();

// Session timeout check
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > config('SESSION_LIFETIME', 7200))) {
    session_unset();
    session_destroy();
    session_start();
}
$_SESSION['last_activity'] = time();

// Rate limiting for login attempts
$max_attempts = 5;
$lockout_time = 900; // 15 minutes

if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
    $_SESSION['last_attempt_time'] = time();
}

// Reset counter if lockout time has passed
if (time() - $_SESSION['last_attempt_time'] > $lockout_time) {
    $_SESSION['login_attempts'] = 0;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if locked out
    if ($_SESSION['login_attempts'] >= $max_attempts) {
        $remaining_time = $lockout_time - (time() - $_SESSION['last_attempt_time']);
        $error = 'Too many failed attempts. Please try again in ' . ceil($remaining_time / 60) . ' minutes.';
    } else {
        $user = trim($_POST['username'] ?? '');
        $pass = trim($_POST['password'] ?? '');

        $ADMIN_USER = config('ADMIN_USER', 'admin');
        $ADMIN_PASS = config('ADMIN_PASS', 'admin123');  // Changed to plaintext password

        // Verify credentials (no hashing - using plaintext comparison)
        if ($user === $ADMIN_USER && $pass === $ADMIN_PASS) {
            // Regenerate session ID to prevent session fixation
            session_regenerate_id(true);

            $_SESSION['logged_in'] = true;
            $_SESSION['username'] = $user;
            $_SESSION['login_time'] = time();
            $_SESSION['login_attempts'] = 0; // Reset on successful login

            header('Location: dashboard.php');
            exit;
        } else {
            $_SESSION['login_attempts']++;
            $_SESSION['last_attempt_time'] = time();
            $error = 'Invalid username or password.';

            // Log failed attempt
            error_log("Failed login attempt for user: $user from IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Admin Login — AHF</title>
  <link rel="stylesheet" href="admin.css">
  <style>
    body {
      background: #000;
      color: #fff;
      font-family: 'Inter', sans-serif;
      display: flex;
      justify-content: center;
      align-items: center;
      height: 100vh;
      margin: 0;
    }
    .login-box {
      background: #111;
      border: 1px solid #d81b60;
      border-radius: 20px;
      padding: 2rem 3rem;
      text-align: center;
      width: 360px;
      box-shadow: 0 0 20px rgba(233, 30, 99, 0.3);
    }
    .login-box h2 {
      color: #d81b60;
      margin-bottom: 1.5rem;
      letter-spacing: 1px;
    }
    .login-box input {
      width: 100%;
      padding: 0.75rem;
      margin: 0.5rem 0;
      border: none;
      border-radius: 8px;
      background: #222;
      color: #fff;
      font-size: 1rem;
    }
    .login-box button {
      width: 100%;
      padding: 0.75rem;
      background: #d81b60;
      color: #fff;
      border: none;
      border-radius: 8px;
      font-size: 1rem;
      font-weight: 600;
      cursor: pointer;
      transition: background 0.2s ease;
      margin-top: 0.5rem;
    }
    .login-box button:hover {
      background: #ff4081;
    }
    .error {
      color: #ff7675;
      margin-top: 1rem;
      font-weight: 500;
    }
  </style>
</head>
<body>
  <div class="login-box">
    <h2>AHF Admin Login</h2>
    <form method="POST">
      <input type="text" name="username" placeholder="Username" required autofocus>
      <input type="password" name="password" placeholder="Password" required>
      <button type="submit">Sign In</button>
      <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>
    </form>
  </div>
</body>
</html>
