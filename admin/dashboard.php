<?php
require_once __DIR__ . '/partials/auth_check.php';
require_once __DIR__ . '/../includes/db.php';

// One-time Schema Clean-up: Remove obsolete availability column
$chk = $conn->query("SHOW COLUMNS FROM dishes LIKE 'availability'");
if ($chk && $chk->num_rows > 0) { $conn->query("ALTER TABLE dishes DROP COLUMN availability"); }

// Legacy Fix: Drop old 'fk_dish_offer' if exists (it points to 'seasonal_offers' which is obsolete)
$conn->query("ALTER TABLE dishes DROP FOREIGN KEY IF EXISTS fk_dish_offer");

// Ensure modern columns exist (Fix for 500 errors if columns are missing)
$cols_to_add = [
    'user_id'             => "INT DEFAULT 0 AFTER id",
    'veg_type'            => "ENUM('veg','non_veg') DEFAULT 'veg' AFTER category_id",
    'available_breakfast' => "TINYINT(1) DEFAULT 1 AFTER veg_type",
    'available_lunch'     => "TINYINT(1) DEFAULT 1 AFTER available_breakfast",
    'available_dinner'    => "TINYINT(1) DEFAULT 1 AFTER available_lunch",
    'description'         => "TEXT AFTER price",
    'currency'            => "VARCHAR(10) DEFAULT 'INR' AFTER description",
    'offer_id'            => "INT DEFAULT NULL AFTER currency",
    'is_deleted'          => "TINYINT(1) DEFAULT 0 AFTER offer_id",
    'deleted_at'          => "DATETIME NULL AFTER is_deleted",
    'display_order'       => "INT DEFAULT 0 AFTER deleted_at"
];
// Ensure categories table is updated
$check_cat = $conn->query("SHOW COLUMNS FROM categories LIKE 'user_id'");
if ($check_cat && $check_cat->num_rows === 0) {
    $conn->query("ALTER TABLE categories ADD COLUMN user_id INT DEFAULT 0 AFTER name");
    $conn->query("ALTER TABLE categories ADD UNIQUE INDEX u_cat_user (user_id, name)");
}

// Ensure users table is updated (Account security)
$check_u_active = $conn->query("SHOW COLUMNS FROM users LIKE 'is_active'");
if ($check_u_active && $check_u_active->num_rows === 0) {
    $conn->query("ALTER TABLE users ADD COLUMN is_active TINYINT(1) DEFAULT 1");
}
$check_u_status = $conn->query("SHOW COLUMNS FROM users LIKE 'status'");
if ($check_u_status && $check_u_status->num_rows === 0) {
    $conn->query("ALTER TABLE users ADD COLUMN status VARCHAR(20) DEFAULT 'active'");
}

foreach($cols_to_add as $col => $def) {
    $check = $conn->query("SHOW COLUMNS FROM dishes LIKE '$col'");
    if($check && $check->num_rows === 0) {
        $conn->query("ALTER TABLE dishes ADD COLUMN $col $def");
    }
}

// Production Error Handling
ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_NOTICE);

// Custom logging function
function dashboard_log($msg) {
    $log_path = __DIR__ . '/debug.log';
    $time = date('Y-m-d H:i:s');
    file_put_contents($log_path, "[$time] $msg\n", FILE_APPEND);
}

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

dashboard_log("Dashboard hit. " . ($_SERVER['REQUEST_METHOD'] === 'POST' ? 'Action: ' . ($_POST['action'] ?? 'none') : 'GET request'));

/* ── Filters ── */
$search     = trim($_GET['q']         ?? '');
$cat_filter = intval($_GET['cat']     ?? 0);
$veg_type   = $_GET['veg_type']       ?? '';
$meal_time  = $_GET['meal_time']      ?? '';
$price_min  = $_GET['price_min']      ?? '';
$price_max  = $_GET['price_max']      ?? '';

$conditions = [];
$params     = [];
$types      = '';

