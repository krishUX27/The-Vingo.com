<?php
require_once __DIR__ . '/partials/auth_check.php';
require_once __DIR__ . '/../includes/db.php';

// Enable error reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);

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
$avail      = $_GET['avail']          ?? '';
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
if ($avail !== '') {
    $conditions[] = 'd.availability = ?';
    $params[]     = $avail;
    $types       .= 's';
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

/* ── Stats ── */
$total_dishes_res = $conn->query("SELECT COUNT(*) FROM dishes");
if (!$total_dishes_res) {
    dashboard_log("Stats Error (total_dishes): " . $conn->error);
}
$total_dishes = $total_dishes_res ? $total_dishes_res->fetch_row()[0] : 0;

$avail_cnt_res = $conn->query("SELECT COUNT(*) FROM dishes WHERE availability='Available'");
if (!$avail_cnt_res) {
    dashboard_log("Stats Error (avail_cnt): " . $conn->error);
}
$avail_cnt = $avail_cnt_res ? $avail_cnt_res->fetch_row()[0] : 0;

$total_cats_res = $conn->query("SELECT COUNT(*) FROM categories");
if (!$total_cats_res) {
    dashboard_log("Stats Error (total_cats): " . $conn->error);
}
$total_cats = $total_cats_res ? $total_cats_res->fetch_row()[0] : 0;

/* ── Handle Add Dish (Modal POST) ── */
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_dish') {
    $name         = trim($_POST['name']          ?? '');
    $price        = $_POST['price']              ?? '';
    $cat_id       = intval($_POST['category_id'] ?? 0);
    $availability = $_POST['availability']       ?? 'Available';
    $currency     = $_POST['currency']           ?? 'INR';
    $offer_id     = !empty($_POST['offer_id'])   ? (int)$_POST['offer_id'] : null;

    if ($name === '')                                           $errors[] = 'Dish name is required.';
    if ($price === '' || !is_numeric($price) || $price < 0)    $errors[] = 'A valid price is required.';
    if ($cat_id === 0)                                         $errors[] = 'Please select a category.';

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
        dashboard_log("Attempting to insert dish: $name, $price, $cat_id");
        $s = $conn->prepare("INSERT INTO dishes (name,price,category_id,image,availability,currency,offer_id) VALUES (?,?,?,?,?,?,?)");
        if (!$s) {
            $err_msg = 'Prepare failed: ' . $conn->error;
            dashboard_log($err_msg);
            $errors[] = $err_msg;
        } else {
            $s->bind_param('sdisssi', $name, $price, $cat_id, $image_name, $availability, $currency, $offer_id);
            if ($s->execute()) {
                $new_id = $conn->insert_id;
                dashboard_log("Dish '{$name}' added successfully. ID: {$new_id}");
                $_SESSION['flash'] = ['type' => 'success', 'msg' => "Dish '{$name}' added successfully!"];
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

/* ── Dish list ── */
$sql  = "SELECT d.*, c.name AS cat_name, o.title AS offer_title, o.discount AS offer_discount
         FROM dishes d
         JOIN categories c ON c.id = d.category_id
         LEFT JOIN seasonal_offers o ON o.id = d.offer_id
         {$where}
         ORDER BY d.created_at DESC";
$stmt = $conn->prepare($sql);
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$dishes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

/* ── Category dropdown for filter & modal ── */
$cat_res = $conn->query("SELECT * FROM categories ORDER BY name");
if (!$cat_res) {
    dashboard_log("Category query failed: " . $conn->error);
    $categories = [];
} else {
    $categories = $cat_res->fetch_all(MYSQLI_ASSOC);
}

/* ── Offers dropdown for modal ── */
$offer_res = $conn->query("SELECT id, title FROM seasonal_offers WHERE active=1 ORDER BY title");
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
</head>
<body>

<?php include __DIR__ . '/partials/sidebar.php'; ?>

<div class="main">
  <div class="topbar">
    <div class="topbar-left" style="display:flex; align-items:center; gap:16px">
      <div class="menu-toggle" id="menuToggle">☰</div>
      <div>
        <h1>Dashboard</h1>
        <p class="meta"><?= date('l, d F Y') ?></p>
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
        <?= $flash['type'] === 'success' ? '✅' : '❌' ?>
        <?= htmlspecialchars($flash['msg']) ?>
      </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
      <?php foreach ($errors as $e): ?>
        <div class="flash flash-danger">❌ <?= htmlspecialchars($e) ?></div>
      <?php endforeach; ?>
    <?php endif; ?>

    <!-- Stats -->
    <div class="stats-grid">
      <div class="stat-box">
        <div class="stat-icon si-purple">🍽️</div>
        <div><div class="stat-val"><?= $total_dishes ?></div><div class="stat-label">Total Dishes</div></div>
      </div>
      <div class="stat-box">
        <div class="stat-icon si-green">✅</div>
        <div><div class="stat-val"><?= $avail_cnt ?></div><div class="stat-label">Available</div></div>
      </div>
      <div class="stat-box">
        <div class="stat-icon si-red">❌</div>
        <div><div class="stat-val"><?= $total_dishes - $avail_cnt ?></div><div class="stat-label">Not Available</div></div>
      </div>
      <div class="stat-box">
        <div class="stat-icon si-orange">📂</div>
        <div><div class="stat-val"><?= $total_cats ?></div><div class="stat-label">Categories</div></div>
      </div>
    </div>

    <!-- Dish Table -->
    <div class="card">
      <div class="card-title">🍴 All Dishes</div>

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
            <label>Availability</label>
            <select name="avail">
              <option value="">All</option>
              <option value="Available"     <?= $avail==='Available'     ? 'selected':'' ?>>Available</option>
              <option value="Not Available" <?= $avail==='Not Available' ? 'selected':'' ?>>Not Available</option>
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

      <div class="btn-grp" style="margin-bottom:12px">
        <button type="button" class="btn btn-primary" id="open-add-dish">
           <span style="font-size:1rem">➕</span> Add New Dish
        </button>
      </div>

      <!-- Table -->
      <div class="table-wrap">
        <?php if (empty($dishes)): ?>
          <div class="no-data">
            <span class="nd-icon">🍽️</span>
            No dishes found. <a href="add-item.php">Add your first dish!</a>
          </div>
        <?php else: ?>
        <table>
          <thead>
            <tr>
              <th>#</th>
              <th>Image</th>
              <th>Name</th>
              <th>Price</th>
              <th>Category</th>
              <th>Availability</th>
              <th>Added</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php
              $new_id = $_GET['new_id'] ?? 0;
            ?>
            <?php foreach ($dishes as $i => $d): ?>
            <tr class="<?= ($d['id'] == $new_id) ? 'row-highlight' : '' ?>">
              <td><?= $i + 1 ?></td>
              <td>
                <?php if ($d['image'] && file_exists(__DIR__ . '/../uploads/' . $d['image'])): ?>
                  <img class="dish-img" src="../uploads/<?= htmlspecialchars($d['image']) ?>" alt="">
                <?php else: ?>
                  <div class="dish-placeholder">🍽️</div>
                <?php endif; ?>
              </td>
              <td><strong><?= htmlspecialchars($d['name']) ?></strong></td>
              <td><?= ($d['currency'] === 'USD' ? '$' : '₹') . number_format($d['price'], 2) ?></td>
              <td><span class="badge badge-info"><?= htmlspecialchars($d['cat_name']) ?></span></td>
              <td>
                <?php if ($d['availability'] === 'Available'): ?>
                  <span class="badge badge-success">✅ Available</span>
                <?php else: ?>
                  <span class="badge badge-danger">❌ Not Available</span>
                <?php endif; ?>
              </td>
              <td style="color:var(--muted);font-size:.78rem"><?= date('d M Y', strtotime($d['created_at'])) ?></td>
              <td>
                <div class="btn-grp">
                  <a href="edit-item.php?id=<?= $d['id'] ?>" class="btn btn-warn btn-sm">✏️ Edit</a>
                  <a href="delete-item.php?id=<?= $d['id'] ?>"
                     class="btn btn-danger btn-sm"
                     onclick="return confirm('Delete \'<?= addslashes(htmlspecialchars($d['name'])) ?>\'? This cannot be undone.')">
                    🗑️
                  </a>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <?php endif; ?>
      </div>
    </div>

  </div>
</div>

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
        <label>Category *</label>
        <select name="category_id" required>
          <option value="">-- Select Category --</option>
          <?php foreach ($categories as $c): ?>
            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Availability</label>
        <select name="availability">
          <option value="Available">Available</option>
          <option value="Not Available">Not Available</option>
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

  openBtn.addEventListener('click', showModal);
  closeBtn.addEventListener('click', hideModal);
  cancelBtn.addEventListener('click', hideModal);
  overlay.addEventListener('click', hideModal);

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

</body>
</html>

