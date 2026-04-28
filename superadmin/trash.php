<?php
// superadmin/trash.php — Super Admin Trash for Admin Accounts
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../includes/db.php';

// Handle Restore
if (isset($_GET['restore'])) {
    $id = (int)$_GET['restore'];
    $stmt = $conn->prepare("UPDATE users SET is_deleted = 0, deleted_at = NULL WHERE id = ? AND role = 'admin'");
    $stmt->bind_param('i', $id);
    if ($stmt->execute()) {
        $_SESSION['flash'] = ['type' => 'success', 'msg' => "Admin account restored."];
    }
    header('Location: trash.php');
    exit;
}

// Handle Permanent Delete
if (isset($_GET['pdelete'])) {
    $id = (int)$_GET['pdelete'];
    
    // Safety: only delete 'admin' roles
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role = 'admin'");
    $stmt->bind_param('i', $id);
    if ($stmt->execute()) {
        $_SESSION['flash'] = ['type' => 'success', 'msg' => "Admin account permanently deleted."];
    }
    header('Location: trash.php');
    exit;
}

// Handle Bulk Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $ids = $_POST['admin_ids'] ?? [];
    if (!empty($ids)) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        if ($_POST['action'] === 'bulk_restore') {
            $stmt = $conn->prepare("UPDATE users SET is_deleted = 0, deleted_at = NULL WHERE id IN ($placeholders) AND role = 'admin'");
        } else {
            $stmt = $conn->prepare("DELETE FROM users WHERE id IN ($placeholders) AND role = 'admin'");
        }
        $types = str_repeat('i', count($ids));
        $params = array_map('intval', $ids);
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $stmt->close();
        $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Bulk action completed.'];
    }
    header('Location: trash.php');
    exit;
}

$trash_admins = $conn->query("SELECT id, username, email, deleted_at FROM users WHERE role = 'admin' AND is_deleted = 1 ORDER BY deleted_at DESC")->fetch_all(MYSQLI_ASSOC);

$cur = 'trash.php';
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Super Trash Bin | Vingo Master</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="../assets/css/menu-style.css?v=<?= time() ?>">
  <link rel="icon" type="image/png" href="../assets/images/favicon.png">
  <style>
    :root { 
      --super-accent: #f59e0b; 
      --super-accent-glow: rgba(245, 158, 11, 0.3);
      --super-sidebar: #0f172a; 
    }
    .sidebar { background: var(--super-sidebar) !important; border-right: 1px solid rgba(255,255,255,0.05) !important; }
    .sidebar-header { color: var(--super-accent) !important; }
    .sidebar nav a.active { background: var(--super-accent) !important; color: #0f172a !important; box-shadow: 0 4px 20px var(--super-accent-glow) !important; }
    
    .btn-primary { background: var(--super-accent); color: #0f172a; font-weight: 800; border: none; }
    .btn-primary:hover { background: #fbbf24; transform: translateY(-2px); }
    .status-badge { padding: 4px 12px; border-radius: 99px; font-size: 0.75rem; font-weight: 600; }
    .user-avatar { background: var(--super-accent) !important; color: #0f172a !important; box-shadow: 0 4px 12px var(--super-accent-glow) !important; }
    
    @media (max-width: 768px) {
      .content { padding: 20px; }
      .topbar { padding: 0 20px; }
      .table-wrap { overflow-x: auto; margin: 0 -15px; padding: 0 15px; }
      .table-wrap table { min-width: 800px; }
      h1 { font-size: 1.3rem; }
    }
  </style>
</head>
<body>
<?php include __DIR__ . '/partials/super_sidebar.php'; ?>
<div class="main">
  <div class="topbar">
    <div class="topbar-left">
      <div class="menu-toggle" id="menuToggle">☰</div>
      <h1>🗑️ Super Trash Bin</h1>
    </div>
    <div class="topbar-right">
       <?php include __DIR__ . '/../admin/partials/topbar_user.php'; ?>
    </div>
  </div>

  <div class="content">
    <?php if ($flash): ?>
      <div class="flash flash-<?= $flash['type'] ?>"><?= $flash['msg'] ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-title">Deleted Admin Accounts</div>
        <p class="meta" style="margin-bottom:20px">Restore active restaurants or permanently remove data.</p>

        <form method="POST">
        <div id="bulk-controls" style="display:none; margin-bottom:15px; gap:10px">
            <button type="submit" name="action" value="bulk_restore" class="btn btn-primary btn-sm">♻️ Restore Selected</button>
            <button type="submit" name="action" value="bulk_pdelete" class="btn btn-danger btn-sm" onclick="return confirm('Permanently delete selected accounts?')">❌ Delete Permanently</button>
        </div>

        <div class="table-wrap">
            <table style="width:100%">
                <thead>
                    <tr>
                        <th><input type="checkbox" id="select-all"></th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Deleted On</th>
                        <th style="text-align:right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($trash_admins)): ?>
                        <tr><td colspan="5" style="text-align:center; padding:40px; color:#64748b">No deleted accounts in trash.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($trash_admins as $admin): ?>
                    <tr>
                        <td><input type="checkbox" name="admin_ids[]" value="<?= $admin['id'] ?>" class="admin-checkbox"></td>
                        <td><strong><?= htmlspecialchars($admin['username']) ?></strong></td>
                        <td><?= htmlspecialchars($admin['email']) ?></td>
                        <td style="font-size:0.8rem"><?= date('M d Y, H:i', strtotime($admin['deleted_at'])) ?></td>
                        <td style="text-align:right">
                            <div class="btn-grp">
                                <a href="trash.php?restore=<?= $admin['id'] ?>" class="btn btn-primary btn-sm">♻️ Restore</a>
                                <a href="trash.php?pdelete=<?= $admin['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Permanently delete this account?')">🗑️</a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        </form>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const selectAll = document.getElementById('select-all');
    const checkboxes = document.querySelectorAll('.admin-checkbox');
    const bulkControls = document.getElementById('bulk-controls');

    function updateUI() {
        const checkedCount = document.querySelectorAll('.admin-checkbox:checked').length;
        bulkControls.style.display = checkedCount > 0 ? 'flex' : 'none';
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
</body>
</html>