if ($search !== '') {
    $conditions[] = 'd.name LIKE ?';
    $params[]     = "%{$search}%";
    $types       .= 's';
}
if ($cat_filter > 0) {
    $conditions[] = 'd.category_id = ?';
    $params[]     = $cat_filter;
    $types       .= 'i';
}
if ($veg_type !== '') {
    $conditions[] = 'd.veg_type = ?';
    $params[]     = $veg_type;
    $types       .= 's';
}
if ($meal_time !== '') {
    if ($meal_time === 'breakfast') $conditions[] = 'd.available_breakfast = 1';
    if ($meal_time === 'lunch')     $conditions[] = 'd.available_lunch = 1';
    if ($meal_time === 'dinner')    $conditions[] = 'd.available_dinner = 1';
}
if ($price_min !== '') {
    $conditions[] = 'd.price >= ?';
    $params[]     = (float)$price_min;
    $types       .= 'd';
}
if ($price_max !== '') {
    $conditions[] = 'd.price <= ?';
    $params[]     = (float)$price_max;
    $types       .= 'd';
}
$where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

// Current Admin Session ID
$admin_sess_id = (int)($_SESSION['admin_id'] ?? 0);

/* ── Stats (Filtered by User - Securely) ── */
function get_stat_count($conn, $sql, $uid) {
    try {
        $st = $conn->prepare($sql);
        if (!$st) return 0;
        $st->bind_param('i', $uid);
        $st->execute();
        $r = $st->get_result()->fetch_row();
        $st->close();
        return $r ? (int)$r[0] : 0;
    } catch (Exception $e) {
        dashboard_log("Stat Error: " . $e->getMessage());
        return 0;
    }
}

$total_dishes = get_stat_count($conn, "SELECT COUNT(*) FROM dishes WHERE user_id = ? AND is_deleted = 0", $admin_sess_id);
$total_cats   = get_stat_count($conn, "SELECT COUNT(*) FROM categories WHERE user_id = ? AND is_deleted = 0", $admin_sess_id);
$total_scans  = get_stat_count($conn, "SELECT COUNT(*) FROM qr_scan_logs WHERE admin_id = ?", $admin_sess_id);

/* ── Handle Add Dish (Modal POST) ── */
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_dish') {
    $name         = trim($_POST['name']          ?? '');
    $price        = $_POST['price']              ?? '';
    $cat_id       = intval($_POST['category_id'] ?? 0);
    $break        = isset($_POST['available_breakfast']) ? 1 : 0;
    $lunch        = isset($_POST['available_lunch'])     ? 1 : 0;
    $dinner       = isset($_POST['available_dinner'])    ? 1 : 0;
    $veg_type_val = $_POST['veg_type']           ?? 'veg';
    $currency     = $_POST['currency']           ?? 'INR';
    $offer_id     = !empty($_POST['offer_id'])   ? (int)$_POST['offer_id'] : null;

    if ($name === '')                                           $errors[] = 'Dish name is required.';
    if ($price === '' || !is_numeric($price) || $price < 0)    $errors[] = 'A valid price is required.';
    if ($cat_id === 0)                                         $errors[] = 'Please select a category.';
    
    // Verify Category Ownership
    if ($cat_id > 0) {
        $cat_check = $conn->prepare("SELECT id FROM categories WHERE id = ? AND user_id = ? AND is_deleted = 0");
        $cat_check->bind_param('ii', $cat_id, $admin_sess_id);
        $cat_check->execute();
        if ($cat_check->get_result()->num_rows === 0) {
            $errors[] = 'Invalid category selection.';
        }
        $cat_check->close();
    }

    // Check for duplicate dish name for this user (Case-Insensitive)
    if (empty($errors)) {
        $check_stmt = $conn->prepare("SELECT id FROM dishes WHERE LOWER(TRIM(name)) = LOWER(TRIM(?)) AND user_id = ? AND is_deleted = 0");
        $check_stmt->bind_param('si', $name, $admin_sess_id);
        $check_stmt->execute();
        if ($check_stmt->get_result()->num_rows > 0) {
            $errors[] = "This dish already exists.";
        }
        $check_stmt->close();
    }

    $image_name = null;
    if (!empty($_FILES['image']['name'])) {
        $f = $_FILES['image'];
        $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg','jpeg','png','gif','webp'])) {
            if ($f['size'] <= 3 * 1024 * 1024) {
                $image_name = uniqid('dish_', true) . '.' . $ext;
                if (!move_uploaded_file($f['tmp_name'], __DIR__ . '/../uploads/' . $image_name)) {
                    $image_name = null;
                }
            } else {
                $errors[] = 'Image must be < 3 MB.';
            }
        } else {
            $errors[] = 'Invalid image type.';
        }
    }

    if (empty($errors)) {
        dashboard_log("Attempting to insert dish: $name, $price, $cat_id, $veg_type_val for User $admin_sess_id");
        $s = $conn->prepare("INSERT INTO dishes (name,price,category_id,veg_type,available_breakfast,available_lunch,available_dinner,image,currency,offer_id,user_id) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
        if (!$s) {
            $err_msg = 'Prepare failed: ' . $conn->error;
            dashboard_log($err_msg);
            $errors[] = $err_msg;
        } else {
            $s->bind_param('sdisiiissii', $name, $price, $cat_id, $veg_type_val, $break, $lunch, $dinner, $image_name, $currency, $offer_id, $admin_sess_id);
            if ($s->execute()) {
                $new_id = $conn->insert_id;
                dashboard_log("Dish '{$name}' added successfully. ID: {$new_id}");
                $_SESSION['flash'] = ['type' => 'success', 'msg' => "Dish added successfully!"];
                header("Location: dashboard.php?new_id={$new_id}");
                exit;
            }
            $err_msg = 'DB execute error: ' . $conn->error;
            dashboard_log($err_msg);
            $errors[] = $err_msg;
            $s->close();
        }
    } else {
        dashboard_log("Validation errors: " . implode(', ', $errors));
    }
}

