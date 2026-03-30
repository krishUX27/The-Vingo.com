<?php
session_start();
require_once __DIR__ . '/../includes/db.php';

// Enable error reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Custom logging function (same as dashboard)
function login_log($msg) {
    if (function_exists('dashboard_log')) {
        dashboard_log("[LOGIN] $msg");
    } else {
        $log_path = __DIR__ . '/debug.log';
        $time = date('Y-m-d H:i:s');
        file_put_contents($log_path, "[$time] [LOGIN] $msg\n", FILE_APPEND);
    }
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = trim($_POST['username'] ?? '');
    $p = $_POST['password'] ?? '';

    if (empty($u) || empty($p)) {
        $error = 'Please enter both username and password.';
    } else {
        $stmt = $conn->prepare("SELECT id, username, password FROM users WHERE username = ? LIMIT 1");
        if (!$stmt) {
            login_log("Query error: " . $conn->error);
            $error = 'Internal server error. Please check logs.';
        } else {
            $stmt->bind_param('s', $u);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($row = $result->fetch_assoc()) {
                if (password_verify($p, $row['password'])) {
                    login_log("User '$u' logged in successfully.");
                    $_SESSION['admin_logged_in'] = true;
                    $_SESSION['admin_username']  = $row['username'];
                    $_SESSION['admin_id']        = $row['id'];
                    header('Location: dashboard.php');
                    exit;
                } else {
                    login_log("Failed login attempt for '$u': Incorrect password.");
                    $error = 'Invalid username or password.';
                }
            } else {
                login_log("Failed login attempt for '$u': User not found.");
                $error = 'Invalid username or password.';
            }
            $stmt->close();
        }
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
  <meta name="viewport" content="width=device-width,initial-scale=1">
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
      ❌ <?= htmlspecialchars($error) ?>
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
