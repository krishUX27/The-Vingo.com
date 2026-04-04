<?php
// superadmin/manage-admins.php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/mail_helper.php';
require_once __DIR__ . '/../includes/logger.php';

// Auto-Fix: Ensure the email and activation columns exist in the users table
$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS email VARCHAR(100) AFTER username");
$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS is_active TINYINT DEFAULT 1 AFTER role");
$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS status ENUM('active','hold') DEFAULT 'active'");
$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS activation_token VARCHAR(128) DEFAULT NULL");
$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS token_expiry DATETIME DEFAULT NULL");

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$error = '';
$success = '';

// Handle Create Admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_admin') {
    $u = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $p = $_POST['password'] ?? '';
    $r = $_POST['role'] ?? 'admin';

    if (empty($u) || empty($email)) {
        $error = 'Username and email are required.';
    } else {
        // Generate Secure Token
        $token = bin2hex(random_bytes(32));
        $expiry = date('Y-m-d H:i:s', strtotime('+24 hours'));
        
        // Temporary random password (will be reset by admin)
        $temp_pass = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
        
        $stmt = $conn->prepare("INSERT INTO users (username, email, password, role, is_active, activation_token, token_expiry) VALUES (?, ?, ?, ?, 0, ?, ?)");
        
        if (!$stmt) {
             platform_log("User Prep Fault", $conn->error, "CRITICAL");
             $error = "Database preparation failed. Please check table structure.";
        } else {
            $stmt->bind_param('ssssss', $u, $email, $temp_pass, $r, $token, $expiry);

            if ($stmt->execute()) {
                // Trigger the mail notification with token
                $sent = sendSetupEmail($email, $u, $token);

                if ($sent) {
                    $_SESSION['flash'] = ['type' => 'success', 'msg' => "Admin invited! Activation link sent to {$email}."];
                } else {
                    $_SESSION['flash'] = ['type' => 'warn', 'msg' => "Admin created, but the email invite failed to send. Please check your server SMTP settings."];
                }
                
                header('Location: manage-admins.php');
                exit;
            } else {
                // Check for duplicate key
                if ($stmt->errno === 1062) {
                    $error = "Account already exists! Use a unique username or email.";
                } else {
                    // Log and show generic error
                    platform_log("User Creation Fault", $stmt->error, "CRITICAL");
                    $error = "System Error: " . $stmt->error;
                }
            }
            $stmt->close();
        }
    }
}

// Handle Delete Admin
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    // Soft Delete: set is_deleted = 1
    $stmt = $conn->prepare("UPDATE users SET is_deleted = 1, deleted_at = NOW() WHERE id = ? AND role = 'admin'");
    $stmt->bind_param('i', $id);
    if ($stmt->execute()) {
        $_SESSION['flash'] = ['type' => 'success', 'msg' => "Admin account moved to Trash."];
    }
    header('Location: manage-admins.php');
    exit;
}

// Handle Bulk Delete Admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'bulk_delete_admins') {
    $ids = $_POST['admin_ids'] ?? [];
    if (!empty($ids)) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $conn->prepare("UPDATE users SET is_deleted = 1, deleted_at = NOW() WHERE id IN ($placeholders) AND role = 'admin'");
        $types = str_repeat('i', count($ids));
        $params = array_map('intval', $ids);
        $stmt->bind_param($types, ...$params);
        if ($stmt->execute()) {
            $_SESSION['flash'] = ['type' => 'success', 'msg' => count($ids) . " admin accounts moved to Trash."];
        }
    }
    header('Location: manage-admins.php');
    exit;
}

// Handle Status Toggle (Hold/Activate)
if (isset($_GET['status_toggle']) && isset($_GET['status'])) {
    $id = (int)$_GET['status_toggle'];
    $new_status = $_GET['status'] === 'hold' ? 'hold' : 'active';
    
    // Safety: ensure we only target 'admin' users
    $stmt = $conn->prepare("UPDATE users SET status = ? WHERE id = ? AND role = 'admin'");
    $stmt->bind_param('si', $new_status, $id);
    
    if ($stmt->execute()) {
        if ($new_status === 'hold') {
            // Fetch admin email/name for notification
            $u_stmt = $conn->prepare("SELECT username, email FROM users WHERE id = ?");
            $u_stmt->bind_param('i', $id);
            $u_stmt->execute();
            $admin_data = $u_stmt->get_result()->fetch_assoc();
            
            if ($admin_data) {
                sendHoldEmail($admin_data['email'], $admin_data['username']);
            }
            $_SESSION['flash'] = ['type' => 'success', 'msg' => "Admin account has been placed on hold."];
        } else {
            $_SESSION['flash'] = ['type' => 'success', 'msg' => "Admin account has been re-activated."];
        }
    }
    header('Location: manage-admins.php');
    exit;
}

// Fetch all admins (only non-deleted)
$admins = $conn->query("SELECT id, username, email, role, status, created_at FROM users WHERE role = 'admin' AND is_deleted = 0 ORDER BY created_at DESC")->fetch_all(MYSQLI_ASSOC);

