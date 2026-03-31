<?php
// superadmin/manage-admins.php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/mail_helper.php';

// Auto-Fix: Ensure the email column exists in the users table
$conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS email VARCHAR(100) AFTER username");

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

    if (empty($u) || empty($p) || empty($email)) {
        $error = 'Username, email, and password are required.';
    } else {
        $hash = password_hash($p, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO users (username, email, password, role) VALUES (?, ?, ?, ?)");
        $stmt->bind_param('ssss', $u, $email, $hash, $r);

        if ($stmt->execute()) {
            // Trigger the mail notification
            @sendSetupEmail($email, $u);

            $_SESSION['flash'] = ['type' => 'success', 'msg' => "Admin '{$u}' created!"];
            header('Location: manage-admins.php');
            exit;
        } else {
            // Check for duplicate key
            if ($stmt->errno === 1062) {
                $error = "Account already exists! Create new Vingo Menu login credentials.";
            } else {
                // Log and show generic error
                platform_log("User Creation Fault", $stmt->error, "CRITICAL");
                $error = "System Error: " . $stmt->error;
            }
        }
        $stmt->close();
    }
}

// Handle Delete Admin
if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    // Safety: ensure we only delete 'admin' roles from this specific page
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role = 'admin'");
    $stmt->bind_param('i', $id);
    if ($stmt->execute()) {
        $_SESSION['flash'] = ['type' => 'success', 'msg' => "Admin account deleted."];
    }
    header('Location: manage-admins.php');
    exit;
}

// Fetch all admins
$admins = $conn->query("SELECT id, username, email, role, created_at FROM users WHERE role = 'admin' ORDER BY created_at DESC")->fetch_all(MYSQLI_ASSOC);

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
    :root { --super-accent: #f59e0b; }
    .main { min-height: 100vh; }
    .content { padding: 30px; }
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

    <div style="display:grid; grid-template-columns: 1fr 340px; gap:30px; align-items:start">
      
      <!-- Admin List -->
      <div class="card">
        <div class="card-title">Active Admin Accounts</div>
        <div class="table-wrap">
          <table style="width:100%">
            <thead>
              <tr style="text-align:left">
                <th>Username</th>
                <th>Email</th>
                <th>Created</th>
                <th style="text-align:right">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($admins as $a): ?>
              <tr>
                <td><strong><?= htmlspecialchars($a['username']) ?></strong></td>
                <td style="font-size:0.85rem; color:#64748b"><?= htmlspecialchars($a['email'] ?? 'N/A') ?></td>
                <td style="font-size:0.85rem; color:var(--text-light)"><?= date('M d, Y', strtotime($a['created_at'])) ?></td>
                <td style="text-align:right">
                  <a href="?delete=<?= $a['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete this admin account?')">🗑️ Delete</a>
                </td>
              </tr>
              <?php endforeach; ?>
              <?php if (empty($admins)): ?>
                <tr><td colspan="4" style="text-align:center; padding:40px; color:var(--text-light)">No admin accounts found.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Add Admin Form -->
      <div class="card">
        <div class="card-title">➕ Create New Admin</div>
        <form method="POST">
          <input type="hidden" name="action" value="add_admin">
          <div class="form-group" style="margin-bottom:15px">
            <label>Internal ID</label>
            <input type="text" name="username" required placeholder="e.g. josh_ops">
          </div>
          <div class="form-group" style="margin-bottom:15px">
            <label>Contact Email</label>
            <input type="email" name="email" required placeholder="admin@thevingo.com">
          </div>
          <div class="form-group" style="margin-bottom:15px">
            <label>Security Key</label>
            <input type="password" name="password" required placeholder="••••••••">
          </div>
          <button type="submit" class="btn btn-primary" style="width:100%; justify-content:center">🚀 Create Account</button>
        </form>
      </div>

    </div>
  </div>
</div>

</body>
</html>
