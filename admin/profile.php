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
    $email = trim($_POST['email'] ?? '');
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'Please enter a valid email address.'];
    } else {
        // Update the users table directly
        $stmt = $conn->prepare("UPDATE users SET email = ? WHERE id = ?");
        $stmt->bind_param('si', $email, $admin_sess_id);
        
        if ($stmt->execute()) {
            $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Profile updated successfully!'];
            header('Location: profile.php');
            exit;
        } else {
            $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'Failed to update email.'];
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
        <div style="background:#f1f5f9; color:#475569; padding:16px; border-radius:12px; font-size:0.85rem; margin-bottom:20px">
          <strong>Security Note:</strong> Contact the Platform Superadmin to reset your master login credentials or password if required.
        </div>
        
        <div class="form-group" style="opacity:0.6; pointer-events:none">
          <label>New Password</label>
          <input type="password" placeholder="••••••••" disabled>
        </div>
        <div class="form-group" style="margin-top:20px; opacity:0.6; pointer-events:none">
          <label>Confirm Password</label>
          <input type="password" placeholder="••••••••" disabled>
        </div>

        <div style="margin-top:24px">
          <button disabled class="btn btn-outline" style="cursor:not-allowed; opacity:0.5">Manage Security</button>
        </div>
      </div>
    </div>
  </div>
</div>

</body>
</html>
