<?php
// admin/add-category.php — Manage categories
require_once __DIR__ . '/partials/auth_check.php';
require_once __DIR__ . '/../includes/db.php';

$flash  = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
$errors = [];

$admin_sess_id = $_SESSION['admin_id'] ?? 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    /* ── Add ── */
    if ($action === 'add') {
        $name = trim($_POST['cat_name'] ?? '');
        if ($name === '') {
            $errors[] = 'Category name is required.';
        } else {
            // Check for duplicate for THIS user only (Active and Deleted)
            $chk = $conn->prepare("SELECT id, is_deleted FROM categories WHERE name = ? AND user_id = ?");
            $chk->bind_param('si', $name, $admin_sess_id);
            $chk->execute();
            $res = $chk->get_result();
            
            if ($res->num_rows > 0) {
                $existing = $res->fetch_assoc();
                if ($existing['is_deleted'] == 1) {
                    // Reactivate soft-deleted category
                    $upd = $conn->prepare("UPDATE categories SET is_deleted = 0, deleted_at = NULL WHERE id = ?");
                    $upd->bind_param('i', $existing['id']);
                    $upd->execute();
                    $upd->close();
                    $_SESSION['flash'] = ['type' => 'success', 'msg' => "Category '{$name}' restored."];
                    header('Location: add-category.php');
                    exit;
                } else {
                    $errors[] = "'{$name}' already exists in your menu.";
                }
            } else {
                $ins = $conn->prepare("INSERT INTO categories (name, user_id, is_deleted) VALUES (?, ?, 0)");
                $ins->bind_param('si', $name, $admin_sess_id);
                $ins->execute();
                $ins->close();
                $_SESSION['flash'] = ['type' => 'success', 'msg' => "Category '{$name}' added."];
                header('Location: add-category.php');
                exit;
            }
            $chk->close();
        }
    }
    
    /* ── Edit ── */
    if ($action === 'edit') {
        $eid  = intval($_POST['cat_id'] ?? 0);
        $name = trim($_POST['cat_name'] ?? '');
        if ($name === '') {
            $errors[] = 'Category name is required.';
        } else {
            // Check for duplicate for THIS user only (excluding current)
            $chk = $conn->prepare("SELECT id FROM categories WHERE name = ? AND user_id = ? AND id != ? AND is_deleted = 0");
            $chk->bind_param('sii', $name, $admin_sess_id, $eid);
            $chk->execute();
            if ($chk->get_result()->num_rows > 0) {
                $errors[] = "'{$name}' already exists in your menu.";
            } else {
                $upd = $conn->prepare("UPDATE categories SET name = ? WHERE id = ? AND user_id = ?");
                $upd->bind_param('sii', $name, $eid, $admin_sess_id);
                $upd->execute();
                $upd->close();
                $_SESSION['flash'] = ['type' => 'success', 'msg' => "Category updated to '{$name}'."];
                header('Location: add-category.php');
                exit;
            }
            $chk->close();
        }
    }

    /* ── Delete ── */
    if ($action === 'delete') {
        $did = intval($_POST['cat_id'] ?? 0);
        // Verify ownership and check count
        $used = $conn->prepare("SELECT COUNT(*) FROM dishes WHERE category_id = ? AND user_id = ?");
        $used->bind_param('ii', $did, $admin_sess_id);
        $used->execute();
        $count = $used->get_result()->fetch_row()[0];
        $used->close();

        if ($count > 0) {
            $errors[] = "Cannot delete — {$count} dish(es) belong to this category.";
        } else {
            $del = $conn->prepare("DELETE FROM categories WHERE id = ? AND user_id = ?");
            $del->bind_param('ii', $did, $admin_sess_id);
            $del->execute();
            $del->close();
            $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Category deleted.'];
            header('Location: add-category.php');
            exit;
        }
    }
}

$categories = $conn->query(
    "SELECT c.*, (SELECT COUNT(*) FROM dishes WHERE category_id = c.id AND is_deleted = 0) AS dish_count
     FROM categories c
     WHERE c.user_id = $admin_sess_id AND c.is_deleted = 0
     ORDER BY c.name"
)->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Categories — Menu Manager</title>
  <link rel="stylesheet" href="../assets/css/menu-style.css?v=<?= time() ?>">
  <link rel="icon" type="image/png" href="../assets/images/favicon.png">
  <style>
    .modal {
      display: none;
      position: fixed;
      z-index: 2000;
      left: 0; top: 0; width: 100%; height: 100%;
      background: rgba(15, 23, 42, 0.6);
      backdrop-filter: blur(4px);
      align-items: center;
      justify-content: center;
      padding: 20px;
    }
    .modal.active { display: flex; }
    .cat-actions { display: flex; gap: 8px; align-items: center; }
    .btn-icon { width: 36px; height: 36px; display: flex; align-items: center; justify-content: center; padding: 0; border-radius: 10px; }
  </style>