/* ── Handle Bulk Delete ── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'bulk_delete') {
    $ids = $_POST['dish_ids'] ?? [];
    if (!empty($ids)) {
        $id_placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $conn->prepare("UPDATE dishes SET is_deleted = 1, deleted_at = NOW() WHERE id IN ($id_placeholders) AND user_id = ?");
        $types = str_repeat('i', count($ids)) . 'i';
        $params = array_map('intval', $ids);
        $params[] = $admin_sess_id;
        $stmt->bind_param($types, ...$params);
        if ($stmt->execute()) {
            $_SESSION['flash'] = ['type' => 'success', 'msg' => count($ids) . " dishes moved to Trash."];
        }
        $stmt->close();
    }
    header("Location: dashboard.php");
    exit;
}

/* ── Dish list (Owner filtered) ── */
$sql  = "SELECT d.*, c.name AS cat_name, o.title AS offer_title, o.discount_percentage AS offer_discount
         FROM dishes d
         JOIN categories c ON c.id = d.category_id
         LEFT JOIN offers o ON o.id = d.offer_id AND o.offer_type = 'seasonal' AND o.is_deleted = 0
         WHERE d.user_id = ? AND d.is_deleted = 0 " . ($where ? "AND " . str_replace('WHERE','',$where) : "") . "
         ORDER BY d.display_order ASC, d.created_at DESC";
$stmt = $conn->prepare($sql);

// Add the user_id to the params array
array_unshift($params, $admin_sess_id);
$types = 'i' . $types;

if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$dishes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

/* ── Category dropdown (Owner filtered & Active) ── */
$cat_res = $conn->query("SELECT * FROM categories WHERE user_id = $admin_sess_id AND is_deleted = 0 ORDER BY name");
if (!$cat_res) {
    dashboard_log("Category query failed: " . $conn->error);
    $categories = [];
} else {
    $categories = $cat_res->fetch_all(MYSQLI_ASSOC);
}

