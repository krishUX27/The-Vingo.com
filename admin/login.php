<?php
session_start();
require_once __DIR__ . '/../includes/db.php';

// Production Error Handling
ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_NOTICE);

// Custom logging function (same as dashboard)
function login_log($msg)
{
  if (function_exists('dashboard_log')) {
    dashboard_log("[LOGIN] $msg");
  } else {
    $log_path = __DIR__ . '/debug.log';
    $time = date('Y-m-d H:i:s');
    file_put_contents($log_path, "[$time] [LOGIN] $msg\n", FILE_APPEND);
  }
}

$error = '';
if (isset($_GET['msg'])) {
  if ($_GET['msg'] === 'on_hold') {
    $error = 'Your Vingo service is currently on hold because the payment has not been completed. Please complete the payment to restore access.';
  } elseif ($_GET['msg'] === 'account_disabled') {
    $error = 'Your account has been deactivated. Please contact the Super Admin.';
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $u = trim($_POST['username'] ?? '');
  $p = $_POST['password'] ?? '';

  if (empty($u) || empty($p)) {
    $error = 'Please enter both username and password.';
  } else {
    $stmt = $conn->prepare("SELECT id, username, password, role, status FROM users WHERE (username = ? OR email = ?) AND role = 'admin' LIMIT 1");
    if (!$stmt) {
      login_log("Query error: " . $conn->error);
      $error = 'Internal server error. Please check logs.';
    } else {
      $stmt->bind_param('ss', $u, $u);
      $stmt->execute();
      $result = $stmt->get_result();

      if ($row = $result->fetch_assoc()) {
        if (password_verify($p, $row['password'])) {
          if (($row['status'] ?? 'active') === 'hold') {
            $error = 'Your Vingo service is currently on hold because the payment has not been completed. Please complete the payment to restore access.';
            login_log("Login blocked: User '{$row['username']}' is on hold.");
          } else {
            login_log("User '$u' logged in successfully.");
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_username'] = $row['username'];
            $_SESSION['admin_id'] = $row['id'];
            $_SESSION['admin_status'] = $row['status'];
            header('Location: dashboard.php');
            exit;
          }
        } else {
          $error = 'Incorrect password.';
        }
      } else {
        // Double check if account exists but is a Superadmin
        $check = $conn->prepare("SELECT role FROM users WHERE username = ? OR email = ? LIMIT 1");
        $check->bind_param('ss', $u, $u);
        $check->execute();
        $res = $check->get_result();
        if ($r = $res->fetch_assoc()) {
          if ($r['role'] === 'superadmin') {
            $error = '';
          } else {
            $error = 'Account not found.';
          }
        } else {
          $error = 'Account not found.';
        }
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
  <link rel="icon" type="image/png" href="../assets/images/favicon.png">
</head>

<body class="login-screen">

  <?php
  // Determine animation class based on error type
  $anim_class = '';
  if ($error) {
    if (strpos($error, 'on hold') !== false) {
      $anim_class = 'card-hold';
    } else {
      $anim_class = 'shake';
    }
  }
  ?>

  <div class="login-card <?= $anim_class ?>">
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
        <label for="username">Username or Email</label>
        <input type="text" id="username" name="username" placeholder="Enter username or email" required autofocus>
      </div>
      <div class="form-group" style="margin-bottom:24px">
        <label for="password">Password</label>
        <div style="position:relative">
          <input type="password" id="password" name="password" placeholder="••••••••" required
            style="width:100%; padding-right:45px">
          <span id="togglePassword"
            style="position:absolute; right:15px; top:50%; transform:translateY(-50%); cursor:pointer; font-size:1.1rem; opacity:0.9; transition:0.2s">👁️</span>
        </div>
        <div style="text-align:right; margin-top:8px">
          <a href="forgot-password.php" style="font-size:0.75rem; color:var(--text-light); font-weight:600; text-decoration:none">Forgot Password?</a>
        </div>
      </div>
      <button type="submit" class="btn btn-primary" style="width:100%; justify-content:center; padding:16px">
        🚀 Sign In
      </button>
    </form>

    <script>
      const togglePassword = document.querySelector('#togglePassword');
      const password = document.querySelector('#password');

      togglePassword.addEventListener('click', function (e) {
        // Toggle the type attribute
        const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
        password.setAttribute('type', type);

        // Toggle the eye icon emoji
        this.textContent = type === 'password' ? '👁️' : '🙈';
      });
    </script>

    <p style="text-align:center; font-size:0.75rem; color:var(--text-light); margin-top:30px">
      © <?= date('Y') ?> Vingo Menu Manager v2
    </p>
  </div>

</body>

</html>