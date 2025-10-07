<?php
session_start();

$ADMIN_USER = 'admin';
$ADMIN_PASS = 'fit2025!';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $user = trim($_POST['username'] ?? '');
  $pass = trim($_POST['password'] ?? '');

  if ($user === $ADMIN_USER && $pass === $ADMIN_PASS) {
    $_SESSION['logged_in'] = true;
    header('Location: dashboard.php');
    exit;
  } else {
    $error = 'Invalid username or password.';
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
