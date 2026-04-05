<?php
session_start();
require_once __DIR__ . '/../includes/db.php';

// Enable error reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Custom logging function
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
        try {
            $stmt = $conn->prepare("SELECT id, username, password, role, is_active, status FROM users WHERE (username = ? OR email = ?) AND role = 'admin' AND is_deleted = 0 LIMIT 1");
            if (!$stmt) {
                login_log("Prepare failed: " . $conn->error);
                $error = 'System connectivity error.';
            } else {
                $stmt->bind_param('ss', $u, $u);
                $stmt->execute();
                $result = $stmt->get_result();
            }
        } catch (Exception $e) {
            login_log("Login Query Exception: " . $e->getMessage());
            // Fallback for cases where is_deleted doesn't exist yet
            $stmt = $conn->prepare("SELECT id, username, password, role, is_active, status FROM users WHERE (username = ? OR email = ?) AND role = 'admin' LIMIT 1");
            $stmt->bind_param('ss', $u, $u);
            $stmt->execute();
            $result = $stmt->get_result();
        }

        if (isset($result) && ($row = $result->fetch_assoc())) {
            if (password_verify($p, $row['password'])) {
                // [Hold Feature Check]
                if (($row['status'] ?? 'active') === 'hold') {
                    $error = 'Your Vingo service is currently on hold because the payment has not been completed. Please complete the payment to restore access.';
                    login_log("Login blocked: User '{$row['username']}' is on hold.");
                } elseif ($row['is_active'] != 1) {
                    $error = 'Your account is not yet activated. Please check your email.';
                } else {
                    login_log("User '$u' logged in successfully.");
                    $_SESSION['admin_logged_in'] = true;
                    $_SESSION['admin_username']  = $row['username'];
                    $_SESSION['admin_id']        = $row['id'];
                    $_SESSION['admin_status']    = $row['status'] ?? 'active';
                    header('Location: dashboard.php');
                    exit;
                }
            } else {
                $error = 'Incorrect password.';
            }
        } else {
            // Better descriptive error for roles
            $check = $conn->prepare("SELECT role FROM users WHERE (username = ? OR email = ?) LIMIT 1");
            $check->bind_param('ss', $u, $u);
            $check->execute();
            $res_check = $check->get_result();
            if ($r = $res_check->fetch_assoc()) {
                if ($r['role'] === 'superadmin') {
                    $error = '';
                } else {
                    $error = 'Account not found.';
                }
            } else {
                $error = 'Account not found.';
            }
        }
        if (isset($stmt)) $stmt->close();
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
  <style>
    @keyframes shake {
      0%, 100% { transform: translateX(0); }
      20%, 60% { transform: translateX(-8px); }
      40%, 80% { transform: translateX(8px); }
    }
    .shake { animation: shake 0.5s ease-in-out; }

    @keyframes flipIn {
      0% { transform: perspective(1000px) rotateX(-90deg); opacity: 0; }
      100% { transform: perspective(1000px) rotateX(0deg); opacity: 1; }
    }
    .flip-active { animation: flipIn 0.6s cubic-bezier(0.23, 1, 0.32, 1) forwards; }

    .hold-container { 
      display: flex; flex-direction: column; align-items: center; justify-content: center; 
      padding: 20px 0; text-align: center;
    }
    .hold-icon { font-size: 3rem; margin-bottom: 15px; }
    .hold-title { font-weight: 800; font-size: 1.2rem; color: #991b1b; margin-bottom: 10px; }
    .hold-text { font-size: 0.9rem; color: #4b5563; line-height: 1.5; margin-bottom: 25px; }
  </style>
</head>
<body class="login-screen">

<?php 
  $is_hold = (strpos($error, 'on hold') !== false); 
  $is_shake = ($error !== '' && !$is_hold);
?>

<div class="login-card <?= $is_shake ? 'shake' : '' ?> <?= $is_hold ? 'flip-active' : '' ?>" id="loginCard">
  
  <?php if ($is_hold): ?>
    <!-- Account Hold View -->
    <div class="hold-container">
      <div class="hold-icon">⚠️</div>
      <div class="hold-title">Account On Hold</div>
      <p class="hold-text">
        Your account is currently on hold due to pending payment.<br>
        Please contact the <strong>Super Admin</strong> to restore access.
      </p>
      <a href="index.php" class="btn btn-outline" style="width:100%; justify-content:center; padding:14px; border-radius:12px">
        🔄 Retry Login
      </a>
    </div>
  <?php else: ?>
    <!-- Normal Login Form -->
    <div class="login-header">
      <div class="logo">🍴</div>
      <h2>Vingo Menu</h2>
      <p>Sign in to manage your menu</p>
    </div>

    <?php if ($error): ?>
      <div class="flash flash-danger" style="margin-bottom:20px; text-align:center; border-radius:10px">
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
          <input type="password" id="password" name="password" placeholder="••••••••" required style="width:100%; padding-right:45px">
          <span id="togglePassword" style="position:absolute; right:15px; top:50%; transform:translateY(-50%); cursor:pointer; font-size:1.1rem; opacity:0.9; transition:0.2s">👁️</span>
        </div>
      </div>
      <button type="submit" class="btn btn-primary" style="width:100%; justify-content:center; padding:16px; border-radius:12px">
        🚀 Sign In
      </button>
    </form>
  <?php endif; ?>

  <p style="text-align:center; font-size:0.75rem; color:var(--text-light); margin-top:30px">
    © <?= date('Y') ?> Vingo Menu Manager v2
  </p>
</div>

<script>
  // Ensure animations play on page load
  document.addEventListener('DOMContentLoaded', () => {
    const card = document.getElementById('loginCard');
    // If we have a shake class, we trigger it explicitly
    if (card && card.classList.contains('shake')) {
      card.style.animation = 'none';
      card.offsetHeight; /* trigger reflow */
      card.style.animation = null;
    }

    const togglePassword = document.querySelector('#togglePassword');
    const password = document.querySelector('#password');
    if (togglePassword && password) {
      togglePassword.addEventListener('click', function (e) {
        const type = password.getAttribute('type') === 'password' ? 'text' : 'password';
        password.setAttribute('type', type);
        this.textContent = type === 'password' ? '👁️' : '🙈';
      });
    }
  });
</script>

</body>
</html>
