<?php
session_start();
require_once __DIR__ . '/../includes/db.php';

// Hardcoded Admin Credentials (Change as needed)
$admin_user = 'admin';
$admin_pass = 'admin123';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = $_POST['username'] ?? '';
    $p = $_POST['password'] ?? '';

    if ($u === $admin_user && $p === $admin_pass) {
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_username']  = $u;
        header('Location: dashboard.php');
        exit;
    } else {
        $error = 'Invalid username or password.';
    }
}

// Redirect if already logged in
if (isset($_SESSION['admin_logged_in'])) {
    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Login — Vingo Menu Manager</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="../assets/css/menu-style.css?v=<?= time() ?>">
</head>
<body class="login-screen">

<div class="login-card">
  <div class="login-header">
    <div class="logo">🍴</div>
    <h2>Vingo Menu</h2>
    <p>Sign in to manage your menu</p>
  </div>

  <?php if ($error): ?>
    <div class="flash flash-danger" style="margin-bottom:20px; text-align:center">
      ❌ <?= $error ?>
    </div>
  <?php endif; ?>

  <form method="POST">
    <div class="form-group" style="margin-bottom:20px">
      <label for="username">Username</label>
      <input type="text" id="username" name="username" placeholder="Enter username" required autofocus>
    </div>
    <div class="form-group" style="margin-bottom:24px">
      <label for="password">Password</label>
      <input type="password" id="password" name="password" placeholder="••••••••" required>
    </div>
    <button type="submit" class="btn btn-primary" style="width:100%; justify-content:center; padding:16px">
      🚀 Sign In
    </button>
  </form>

  <p style="text-align:center; font-size:0.75rem; color:var(--text-light); margin-top:30px">
    © <?= date('Y') ?> Vingo Menu Manager v2
  </p>
</div>

</body>
</html>
