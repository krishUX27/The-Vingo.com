<?php
// admin/profile.php
require_once __DIR__ . '/partials/auth_check.php';
require_once __DIR__ . '/../includes/db.php';

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
$admin_sess_id = $_SESSION['admin_id'] ?? 0;
$sess_username = $_SESSION['admin_username'] ?? 'admin';

// Fetch current user details from DB
$u_stmt = $conn->prepare("SELECT email FROM users WHERE id = ?");
$u_stmt->bind_param('i', $admin_sess_id);
$u_stmt->execute();
$u_res = $u_stmt->get_result();
$user_data = $u_res->fetch_assoc();
$admin_email = $user_data['email'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $email = trim($_POST['email'] ?? '');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'Please enter a valid email address.'];
        } else {
            $stmt = $conn->prepare("UPDATE users SET email = ? WHERE id = ?");
            $stmt->bind_param('si', $email, $admin_sess_id);
            if ($stmt->execute()) {
                $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Profile updated successfully!'];
                header('Location: profile.php'); exit;
            } else {
                $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'Failed to update email.'];
            }
        }
    } elseif ($action === 'update_password') {
        $new_pass = $_POST['new_password'] ?? '';
        $conf_pass = $_POST['confirm_password'] ?? '';

        if (strlen($new_pass) < 6) {
            $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'Password must be at least 6 characters.'];
        } elseif ($new_pass !== $conf_pass) {
            $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'Passwords do not match.'];
        } else {
            $hashed = password_hash($new_pass, PASSWORD_BCRYPT);
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->bind_param('si', $hashed, $admin_sess_id);
            if ($stmt->execute()) {
                $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Password updated successfully!'];
                header('Location: profile.php'); exit;
            } else {
                $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'Failed to update password.'];
            }
        }
    }
}

$cur = 'profile.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>My Profile — Vingo Menu</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="../assets/css/menu-style.css?v=<?= time() ?>">
  <link rel="icon" type="image/png" href="../assets/images/favicon.png">
</head>
<body>

<?php include __DIR__ . '/partials/sidebar.php'; ?>

<div class="main">
  <div class="topbar">
    <div class="topbar-left" style="display:flex; align-items:center; gap:16px">
      <div class="menu-toggle" id="menuToggle">☰</div>
      <div>
        <h1>👤 My Profile</h1>
        <p class="meta">Manage your account and credentials</p>
      </div>
    </div>
    <div class="topbar-right" style="display:flex; gap:16px; align-items:center">
      <?php include __DIR__ . '/partials/topbar_user.php'; ?>
    </div>
  </div>

  <div class="content">
    <?php if ($flash): ?>
      <div class="flash flash-<?= $flash['type'] ?>" style="margin-bottom:20px"><?= $flash['msg'] ?></div>
    <?php endif; ?>

    <div style="display:grid; grid-template-columns:1fr 1fr; gap:24px">
      <div class="card">
        <div class="card-title">Profile Identity</div>
        <form method="POST">
          <input type="hidden" name="action" value="update_profile">
          <div class="form-group">
            <label>Username</label>
            <input type="text" value="<?= htmlspecialchars($sess_username) ?>" disabled style="background:#f1f5f9; cursor:not-allowed">
            <p style="font-size:0.75rem; color:var(--text-light); margin-top:4px">Username cannot be changed in this version.</p>
          </div>
          
          <div class="form-group" style="margin-top:20px">
            <label>Support Email</label>
            <input type="email" name="email" value="<?= htmlspecialchars($admin_email) ?>" required>
          </div>

          <div style="margin-top:24px">
            <button type="submit" class="btn btn-primary">Update Profile</button>
          </div>
        </form>
      </div>

      <div class="card" style="border-color:rgba(239, 68, 68, 0.1)">
        <div class="card-title" style="color:var(--danger)">Security & Access</div>
        <p style="font-size:0.85rem; color:var(--text-light); margin-bottom:20px">
          Update your login password below. Ensure it's at least 6 characters long.
        </p>
        
        <form method="POST">
          <input type="hidden" name="action" value="update_password">
          <div class="form-group">
            <label>New Password</label>
            <input type="password" name="new_password" placeholder="••••••••" required>
          </div>
          <div class="form-group" style="margin-top:20px">
            <label>Confirm Password</label>
            <input type="password" name="confirm_password" placeholder="••••••••" required>
          </div>

          <div style="margin-top:24px">
            <button type="submit" class="btn btn-primary">Update Password</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

</body>
</html>
