<?php
// superadmin/edit-admin.php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/logger.php';

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    header('Location: manage-admins.php');
    exit;
}

// Fetch admin
$stmt = $conn->prepare("SELECT id, username, email, role FROM users WHERE id = ? AND role = 'admin'");
$stmt->bind_param('i', $id);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$admin) {
    header('Location: manage-admins.php');
    exit;
}

$error = '';
$success = '';

// Handle Update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $role = $_POST['role'] ?? 'admin';
    $new_pass = $_POST['new_password'] ?? '';

    if (empty($u) || empty($email)) {
        $error = 'Username and email are required.';
    } else {
        if (!empty($new_pass)) {
            // Updating with password
            $hashed = password_hash($new_pass, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET username = ?, email = ?, role = ?, password = ? WHERE id = ?");
            $stmt->bind_param('ssssi', $u, $email, $role, $hashed, $id);
        } else {
            // Updating without password
            $stmt = $conn->prepare("UPDATE users SET username = ?, email = ?, role = ? WHERE id = ?");
            $stmt->bind_param('sssi', $u, $email, $role, $id);
        }

        if ($stmt->execute()) {
            $_SESSION['flash'] = ['type' => 'success', 'msg' => "Admin '{$u}' updated successfully."];
            header('Location: manage-admins.php');
            exit;
        } else {
            if ($stmt->errno === 1062) {
                $error = "Duplicate entry! Username or email already exists.";
            } else {
                platform_log("Admin Edit Fault", $stmt->error, "CRITICAL");
                $error = "System Error: " . $stmt->error;
            }
        }
        $stmt->close();
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Edit Admin | Vingo Master</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="../assets/css/menu-style.css?v=<?= time() ?>">
  <style>
    :root { 
      --super-accent: #f59e0b; 
      --super-accent-glow: rgba(245, 158, 11, 0.3);
      --super-sidebar: #0f172a; 
      --super-sidebar-h: #1e293b;
    }
    
    .sidebar { background: var(--super-sidebar) !important; border-right: 1px solid rgba(255,255,255,0.05) !important; }
    .sidebar-header { color: var(--super-accent) !important; }
    .sidebar-header span:first-child { background: var(--super-accent) !important; box-shadow: 0 4px 12px var(--super-accent-glow) !important; }
    .sidebar nav a:hover { background: var(--super-sidebar-h) !important; color: var(--super-accent) !important; }
    .sidebar nav a.active { background: var(--super-accent) !important; color: #0f172a !important; box-shadow: 0 4px 20px var(--super-accent-glow) !important; }

    .main { min-height: 100vh; }
    .content { padding: 30px; }
    
    .btn-primary { background: var(--super-accent); color: #0f172a; font-weight: 800; border: none; }
    .btn-primary:hover { background: #fbbf24; transform: translateY(-2px); box-shadow: 0 8px 20px var(--super-accent-glow); }
    
    input:focus { border-color: var(--super-accent) !important; box-shadow: 0 0 0 4px var(--super-accent-glow) !important; }

    @media (max-width: 768px) {
      .content { padding: 20px; }
      .topbar { padding: 0 20px; }
      h1 { font-size: 1.3rem; }
    }
  </style>
</head>
<body>

<?php include __DIR__ . '/partials/super_sidebar.php'; ?>

<div class="main">
  <div class="topbar">
    <div class="topbar-left" style="display:flex; align-items:center; gap:16px">
      <div class="menu-toggle" id="menuToggle">☰</div>
      <h1>✏️ Edit Admin Account</h1>
    </div>
    <div class="topbar-right">
      <?php include __DIR__ . '/../admin/partials/topbar_user.php'; ?>
    </div>
  </div>

   <div class="content">
    <div style="max-width: 600px; margin: 0 auto;">
      
      <a href="manage-admins.php" style="display:inline-block; margin-bottom:20px; color:#64748b; text-decoration:none">← Back to Admins</a>

      <div class="card">
        <div class="card-title">Edit Admin Details</div>
        
        <?php if ($error): ?>
          <div class="flash flash-danger" style="margin-bottom:20px"><?= $error ?></div>
        <?php endif; ?>

        <form method="POST">
          <div class="form-group" style="margin-bottom:20px">
            <label>Username</label>
            <input type="text" name="username" required value="<?= htmlspecialchars($admin['username']) ?>">
          </div>
          
          <div class="form-group" style="margin-bottom:20px">
            <label>Email Address</label>
            <input type="email" name="email" required value="<?= htmlspecialchars($admin['email']) ?>">
          </div>

          <div class="form-group" style="margin-bottom:20px">
            <label>Role</label>
            <select name="role">
              <option value="admin" <?= $admin['role'] === 'admin' ? 'selected' : '' ?>>Restaurant Admin</option>
              <option value="superadmin" <?= $admin['role'] === 'superadmin' ? 'selected' : '' ?>>Super Admin</option>
            </select>
          </div>

          <div class="form-group" style="margin-bottom:20px">
            <label>Reset Password <span style="font-size:0.75rem; color:#64748b">(Leave blank to keep current)</span></label>
            <input type="password" name="new_password" placeholder="Enter new password if required">
          </div>

          <div style="display:flex; gap:10px; margin-top:30px">
            <button type="submit" class="btn btn-primary" style="flex:1; justify-content:center">💾 Save Changes</button>
            <a href="manage-admins.php" class="btn btn-outline" style="flex:1; justify-content:center; text-align:center">Cancel</a>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

</body>
</html>