</head>
<body>

<?php include __DIR__ . '/partials/sidebar.php'; ?>

<div class="main">
  <div class="topbar">
    <div class="topbar-left" style="display:flex; align-items:center; gap:16px">
      <div class="menu-toggle" id="menuToggle">☰</div>
      <div>
        <h1>Categories</h1>
        <p class="meta">Manage your menu sections</p>
      </div>
    </div>
    <div class="topbar-right" style="display:flex; gap:16px; align-items:center">
      <a href="../menu.php?id=<?= $admin_sess_id ?>" target="_blank" class="btn btn-outline btn-sm">
        <span class="live-dot"></span> Live Menu View
      </a>
      <?php include __DIR__ . '/partials/topbar_user.php'; ?>
    </div>
  </div>
  <div class="content">

    <?php if ($flash): ?>
      <div class="flash flash-<?= $flash['type'] ?>">
        <?= $flash['type']==='success' ? '✅' : '❌' ?> <?= htmlspecialchars($flash['msg']) ?>
      </div>
    <?php endif; ?>

    <div class="grid-2" style="align-items:start">

      <!-- Add Form -->
      <div class="card">
        <div class="card-title">➕ Add Category</div>
        <?php foreach ($errors as $e): ?>
          <div class="flash flash-danger">❌ <?= htmlspecialchars($e) ?></div>
        <?php endforeach; ?>
        <form method="POST">
          <input type="hidden" name="action" value="add">
          <div class="form-group" style="margin-bottom:14px">
            <label for="cat_name">Category Name <span class="req">*</span></label>
            <input type="text" id="cat_name" name="cat_name" placeholder="e.g. Soups" required>
          </div>
          <button type="submit" class="btn btn-primary">
            <span style="font-size:1rem">💾</span> Add Category
          </button>
        </form>
      </div>

      <!-- List -->
      <div class="card">
        <div class="card-title">📋 All Categories (<?= count($categories) ?>)</div>
        <?php if (empty($categories)): ?>
          <div class="no-data"><span class="nd-icon">📂</span>No categories yet.</div>
        <?php else: ?>
          <ul class="cat-list">
            <?php foreach ($categories as $c): ?>
              <?php
                $cnt = $c['dish_count']; 
              ?>
              <li>
                <div class="cat-name-box">
                  <span>📂</span> <?= htmlspecialchars($c['name']) ?>
                </div>
                
                <div class="cat-actions">
                    <span class="badge badge-info"><?= $cnt ?> dishes</span>
                    <button class="btn btn-primary btn-sm btn-icon" 
                            onclick="openEditModal(<?= $c['id'] ?>, '<?= addslashes(htmlspecialchars($c['name'])) ?>')"
                            title="Edit">
                      ✏️
                    </button>
                    <a href="delete-category.php?id=<?= $c['id'] ?>" 
                       class="btn btn-danger btn-sm btn-icon"
                       onclick="return confirm('Delete \'<?= addslashes(htmlspecialchars($c['name'])) ?>\'?')"
                       title="Delete">
                      🗑️
                    </a>
                </div>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </div>

    </div>

  </div>
</div>

<!-- Edit Modal -->
<div id="editModal" class="modal">
  <div class="card" style="max-width:400px; width:100%; margin:0; border:none; box-shadow: 0 20px 50px rgba(0,0,0,0.2)">
    <div class="card-title" style="margin-bottom:20px">✏️ Edit Category</div>
    <form method="POST">
      <input type="hidden" name="action" value="edit">
      <input type="hidden" id="edit_cat_id" name="cat_id">
      <div class="form-group" style="margin-bottom:20px">
        <label for="edit_cat_name">Category Name <span class="req">*</span></label>
        <input type="text" id="edit_cat_name" name="cat_name" required>
      </div>
      <div style="display:flex; gap:12px">
        <button type="submit" class="btn btn-primary" style="flex:1">💾 Save Changes</button>
        <button type="button" class="btn btn-outline" style="flex:1" onclick="closeEditModal()">Cancel</button>
      </div>
    </form>
  </div>
</div>

<script>
  function openEditModal(id, name) {
    document.getElementById('edit_cat_id').value = id;
    document.getElementById('edit_cat_name').value = name;
    document.getElementById('editModal').classList.add('active');
    document.getElementById('edit_cat_name').focus();
  }
  function closeEditModal() {
    document.getElementById('editModal').classList.remove('active');
  }
  // Close on outside click
  window.onclick = function(event) {
    const modal = document.getElementById('editModal');
    if (event.target == modal) closeEditModal();
  }
</script>

</body>
</html>

