<?php
// admin/seasonal-offers.php — Manage seasonal deals
require_once __DIR__ . '/partials/auth_check.php';
require_once __DIR__ . '/../includes/db.php';

$flash  = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

// Production Error Handling
ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_NOTICE);

// Custom logging function
function offers_log($msg) {
    $log_path = __DIR__ . '/debug.log';
    $time = date('Y-m-d H:i:s');
    file_put_contents($log_path, "[$time] [OFFERS] $msg\n", FILE_APPEND);
}

$errors = [];

$admin_sess_id = $_SESSION['admin_sess_id'] ?? ($_SESSION['admin_id'] ?? 0);

// Robust Schema Auto-Fix (Version Agnostic)
$check = $conn->query("SHOW COLUMNS FROM seasonal_offers LIKE 'is_deleted'");
if ($check && $check->num_rows === 0) {
    $conn->query("ALTER TABLE seasonal_offers ADD is_deleted TINYINT(1) DEFAULT 0 AFTER discount");
    $conn->query("ALTER TABLE seasonal_offers ADD deleted_at DATETIME NULL AFTER is_deleted");
}

// ADD OFFER
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    $title       = trim($_POST['title'] ?? '');
    $desc        = trim($_POST['description'] ?? '');
    $discount    = trim($_POST['discount'] ?? '');
    $expires_at  = trim($_POST['expires_at'] ?? '');

    if (empty($title)) $errors[] = 'Offer title is required.';

    if (empty($errors)) {
        $exp = !empty($expires_at) ? $expires_at : null;
        $s = $conn->prepare("INSERT INTO seasonal_offers (title, description, discount, expires_at, user_id) VALUES (?, ?, ?, ?, ?)");
        if (!$s) {
            $err_msg = "Prepare failed: " . $conn->error;
            offers_log($err_msg);
            $errors[] = $err_msg;
        } else {
            $s->bind_param('ssssi', $title, $desc, $discount, $exp, $admin_sess_id);
            if ($s->execute()) {
                $_SESSION['flash'] = ['type' => 'success', 'msg' => "Offer '{$title}' added!"];
                header('Location: seasonal-offers.php');
                exit;
            }
            $err_msg = "Execute failed: " . $conn->error;
            offers_log($err_msg);
            $errors[] = $err_msg;
            $s->close();
        }
    }
}

// DELETE OFFER (Soft Delete)
if (isset($_GET['del'])) {
    $del_id = (int)$_GET['del'];
    $conn->query("UPDATE seasonal_offers SET is_deleted = 1, deleted_at = NOW() WHERE id = $del_id AND user_id = $admin_sess_id");
    $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Offer moved to Trash.'];
    header('Location: seasonal-offers.php');
    exit;
}

$offers_res = $conn->query("SELECT * FROM seasonal_offers WHERE user_id = $admin_sess_id AND is_deleted = 0 ORDER BY created_at DESC");
$offers = ($offers_res) ? $offers_res->fetch_all(MYSQLI_ASSOC) : [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Seasonal Offers — Vingo Menu</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="../assets/css/menu-style.css?v=<?= time() ?>">
</head>
<body>

<?php include __DIR__ . '/partials/sidebar.php'; ?>

<div class="main">
  <div class="topbar">
    <div class="topbar-left" style="display:flex; align-items:center; gap:16px">
      <div class="menu-toggle" id="menuToggle">☰</div>
      <div>
        <h1>🎁 Seasonal Offers</h1>
        <p class="meta">Manage deals and special discounts</p>
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
        <div class="card-title">✨ Create New Offer</div>
        <?php foreach ($errors as $e): ?>
          <div class="flash flash-danger">❌ <?= htmlspecialchars($e) ?></div>
        <?php endforeach; ?>

        <form method="POST">
          <input type="hidden" name="action" value="add">
          
          <div class="form-group" style="margin-bottom:16px">
            <label>Offer Title *</label>
            <input type="text" name="title" placeholder="e.g. Summer Cooler Special" required>
          </div>

          <div class="form-group" style="margin-bottom:16px">
            <label>Description</label>
            <textarea name="description" placeholder="Short details about the offer..." rows="3" style="width:100%; border-radius:12px; border:1px solid var(--border); padding:12px; font-family:inherit"></textarea>
          </div>

          <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-bottom:20px">
            <div class="form-group">
              <label>Discount Label</label>
              <input type="text" name="discount" placeholder="e.g. 20% OFF">
            </div>
            <div class="form-group">
              <label>Expiry Date</label>
              <input type="date" name="expires_at">
            </div>
          </div>

          <button type="submit" class="btn btn-primary" style="width:100%; justify-content:center">
             <span style="font-size:1rem">💾</span> Save Offer
          </button>
        </form>
      </div>

      <!-- List -->
      <div class="card">
        <div class="card-title">📋 Active Offers (<?= count($offers) ?>)</div>
        <?php if (empty($offers)): ?>
          <div class="no-data"><span class="nd-icon">🎁</span>No active offers found.</div>
        <?php else: ?>
          <ul class="cat-list">
            <?php foreach ($offers as $o): ?>
              <li>
                <div class="cat-name-box">
                  <div style="display:flex; flex-direction:column">
                    <span style="font-weight:700; color:#0f172a"><?= htmlspecialchars($o['title']) ?></span>
                    <span style="font-size:0.75rem; color:var(--text-light)"><?= $o['discount'] ?: 'No discount' ?></span>
                  </div>
                </div>
                
                <div class="cat-actions">
                  <?php if ($o['expires_at']): ?>
                    <span class="badge badge-info" style="font-size:0.6rem">Ends: <?= date('d M', strtotime($o['expires_at'])) ?></span>
                  <?php endif; ?>
                  
                  <a href="seasonal-offers.php?del=<?= $o['id'] ?>" 
                     class="btn btn-danger btn-sm"
                     style="padding:6px 12px; border-radius:10px"
                     onclick="return confirm('Delete this offer?')">
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
