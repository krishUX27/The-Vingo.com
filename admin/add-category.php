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
            // Check for duplicate for THIS user only
            $chk = $conn->prepare("SELECT id FROM categories WHERE name = ? AND user_id = ?");
            $chk->bind_param('si', $name, $admin_sess_id);
            $chk->execute();
            if ($chk->get_result()->num_rows > 0) {
                $errors[] = "'{$name}' already exists in your menu.";
            } else {
                $ins = $conn->prepare("INSERT INTO categories (name, user_id) VALUES (?, ?)");
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
    "SELECT c.*, (SELECT COUNT(*) FROM dishes WHERE category_id = c.id) AS dish_count
     FROM categories c
     WHERE c.user_id = $admin_sess_id
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
      <a href="../menu.php" target="_blank" class="btn btn-outline btn-sm">
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

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;align-items:start">

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
                $cnt = $conn->query("SELECT COUNT(*) FROM dishes WHERE category_id = {$c['id']}")->fetch_row()[0];
              ?>
              <li>
                <div class="cat-name-box">
                  <span>📂</span> <?= htmlspecialchars($c['name']) ?>
                </div>
                
                    <span class="badge badge-info"><?= $cnt ?> dishes</span>
                    <a href="delete-category.php?id=<?= $c['id'] ?>" 
                       class="btn btn-danger btn-sm"
                       style="padding:6px 12px; border-radius:10px"
                       onclick="return confirm('Delete \'<?= addslashes(htmlspecialchars($c['name'])) ?>\'?')">
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

</body>
</html>