$cur = 'manage-admins.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Manage Admins | Vingo Master</title>
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
    
    .flash-success { background: #fffbeb; border-color: var(--super-accent); color: #d97706; }
    
    input:focus { border-color: var(--super-accent) !important; box-shadow: 0 0 0 4px var(--super-accent-glow) !important; }
    .user-avatar { background: var(--super-accent) !important; color: #0f172a !important; box-shadow: 0 4px 12px var(--super-accent-glow) !important; }
    
    .super-grid { display: grid; grid-template-columns: 1fr 340px; gap: 30px; align-items: start; }
    @media (max-width: 1024px) {
      .super-grid { grid-template-columns: 1fr; }
    }
  </style>
</head>
<body>

<?php include __DIR__ . '/partials/super_sidebar.php'; ?>

<div class="main">
  <div class="topbar">
    <div class="topbar-left" style="display:flex; align-items:center; gap:16px">
      <div class="menu-toggle" id="menuToggle">☰</div>
      <h1>👥 Manage Admin Accounts</h1>
    </div>
    <div class="topbar-right">
      <?php include __DIR__ . '/../admin/partials/topbar_user.php'; ?>
    </div>
  </div>

   <div class="content">
    <?php if ($flash): ?>
      <div class="flash flash-<?= $flash['type'] ?>"><?= $flash['msg'] ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="flash flash-danger"><?= $error ?></div>
    <?php endif; ?>

    <div class="super-grid">
      
      <!-- Admin List -->
      <div class="card">
        <div class="card-title">Active Admin Accounts</div>
        <div class="table-wrap">
          <table style="width:100%">
            <thead>
              <tr style="text-align:left">
                <th><input type="checkbox" id="select-all"></th>
                <th>Username</th>
                <th>Email</th>
                <th>Status</th>
                <th>Created</th>
                <th style="text-align:right">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($admins as $a): ?>
              <tr>
                <td><strong><?= htmlspecialchars($a['username']) ?></strong></td>
                <td style="font-size:0.85rem; color:#64748b"><?= htmlspecialchars($a['email'] ?? 'N/A') ?></td>
                <td>
                  <?php if (($a['status'] ?? 'active') === 'active'): ?>
                    <span style="background: #dcfce7; color: #166534; padding: 4px 10px; border-radius: 99px; font-size: 0.75rem; font-weight: 600;">● Active</span>
                  <?php else: ?>
                    <span style="background: #fee2e2; color: #991b1b; padding: 4px 10px; border-radius: 99px; font-size: 0.75rem; font-weight: 600;">● On Hold</span>
                  <?php endif; ?>
                </td>
                <td style="font-size:0.85rem; color:var(--text-light)"><?= date('M d, Y', strtotime($a['created_at'])) ?></td>
                <td style="text-align:right">
                  <a href="edit-admin.php?id=<?= $a['id'] ?>" class="btn btn-outline btn-sm" style="margin-right:5px">✏️ Edit</a>
                  
                  <?php if (($a['status'] ?? 'active') === 'active'): ?>
                    <a href="?status_toggle=<?= $a['id'] ?>&status=hold" class="btn btn-warning btn-sm" style="margin-right:5px; background:#fef3c7; color:#92400e; border:1px solid #fde68a" onclick="return confirm('Place this account on hold?')">⏸️ Hold</a>
                  <?php else: ?>
                    <a href="?status_toggle=<?= $a['id'] ?>&status=active" class="btn btn-success btn-sm" style="margin-right:5px; background:#dcfce7; color:#166534; border:1px solid #bbf7d0" onclick="return confirm('Re-activate this account?')">▶️ Activate</a>
                  <?php endif; ?>

                  <a href="?delete=<?= $a['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete this admin account?')">🗑️ Delete</a>
                </td>
              </tr>
              <?php endforeach; ?>
              <?php if (empty($admins)): ?>
                <tr><td colspan="4" style="text-align:center; padding:40px; color:var(--text-light)">No admin accounts found.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
          </form>
          <script>
          document.addEventListener('DOMContentLoaded', function() {
              const selectAll = document.getElementById('select-all');
              const checkboxes = document.querySelectorAll('.admin-checkbox');
              const bulkControls = document.getElementById('bulk-controls');
              const selectCountDisplay = document.getElementById('select-count');

              function updateUI() {
                  const checkedCount = document.querySelectorAll('.admin-checkbox:checked').length;
                  bulkControls.style.display = checkedCount > 0 ? 'flex' : 'none';
                  selectCountDisplay.textContent = `${checkedCount} selected`;
              }

              if (selectAll) {
                  selectAll.addEventListener('change', function() {
                      checkboxes.forEach(cb => cb.checked = this.checked);
                      updateUI();
                  });
              }

              checkboxes.forEach(cb => {
                  cb.addEventListener('change', updateUI);
              });
          });
          </script>
        </div>
      </div>

      <!-- Add Admin Form -->
      <div class="card">
        <div class="card-title">➕ Create New Admin</div>
        <form method="POST">
          <input type="hidden" name="action" value="add_admin">
          <div class="form-group" style="margin-bottom:15px">
            <label>Master Username (ID)</label>
            <input type="text" name="username" required placeholder="e.g. josh_ops">
          </div>
          <div class="form-group" style="margin-bottom:15px">
            <label>Recipient Email</label>
            <input type="email" name="email" required placeholder="admin@thevingo.com">
          </div>
          <p style="font-size:0.75rem; color:#64748b; margin-bottom:15px">
            A secure password setup link will be sent to the email provided above.
          </p>
          <button type="submit" class="btn btn-primary" style="width:100%; justify-content:center">📧 Send Setup Invite</button>
        </form>
      </div>

    </div>
  </div>
</div>

</body>
</html>
