<?php
// admin/profile.php
require_once __DIR__ . '/partials/auth_check.php';
require_once __DIR__ . '/../includes/db.php';

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$sess_username = $_SESSION['admin_username'] ?? 'admin';
$admin_email    = menu_get_setting('admin_email', 'admin@vingo.com');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Note: Since we are using hardcoded credentials for now, this is just a placeholder logic.
    // In a real DB-backed user system, we would update the users table here.
    
    // We will update the admin_email in settings.
    $email = $_POST['email'] ?? '';
    
    $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    $key = 'admin_email';
    $stmt->bind_param('ss', $key, $email);
    $stmt->execute();
    
    $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Profile updated successfully!'];
    header('Location: profile.php');
    exit;
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
        <div style="background:#fff1f2; color:#b91c1c; padding:16px; border-radius:12px; font-size:0.85rem; margin-bottom:20px">
          <strong>Protected Account:</strong> Credentials for 'Operator' access are predefined. To change the master login, please update the <code>admin/index.php</code> configuration.
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
          <button disabled class="btn btn-outline" style="cursor:not-allowed">Manage Security</button>
        </div>
      </div>
    </div>
  </div>
</div>

</body>
</html>