/* ── Offers dropdown for modal (Owner filtered, Seasonal type only) ── */
$offer_res = $conn->query("SELECT id, title FROM offers WHERE user_id = $admin_sess_id AND status='active' AND offer_type='seasonal' AND is_deleted=0 ORDER BY title");
if (!$offer_res) {
    dashboard_log("Offers query failed: " . $conn->error);
    $offers = [];
} else {
    $offers = $offer_res->fetch_all(MYSQLI_ASSOC);
}

/* ── UI Rendering ── */
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Dashboard — Menu Manager</title>
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
        <h1>🍳 Kitchen Menu</h1>
        <p class="meta"><?= date('l, d F Y') ?></p>
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
      <div class="flash flash-<?= $flash['type'] ?>" style="margin-bottom:20px">
        <?= $flash['type'] === 'success' ? '✅' : '❌' ?>
        <?= htmlspecialchars($flash['msg']) ?>
      </div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="stats-grid">
      <div class="stat-box">
        <div class="stat-icon si-green">🍔</div>
        <div><div class="stat-val"><?= $total_dishes ?></div><div class="stat-label">Total Dishes</div></div>
      </div>
      <div class="stat-box">
        <div class="stat-icon si-red">📁</div>
        <div><div class="stat-val"><?= $total_cats ?></div><div class="stat-label">Categories</div></div>
      </div>
      <div class="stat-box">
        <div class="stat-icon si-blue">📱</div>
        <div><div class="stat-val" id="qr-scan-count"><?= $total_scans ?></div><div class="stat-label">QR Scan Count</div></div>
      </div>

    </div>

    <!-- Dish Table -->
    <div class="card">
      <div class="card-title" style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px">
        <span>🍴 All Dishes</span>
        <span id="reorder-hint" style="font-size:0.78rem; font-weight:500; color:#6366f1; background:#eef2ff; padding:5px 12px; border-radius:20px; display:flex; align-items:center; gap:6px">
          <span style="font-size:1rem">⠿</span> Drag rows to reorder the live menu
        </span>
      </div>

      <!-- Filter bar -->
      <form method="GET" action="">
        <div class="filter-bar">
          <div class="form-group">
            <label>Search</label>
            <input type="text" name="q" placeholder="Dish name…" value="<?= htmlspecialchars($search) ?>">
          </div>
          <div class="form-group">
            <label>Category</label>
            <select name="cat">
              <option value="">All Categories</option>
              <?php foreach ($categories as $c): ?>
                <option value="<?= $c['id'] ?>" <?= $cat_filter == $c['id'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars($c['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label>Dish Type</label>
            <select name="veg_type">
              <option value="">All</option>
              <option value="veg"     <?= $veg_type==='veg'     ? 'selected':'' ?>>🟢 Veg</option>
              <option value="non_veg" <?= $veg_type==='non_veg' ? 'selected':'' ?>>🔴 Non-Veg</option>
            </select>
          </div>
          <div class="form-group">
            <label>Meal Time</label>
            <select name="meal_time">
              <option value="">All Times</option>
              <option value="breakfast" <?= $meal_time==='breakfast' ? 'selected':'' ?>>🌅 Breakfast</option>
              <option value="lunch"     <?= $meal_time==='lunch'     ? 'selected':'' ?>>☀️ Lunch</option>
              <option value="dinner"    <?= $meal_time==='dinner'    ? 'selected':'' ?>>🌙 Dinner</option>
            </select>
          </div>
          <div class="form-group">
            <label>Min ₹</label>
            <input type="number" name="price_min" min="0" step="0.01" placeholder="0" value="<?= htmlspecialchars($price_min) ?>">
          </div>
          <div class="form-group">
            <label>Max ₹</label>
            <input type="number" name="price_max" min="0" step="0.01" placeholder="999" value="<?= htmlspecialchars($price_max) ?>">
          </div>
          <div class="filter-actions" style="display:flex; gap:8px">
            <button type="submit" class="btn btn-primary">🔍 Filter</button>
            <a href="dashboard.php" class="btn btn-outline">Reset</a>
          </div>
        </div>
      </form>

      <form method="POST" id="bulk-action-form">
        <input type="hidden" name="action" value="bulk_delete">
        <div class="btn-grp" style="margin-bottom:12px; display:flex; justify-content:space-between; align-items:center;">
          <div style="display:flex; gap:10px; align-items:center">
            <button type="button" class="btn btn-primary" id="open-add-dish">
               <span style="font-size:1rem">➕</span> Add New Dish
            </button>

          </div>
          <div id="bulk-controls" style="display:none; gap:10px; align-items:center;">
            <span id="select-count" style="font-size:0.85rem; color:var(--muted)">0 selected</span>
            <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Move selected items to Trash?')">
              🗑️ Delete Selected
            </button>
          </div>
        </div>

      <!-- Table -->
      <div class="table-wrap">
        <?php if (empty($dishes)): ?>
          <div class="no-data">
            <span class="nd-icon">🍽️</span>
            No dishes found. <a href="javascript:void(0)" id="open-add-first-dish">Add your first dish!</a>
          </div>
        <?php else: ?>
        <table>
          <thead>
            <tr>
              <th style="width:36px" title="Drag to reorder">⠿</th>
              <th><input type="checkbox" id="select-all"></th>
              <th>#</th>
              <th>Image</th>
              <th>Name</th>
              <th>Type</th>
              <th>Meal Times</th>
              <th>Price</th>
              <th>Category</th>
              <th>Added</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody id="dishes-tbody">
            <?php
              $new_id = $_GET['new_id'] ?? 0;
            ?>
            <?php foreach ($dishes as $i => $d): ?>
            <tr class="dish-row-sortable <?= ($d['id'] == $new_id) ? 'row-highlight' : '' ?>" data-id="<?= $d['id'] ?>">
              <td class="drag-handle" title="Drag to reorder" style="cursor:grab; text-align:center; color:#aaa; font-size:1.2rem; user-select:none">⠿</td>
              <td><input type="checkbox" name="dish_ids[]" value="<?= $d['id'] ?>" class="dish-checkbox"></td>
              <td class="row-num"><?= $i + 1 ?></td>
              <td>
                <?php if ($d['image'] && file_exists(__DIR__ . '/../uploads/' . $d['image'])): ?>
                  <img class="dish-img" src="../uploads/<?= htmlspecialchars($d['image']) ?>" alt="">
                <?php else: ?>
                  <div class="dish-placeholder">🍽️</div>
                <?php endif; ?>
              </td>
              <td><strong><?= htmlspecialchars($d['name']) ?></strong></td>
              <td>
                <?php if ($d['veg_type'] === 'veg'): ?>
                  <span class="badge badge-success" style="background:#dcfce7; color:#166534">🟢 Veg</span>
                <?php else: ?>
                  <span class="badge badge-danger" style="background:#fee2e2; color:#991b1b">🔴 Non-Veg</span>
                <?php endif; ?>
              </td>
              <td>
                <div style="display:flex; flex-wrap:wrap; gap:4px; max-width:140px">
                  <?php if ($d['available_breakfast']): ?><span class="badge" style="background:#fef3c7; color:#92400e; font-size:0.65rem">🌅 B</span><?php endif; ?>
                  <?php if ($d['available_lunch']):     ?><span class="badge" style="background:#ffedd5; color:#9a3412; font-size:0.65rem">☀️ L</span><?php endif; ?>
                  <?php if ($d['available_dinner']):    ?><span class="badge" style="background:#ede9fe; color:#5b21b6; font-size:0.65rem">🌙 D</span><?php endif; ?>
                </div>
              </td>
              <td><?= ($d['currency'] === 'USD' ? '$' : '₹') . number_format($d['price'], 2) ?></td>
              <td><span class="badge badge-info"><?= htmlspecialchars($d['cat_name']) ?></span></td>
              <td style="color:var(--muted);font-size:.78rem"><?= date('d M Y', strtotime($d['created_at'])) ?></td>
              <td>
                <div class="btn-grp">
                  <a href="edit-item.php?id=<?= $d['id'] ?>" class="btn btn-warn btn-sm">✏️ Edit</a>
                  <a href="delete-item.php?id=<?= $d['id'] ?>"
                     class="btn btn-danger btn-sm"
                     title="Move to Trash"
                     onclick="return confirm('Move \'<?= addslashes(htmlspecialchars($d['name'])) ?>\' to Trash?')">
                    🗑️
                  </a>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        </form>
        <?php endif; ?>
      </div>
    </div>

  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Modal script (already there, but I need to make sure I don't break it)
    // Actually, I'll just append my script.
    
    const selectAll = document.getElementById('select-all');
    const checkboxes = document.querySelectorAll('.dish-checkbox');
    const bulkControls = document.getElementById('bulk-controls');
    const selectCountDisplay = document.getElementById('select-count');

    function updateBulkUI() {
        const checkedCount = document.querySelectorAll('.dish-checkbox:checked').length;
        if (bulkControls) bulkControls.style.display = checkedCount > 0 ? 'flex' : 'none';
        if (selectCountDisplay) selectCountDisplay.textContent = `${checkedCount} selected`;
    }

    if (selectAll) {
        selectAll.addEventListener('change', function() {
            checkboxes.forEach(cb => cb.checked = this.checked);
            updateBulkUI();
        });
    }

    checkboxes.forEach(cb => {
        cb.addEventListener('change', updateBulkUI);
    });

    // Auto-scroll to highlighted row if exists
    const highlightedRow = document.querySelector('.row-highlight');
    if (highlightedRow) {
        setTimeout(() => {
            highlightedRow.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }, 500);
    }
});
</script>

<!-- Add Dish Modal -->
<div id="dish-modal-overlay" class="modal-overlay"></div>
<div id="dish-modal" class="modal">
  <div class="modal-header">
    <h3>🍽️ Add New Dish</h3>
    <button type="button" class="modal-close" id="close-dish-modal">&times;</button>
  </div>
  <form method="POST" enctype="multipart/form-data">
    <input type="hidden" name="action" value="add_dish">
    <div class="modal-body">
      <?php if (!empty($errors)): ?>
        <div class="error-summary" style="margin-bottom: 20px; border-radius:12px; background:#fff1f1; border:1px solid #fecaca; padding:15px; color:#b91c1c; font-size:0.88rem; font-weight:600">
          <?php foreach ($errors as $e): ?>
            <div style="display:flex; gap:8px; margin-bottom:4px"><span>❌</span> <?= htmlspecialchars($e) ?></div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <div class="form-group">
        <label>Dish Name *</label>
        <input type="text" name="name" required placeholder="e.g. Chicken Curry">
      </div>
      <div class="form-group">
        <label>Price *</label>
        <div style="display:grid; grid-template-columns: 80px 1fr; gap: 10px">
          <select name="currency" required>
            <option value="INR">INR (₹)</option>
            <option value="USD">USD ($)</option>
          </select>
          <input type="number" name="price" required min="0" step="0.01" placeholder="0.00">
        </div>
      </div>
      <div class="form-group">
        <label>Dish Type (Veg / Non-Veg) *</label>
        <select name="veg_type" required>
          <option value="veg" selected>🟢 Veg (Vegetarian)</option>
          <option value="non_veg">🔴 Non-Veg (Non-Vegetarian)</option>
        </select>
      </div>

      <div class="form-group">
        <label>Available During (Meal Times)</label>
        <div style="display:flex; gap:15px; margin-top:5px; background:#f8fafc; padding:10px; border-radius:8px; border:1px solid var(--border)">
          <label style="display:flex; align-items:center; gap:5px; font-weight:normal; margin:0; cursor:pointer">
            <input type="checkbox" name="available_breakfast" checked> Breakfast
          </label>
          <label style="display:flex; align-items:center; gap:5px; font-weight:normal; margin:0; cursor:pointer">
            <input type="checkbox" name="available_lunch" checked> Lunch
          </label>
          <label style="display:flex; align-items:center; gap:5px; font-weight:normal; margin:0; cursor:pointer">
            <input type="checkbox" name="available_dinner" checked> Dinner
          </label>
        </div>
      </div>
      <div class="form-group">
        <label>Category *</label>
        <select name="category_id" required>
          <option value="">-- Select Category --</option>
          <?php foreach ($categories as $c): ?>
            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Seasonal Offer (Optional)</label>
        <select name="offer_id">
          <option value="">No Active Offer</option>
          <?php foreach ($offers as $o): ?>
            <option value="<?= $o['id'] ?>"><?= htmlspecialchars($o['title']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Dish Image</label>
        <input type="file" id="dish-image-input" name="image" accept="image/*">
        <div id="dish-preview-wrap" style="display:none;margin-top:10px">
          <label>Preview</label>
          <img id="dish-img-preview" src="" alt="" class="img-thumb" style="max-width:100px;display:block">
        </div>
      </div>
    </div>
    <div class="modal-footer">
      <button type="submit" class="btn btn-primary">💾 Save Dish</button>
      <button type="button" class="btn btn-outline" id="cancel-dish-modal">Cancel</button>
    </div>
  </form>
</div>

<script>
  const modal = document.getElementById('dish-modal');
  const overlay = document.getElementById('dish-modal-overlay');
  const openBtn = document.getElementById('open-add-dish');
  const closeBtn = document.getElementById('close-dish-modal');
  const cancelBtn = document.getElementById('cancel-dish-modal');
  const imageInput = document.getElementById('dish-image-input');
  const previewImg = document.getElementById('dish-img-preview');
  const previewWrap = document.getElementById('dish-preview-wrap');

  function showModal() {
    modal.classList.add('open');
    overlay.classList.add('open');
  }

  function hideModal() {
    modal.classList.remove('open');
    overlay.classList.remove('open');
    // Clear preview on close
    imageInput.value = '';
    previewImg.src = '';
    previewWrap.style.display = 'none';
  }

  imageInput.addEventListener('change', function() {
    const f = this.files[0];
    if (!f) return;
    const r = new FileReader();
    r.onload = e => {
      previewImg.src = e.target.result;
      previewWrap.style.display = 'block';
    };
    r.readAsDataURL(f);
  });

  if (openBtn) openBtn.addEventListener('click', showModal);
  const openFirstBtn = document.getElementById('open-add-first-dish');
  if (openFirstBtn) openFirstBtn.addEventListener('click', showModal);
  
  closeBtn.addEventListener('click', hideModal);
  cancelBtn.addEventListener('click', hideModal);

  // Auto-open modal if URL contains ?add=1 or there are errors
  if (window.location.search.includes('add=1')) {
    showModal();
    // Clear the URL parameter so it doesn't reappear on reload
    window.history.replaceState({}, document.title, window.location.pathname);
  }
  
  <?php if (!empty($errors)): ?>
    showModal();
  <?php endif; ?>
</script>

<script>
  // Dynamic Analytics Refresh
  async function refreshQRAnalytics() {
    try {
      const response = await fetch('../api/analytics.php');
      const data = await response.json();
      if (data.success) {
        const el = document.getElementById('qr-scan-count');
        if (el) el.innerText = data.qr_scan_count;
      }
    } catch (err) {
      console.error('Failed to fetch analytics:', err);
    }
  }

  // Initial refresh and periodic update (every 30 seconds)
  refreshQRAnalytics();
  setInterval(refreshQRAnalytics, 30000);
</script>

<!-- ═══════════════════════════════════════════════════════════════════════════
     Drag-and-Drop Reordering (Admin Only — SortableJS)
═════════════════════════════════════════════════════════════════════════════ -->
<style>
  /* Drag handle cursor */
  .drag-handle { cursor: grab !important; }
  .drag-handle:active { cursor: grabbing !important; }

  /* Ghost row while dragging */
  .sortable-ghost {
    opacity: 0.4;
    background: #eef2ff !important;
  }

  /* Chosen (picked-up) row */
  .sortable-chosen {
    background: #f5f3ff !important;
    box-shadow: 0 8px 24px rgba(99,102,241,0.18);
    transform: scale(1.01);
    transition: transform 0.15s ease;
  }

  /* Drag row animation */
  .dish-row-sortable { transition: background 0.2s; }

  /* Save order toast */
  #order-toast {
    position: fixed;
    bottom: 24px;
    right: 24px;
    min-width: 240px;
    padding: 14px 20px;
    border-radius: 14px;
    font-size: 0.875rem;
    font-weight: 600;
    font-family: inherit;
    z-index: 9999;
    box-shadow: 0 8px 30px rgba(0,0,0,0.15);
    display: flex;
    align-items: center;
    gap: 10px;
    transform: translateY(80px);
    opacity: 0;
    pointer-events: none;
    transition: transform 0.3s cubic-bezier(0.16,1,0.3,1), opacity 0.3s ease;
  }
  #order-toast.show {
    transform: translateY(0);
    opacity: 1;
    pointer-events: auto;
  }
  #order-toast.toast-success { background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; }
  #order-toast.toast-error   { background: #fff1f2; color: #9f1239; border: 1px solid #fecdd3; }
  #order-toast.toast-saving  { background: #eef2ff; color: #3730a3; border: 1px solid #c7d2fe; }
</style>

<!-- Toast element -->
<div id="order-toast" role="status" aria-live="polite"></div>

<!-- SortableJS CDN (lightweight, no framework needed) -->
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>

<script>
(function() {
  'use strict';

  /* ── Toast Helper ────────────────────────────────────────────────────── */
  const toast = document.getElementById('order-toast');
  let _toastTimer;

  function showOrderToast(msg, type /* 'success' | 'error' | 'saving' */, duration = 3000) {
    if (!toast) return;
    clearTimeout(_toastTimer);
    const icons = { success: '✅', error: '❌', saving: '⏳' };
    toast.innerHTML = `<span style="font-size:1.1rem">${icons[type] || ''}</span> ${msg}`;
    toast.className = `show toast-${type}`;
    if (duration > 0) {
      _toastTimer = setTimeout(() => toast.classList.remove('show'), duration);
    }
  }

  /* ── Re-number visible row indices after sort ──────────────────────── */
  function renumberRows() {
    document.querySelectorAll('#dishes-tbody tr.dish-row-sortable').forEach((tr, i) => {
      const numCell = tr.querySelector('.row-num');
      if (numCell) numCell.textContent = i + 1;
    });
  }

  /* ── Save order via AJAX ─────────────────────────────────────────────── */
  async function saveOrder(orderedIds) {
    showOrderToast('Saving new order…', 'saving', 0);
    try {
      const res = await fetch('../api/update_order.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ order: orderedIds })
      });
      const json = await res.json();
      if (json.success) {
        showOrderToast('Menu order saved! Live menu updated.', 'success');
      } else {
        showOrderToast('Save failed: ' + (json.error || 'Unknown error'), 'error');
        console.error('[Reorder]', json);
      }
    } catch (err) {
      showOrderToast('Network error. Please try again.', 'error');
      console.error('[Reorder] Fetch error:', err);
    }
  }

  /* ── Initialise SortableJS ───────────────────────────────────────────── */
  const tbody = document.getElementById('dishes-tbody');
  if (tbody) {
    Sortable.create(tbody, {
      handle: '.drag-handle',        // Only draggable via the handle icon
      animation: 180,               // Smooth swap animation (ms)
      easing: 'cubic-bezier(0.25, 1, 0.5, 1)',
      ghostClass: 'sortable-ghost', // Ghost placeholder class
      chosenClass: 'sortable-chosen', // Class on picked-up element
      dragClass: 'sortable-drag',
      forceFallback: false,
      onEnd: function(evt) {
        renumberRows();

        // Collect the new ordered list of dish IDs
        const orderedIds = Array.from(
          tbody.querySelectorAll('tr.dish-row-sortable[data-id]')
        ).map(tr => parseInt(tr.getAttribute('data-id'), 10));

        saveOrder(orderedIds);
      }
    });
  }

})();
</script>
</body>
</html>

