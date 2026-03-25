<?php
// admin/index.php — Dashboard
require_once __DIR__ . '/../includes/db.php';
session_start();

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

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
$total_dishes = $conn->query("SELECT COUNT(*) FROM dishes")->fetch_row()[0];
$avail_cnt    = $conn->query("SELECT COUNT(*) FROM dishes WHERE availability='Available'")->fetch_row()[0];
$total_cats   = $conn->query("SELECT COUNT(*) FROM categories")->fetch_row()[0];

/* ── Dish list ── */
$sql  = "SELECT d.*, c.name AS cat_name
         FROM dishes d
         JOIN categories c ON c.id = d.category_id
         {$where}
         ORDER BY d.created_at DESC";
$stmt = $conn->prepare($sql);
if ($params) $stmt->bind_param($types, ...$params);
$stmt->execute();
$dishes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

/* ── Category dropdown for filter ── */
$categories = $conn->query("SELECT * FROM categories ORDER BY name")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Dashboard — Menu Manager</title>
  <link rel="stylesheet" href="../assets/css/menu-style.css">
</head>
<body>

<?php include __DIR__ . '/partials/sidebar.php'; ?>

<div class="main">
  <div class="topbar">
    <h1>📊 Dashboard</h1>
    <div class="meta">
      <?= date('l, d M Y') ?> &nbsp;|&nbsp;
      <a href="../menu.php" target="_blank"
         style="color:var(--accent);font-weight:600;text-decoration:none">
        <span class="live-dot"></span> View Live Menu
      </a>
    </div>
  </div>

  <div class="content">

    <?php if ($flash): ?>
      <div class="flash flash-<?= $flash['type'] ?>">
        <?= $flash['type'] === 'success' ? '✅' : '❌' ?>
        <?= htmlspecialchars($flash['msg']) ?>
      </div>
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
          <div class="form-group" style="justify-content:flex-end">
            <button type="submit" class="btn btn-primary">🔍 Filter</button>
            <a href="dashboard.php" class="btn btn-outline" style="margin-top:5px">Reset</a>
          </div>
        </div>
      </form>

      <div class="btn-grp" style="margin-bottom:16px">
        <a href="add-item.php" class="btn btn-primary">➕ Add Dish</a>
        <a href="../generate_pdf.php" class="btn btn-danger" target="_blank">📄 Download PDF</a>
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
            <?php foreach ($dishes as $i => $d): ?>
            <tr>
              <td><?= $i + 1 ?></td>
              <td>
                <?php if ($d['image'] && file_exists(__DIR__ . '/../uploads/' . $d['image'])): ?>
                  <img class="dish-img" src="../uploads/<?= htmlspecialchars($d['image']) ?>" alt="">
                <?php else: ?>
                  <div class="dish-placeholder">🍽️</div>
                <?php endif; ?>
              </td>
              <td><strong><?= htmlspecialchars($d['name']) ?></strong></td>
              <td>₹<?= number_format($d['price'], 2) ?></td>
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

</body>
</html>

