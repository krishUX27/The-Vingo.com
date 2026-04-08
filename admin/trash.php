<?php
// admin/trash.php — View and manage deleted dishes
require_once __DIR__ . '/partials/auth_check.php';
require_once __DIR__ . '/../includes/db.php';

$admin_sess_id = (int)($_SESSION['admin_id'] ?? 0);

// Handle Restore
if (isset($_GET['restore'])) {
    $rid = (int)$_GET['restore'];
    $stmt = $conn->prepare("UPDATE dishes SET is_deleted = 0, deleted_at = NULL WHERE id = ? AND user_id = ?");
    $stmt->bind_param('ii', $rid, $admin_sess_id);
    if ($stmt->execute()) {
        $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Dish restored successfully!'];
    }
    $stmt->close();
    header("Location: trash.php");
    exit;
}

// Handle Permanent Delete
if (isset($_GET['pdelete'])) {
    $pid = (int)$_GET['pdelete'];
    
    // Fetch image to delete file
    $s = $conn->prepare("SELECT image FROM dishes WHERE id = ? AND user_id = ?");
    $s->bind_param('ii', $pid, $admin_sess_id);
    $s->execute();
    $d = $s->get_result()->fetch_assoc();
    $s->close();

    if ($d && $d['image'] && file_exists(__DIR__ . '/../uploads/' . $d['image'])) {
        unlink(__DIR__ . '/../uploads/' . $d['image']);
    }

    $stmt = $conn->prepare("DELETE FROM dishes WHERE id = ? AND user_id = ?");
    $stmt->bind_param('ii', $pid, $admin_sess_id);
    if ($stmt->execute()) {
        $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Dish permanently deleted.'];
    }
    $stmt->close();
    header("Location: trash.php");
    exit;
}

// Handle Bulk Actions in Trash
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $ids = $_POST['dish_ids'] ?? [];
    if (!empty($ids)) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        if ($_POST['action'] === 'bulk_restore') {
            $stmt = $conn->prepare("UPDATE dishes SET is_deleted = 0, deleted_at = NULL WHERE id IN ($placeholders) AND user_id = ?");
        } else {
            // Permanent delete requires file deletion first
            $stmt = $conn->prepare("SELECT image FROM dishes WHERE id IN ($placeholders) AND user_id = ?");
            $types = str_repeat('i', count($ids)) . 'i';
            $params = array_map('intval', $ids);
            $params[] = $admin_sess_id;
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $res = $stmt->get_result();
            while($row = $res->fetch_assoc()) {
                if ($row['image'] && file_exists(__DIR__ . '/../uploads/' . $row['image'])) {
                    unlink(__DIR__ . '/../uploads/' . $row['image']);
                }
            }
            $stmt->close();
            $stmt = $conn->prepare("DELETE FROM dishes WHERE id IN ($placeholders) AND user_id = ?");
        }
        
        $types = str_repeat('i', count($ids)) . 'i';
        $params = array_map('intval', $ids);
        $params[] = $admin_sess_id;
        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $stmt->close();
        $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Bulk action completed.'];
    }
    header("Location: trash.php");
    exit;
}

$sql = "SELECT d.*, c.name AS cat_name FROM dishes d JOIN categories c ON c.id = d.category_id WHERE d.user_id = ? AND d.is_deleted = 1 ORDER BY d.deleted_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $admin_sess_id);
$stmt->execute();
$trash_items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="../assets/css/menu-style.css?v=<?= time() ?>">
  <style>
    .content { padding: 40px; max-width: 1400px; width: 100%; margin: 0 auto; transition: padding 0.3s ease; }
    
    @media (max-width: 1024px) {
      .content { padding: 20px; }
      .btn-grp { flex-wrap: wrap; }
      .hide-mobile { display: none; }
    }
    
    @media (max-width: 480px) {
        .trash-actions { display: flex; flex-direction: column; gap: 5px; }
        .btn-sm { width: 100%; justify-content: center; }
    }
  </style>
</head>
<body>
<?php include __DIR__ . '/partials/sidebar.php'; ?>
<div class="main">
  <div class="topbar">
    <div class="topbar-left">
      <div class="menu-toggle" id="menuToggle">☰</div>
      <h1>🗑️ Trash Bin</h1>
    </div>
    <div class="topbar-right">
       <?php include __DIR__ . '/partials/topbar_user.php'; ?>
    </div>
  </div>

  <div class="content">
    <?php if ($flash): ?>
      <div class="flash flash-<?= $flash['type'] ?>"><?= $flash['msg'] ?></div>
    <?php endif; ?>

    <div class="card">
        <div class="card-title">Deleted Items (Dishes)</div>
        <p class="meta" style="margin-bottom:20px">Items in the trash can be restored or permanently deleted.</p>

        <form method="POST" id="trash-bulk-form">
        <div class="trash-actions" id="bulk-controls" style="display:none; margin-bottom:15px; gap:10px">
            <button type="submit" name="action" value="bulk_restore" class="btn btn-primary btn-sm">♻️ Restore Selected</button>
            <button type="submit" name="action" value="bulk_pdelete" class="btn btn-danger btn-sm" onclick="return confirm('Permanently delete selected?')">❌ Delete Permanently</button>
        </div>

        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th><input type="checkbox" id="select-all"></th>
                        <th>Image</th>
                        <th>Name</th>
                        <th class="hide-mobile">Deleted On</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($trash_items)): ?>
                        <tr><td colspan="5" style="text-align:center; padding:40px; color:var(--muted)">Your trash is empty.</td></tr>
                    <?php endif; ?>
                    <?php foreach ($trash_items as $item): ?>
                    <tr>
                        <td><input type="checkbox" name="dish_ids[]" value="<?= $item['id'] ?>" class="trash-checkbox"></td>
                        <td>
                            <?php if ($item['image']): ?>
                                <img src="../uploads/<?= $item['image'] ?>" width="40" height="40" style="object-fit:cover; border-radius:4px">
                            <?php else: ?>
                                🍽️
                            <?php endif; ?>
                        </td>
                        <td><strong><?= htmlspecialchars($item['name']) ?></strong></td>
                        <td class="hide-mobile" style="font-size:0.8rem"><?= date('d M Y, H:i', strtotime($item['deleted_at'])) ?></td>
                        <td>
                            <div class="btn-grp">
                                <a href="trash.php?restore=<?= $item['id'] ?>" class="btn btn-primary btn-sm">♻️ <span>Restore</span></a>
                                <a href="trash.php?pdelete=<?= $item['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Permanently delete this dish?')">🗑️</a>
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
    const checkboxes = document.querySelectorAll('.trash-checkbox');
    const bulkControls = document.getElementById('bulk-controls');

    if (selectAll) {
        selectAll.addEventListener('change', function() {
            checkboxes.forEach(cb => cb.checked = this.checked);
            bulkControls.style.display = this.checked && checkboxes.length > 0 ? 'flex' : 'none';
        });
    }

    checkboxes.forEach(cb => {
        cb.addEventListener('change', function() {
            const checkedCount = document.querySelectorAll('.trash-checkbox:checked').length;
            bulkControls.style.display = checkedCount > 0 ? 'flex' : 'none';
        });
    });
});
</script>
</body>
</html>
