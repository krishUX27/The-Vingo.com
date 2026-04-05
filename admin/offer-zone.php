<?php
// admin/offer-zone.php — Unified Offer & Combo Management
require_once __DIR__ . '/partials/auth_check.php';
require_once __DIR__ . '/../includes/db.php';

$admin_id = $_SESSION['admin_sess_id'] ?? ($_SESSION['admin_id'] ?? 0);
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

// Robust Schema Auto-Fix
$conn->query("CREATE TABLE IF NOT EXISTS offers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    offer_type ENUM('seasonal', 'combo') NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    discount_percentage DECIMAL(5,2) DEFAULT NULL,
    combo_price DECIMAL(10,2) DEFAULT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_deleted TINYINT(1) DEFAULT 0
)");

$conn->query("CREATE TABLE IF NOT EXISTS offer_combo_dishes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    offer_id INT NOT NULL,
    dish_id INT NOT NULL
)");

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'save_offer') {
        $id          = intval($_POST['id'] ?? 0);
        $type        = $_POST['offer_type'] ?? 'seasonal';
        $title       = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $discount    = !empty($_POST['discount_percentage']) ? (float)$_POST['discount_percentage'] : null;
        $combo_price = !empty($_POST['combo_price']) ? (float)$_POST['combo_price'] : null;
        $start_date  = $_POST['start_date'] ?? date('Y-m-d');
        $end_date    = $_POST['end_date'] ?? date('Y-m-d');
        $status      = $_POST['status'] ?? 'active';
        $dish_ids    = $_POST['dish_ids'] ?? [];

        if (empty($title)) {
            $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'Offer title is required.'];
        } else {
            if ($id > 0) {
                // Update
                $stmt = $conn->prepare("UPDATE offers SET title=?, description=?, discount_percentage=?, combo_price=?, start_date=?, end_date=?, status=? WHERE id=? AND user_id=?");
                $stmt->bind_param('ssddsssii', $title, $description, $discount, $combo_price, $start_date, $end_date, $status, $id, $admin_id);
                $stmt->execute();
                $offer_id = $id;
            } else {
                // Insert
                $stmt = $conn->prepare("INSERT INTO offers (user_id, offer_type, title, description, discount_percentage, combo_price, start_date, end_date, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param('isssddsss', $admin_id, $type, $title, $description, $discount, $combo_price, $start_date, $end_date, $status);
                $stmt->execute();
                $offer_id = $conn->insert_id;
            }

            // Sync Combo Dishes
            if ($type === 'combo') {
                $conn->query("DELETE FROM offer_combo_dishes WHERE offer_id = $offer_id");
                if (!empty($dish_ids)) {
                    $ins = $conn->prepare("INSERT INTO offer_combo_dishes (offer_id, dish_id) VALUES (?, ?)");
                    foreach ($dish_ids as $did) {
                        $did = intval($did);
                        $ins->bind_param('ii', $offer_id, $did);
                        $ins->execute();
                    }
                    $ins->close();
                }
            }

            $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Offer saved successfully.'];
            header('Location: offer-zone.php');
            exit;
        }
    }
}

// Handle Delete
if (isset($_GET['delete'])) {
    $del_id = intval($_GET['delete']);
    $conn->query("UPDATE offers SET is_deleted = 1 WHERE id = $del_id AND user_id = $admin_id");
    header('Location: offer-zone.php');
    exit;
}

// Fetch Data
$offers_res = $conn->query("SELECT * FROM offers WHERE user_id = $admin_id AND is_deleted = 0 ORDER BY created_at DESC");
$offers = ($offers_res) ? $offers_res->fetch_all(MYSQLI_ASSOC) : [];

$dishes_res = $conn->query("SELECT id, name FROM dishes WHERE user_id = $admin_id AND is_deleted = 0 ORDER BY name");
$all_dishes = ($dishes_res) ? $dishes_res->fetch_all(MYSQLI_ASSOC) : [];

// Helper to get dishes for a combo
function get_combo_dishes($conn, $oid) {
    $res = $conn->query("SELECT dish_id FROM offer_combo_dishes WHERE offer_id = $oid");
    $ids = [];
    while($r = $res->fetch_assoc()) $ids[] = (int)$r['dish_id'];
    return $ids;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Offer Zone | Vingo Admin</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="../assets/css/menu-style.css?v=<?= time() ?>">
  <style>
    .offer-type-selection { display: flex; gap: 20px; margin-bottom: 30px; }
    .type-btn { 
      flex: 1; padding: 25px; border-radius: 16px; border: 2px solid var(--border); 
      background: #fff; cursor: pointer; text-align: center; transition: all 0.2s;
    }
    .type-btn:hover { border-color: var(--accent); background: #f8fafc; }
    .type-btn h3 { margin-bottom: 8px; color: var(--text); }
    .type-btn p { font-size: 0.85rem; color: var(--muted); }

    .offer-list { display: grid; gap: 16px; }
    .offer-item { 
      background: #fff; border: 1px solid var(--border); border-radius: 12px; padding: 20px;
      display: flex; justify-content: space-between; align-items: center;
    }
    .status-active { color: #16a34a; font-weight: 700; }
    .status-inactive { color: #dc2626; font-weight: 700; }
    
    .multi-select { height: 120px !important; }
  </style>
</head>
<body>

<?php include __DIR__ . '/partials/sidebar.php'; ?>

<div class="main">
  <div class="topbar">
    <div class="topbar-left">
      <h1>🎁 Offer Zone</h1>
      <p class="meta">Manage Seasonal & Combo Deals</p>
    </div>
    <div class="topbar-right">
      <?php include __DIR__ . '/partials/topbar_user.php'; ?>
    </div>
  </div>

  <div class="content">
    <?php if ($flash): ?>
      <div class="flash flash-<?= $flash['type'] ?>">
        <?= $flash['type']==='success'?'✅':'❌' ?> <?= htmlspecialchars($flash['msg']) ?>
      </div>
    <?php endif; ?>

    <div class="offer-type-selection">
      <div class="type-btn" onclick="openOfferModal('seasonal')">
        <h3>🍱 Seasonal Offer</h3>
        <p>Percentage discounts on individual items</p>
      </div>
      <div class="type-btn" onclick="openOfferModal('combo')">
        <h3>🍔 Combo Offer</h3>
        <p>Bundle multiple dishes at a fixed price</p>
      </div>
    </div>

    <div class="card">
      <div class="card-title">Active / Inactive Promotions</div>
      <div class="offer-list">
        <?php foreach ($offers as $o): ?>
        <div class="offer-item">
          <div>
            <span class="badge" style="background:#f1f5f9; color:#64748b; margin-bottom:8px">
              <?= strtoupper($o['offer_type']) ?>
            </span>
            <h4 style="margin:4px 0"><?= htmlspecialchars($o['title']) ?></h4>
            <p style="font-size:0.85rem; color:var(--muted)">
              <?= $o['offer_type']==='seasonal' ? $o['discount_percentage'].'% Off' : 'Fixed Price: ₹'.$o['combo_price'] ?>
              • <?= date('d M', strtotime($o['start_date'])) ?> to <?= date('d M', strtotime($o['end_date'])) ?>
            </p>
            <span class="status-<?= $o['status'] ?>"><?= ucfirst($o['status']) ?></span>
          </div>
          <div class="btn-grp">
            <button class="btn btn-outline btn-sm" onclick='editOffer(<?= json_encode($o) ?>, <?= json_encode(get_combo_dishes($conn, $o['id'])) ?>)'>✏️ Edit</button>
            <a href="?delete=<?= $o['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete this offer?')">🗑️ Delete</a>
          </div>
        </div>
        <?php endforeach; ?>
        <?php if (empty($offers)): ?>
          <div class="no-data">No rewards created yet. Choose a type above to start.</div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- Offer Modal -->
<div id="offerModal" class="modal">
  <div class="modal-header">
    <h3 id="modalTitle">Create Offer</h3>
    <button class="modal-close" onclick="closeModal()">&times;</button>
  </div>
  <form method="POST">
    <input type="hidden" name="action" value="save_offer">
    <input type="hidden" name="id" id="offerId" value="0">
    <input type="hidden" name="offer_type" id="offerType" value="seasonal">
    
    <div class="modal-body">
      <div class="form-group">
        <label>Offer / Combo Title *</label>
        <input type="text" name="title" id="fTitle" required>
      </div>
      <div class="form-group">
        <label>Description</label>
        <textarea name="description" id="fDesc" rows="2"></textarea>
      </div>

      <!-- Seasonal Field -->
      <div id="seasonalFields" class="form-group">
        <label>Discount Percentage (%)</label>
        <input type="number" name="discount_percentage" id="fDiscount" step="0.1">
      </div>

      <!-- Combo Fields -->
      <div id="comboFields" style="display:none">
        <div class="form-group">
          <label>Select Dishes (Multi-select)</label>
          <select name="dish_ids[]" id="fDishes" multiple class="multi-select">
            <?php foreach ($all_dishes as $d): ?>
              <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['name']) ?></option>
            <?php endforeach; ?>
          </select>
          <p class="file-hint">Hold Ctrl (Cmd) to select multiple</p>
        </div>
        <div class="form-group">
          <label>Combo Price (₹)</label>
          <input type="number" name="combo_price" id="fPrice" step="0.01">
        </div>
      </div>

      <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px">
        <div class="form-group">
          <label>Start Date</label>
          <input type="date" name="start_date" id="fStart" required value="<?= date('Y-m-d') ?>">
        </div>
        <div class="form-group">
          <label>End Date</label>
          <input type="date" name="end_date" id="fEnd" required value="<?= date('Y-m-d', strtotime('+7 days')) ?>">
        </div>
      </div>

      <div class="form-group">
        <label>Status</label>
        <select name="status" id="fStatus">
          <option value="active">Active (Visible on Menu)</option>
          <option value="inactive">Inactive (Hidden)</option>
        </select>
      </div>
    </div>
    <div class="modal-footer">
      <button type="submit" class="btn btn-primary">💾 Save Changes</button>
      <button type="button" class="btn btn-outline" onclick="closeModal()">Cancel</button>
    </div>
  </form>
</div>
<div id="modalOverlay" class="modal-overlay"></div>

<script>
function openOfferModal(type) {
    document.getElementById('offerId').value = 0;
    document.getElementById('offerType').value = type;
    document.getElementById('modalTitle').textContent = type === 'seasonal' ? 'Create Seasonal Offer' : 'Create Combo Bundle';
    
    // Reset fields
    document.getElementById('fTitle').value = '';
    document.getElementById('fDesc').value = '';
    document.getElementById('fDiscount').value = '';
    document.getElementById('fPrice').value = '';
    document.getElementById('fStatus').value = 'active';
    
    // Toggle Visibility
    document.getElementById('seasonalFields').style.display = type === 'seasonal' ? 'block' : 'none';
    document.getElementById('comboFields').style.display = type === 'combo' ? 'block' : 'none';
    
    document.getElementById('offerModal').classList.add('open');
    document.getElementById('modalOverlay').classList.add('open');
}

function editOffer(o, dishes) {
    document.getElementById('offerId').value = o.id;
    document.getElementById('offerType').value = o.offer_type;
    document.getElementById('modalTitle').textContent = 'Edit ' + (o.offer_type==='seasonal'?'Offer':'Combo');
    
    document.getElementById('fTitle').value = o.title;
    document.getElementById('fDesc').value = o.description;
    document.getElementById('fDiscount').value = o.discount_percentage;
    document.getElementById('fPrice').value = o.combo_price;
    document.getElementById('fStart').value = o.start_date;
    document.getElementById('fEnd').value = o.end_date;
    document.getElementById('fStatus').value = o.status;

    // Multi-select sync
    const sel = document.getElementById('fDishes');
    for (const opt of sel.options) {
        opt.selected = dishes.includes(parseInt(opt.value));
    }

    document.getElementById('seasonalFields').style.display = o.offer_type === 'seasonal' ? 'block' : 'none';
    document.getElementById('comboFields').style.display = o.offer_type === 'combo' ? 'block' : 'none';

    document.getElementById('offerModal').classList.add('open');
    document.getElementById('modalOverlay').classList.add('open');
}

function closeModal() {
    document.getElementById('offerModal').classList.remove('open');
    document.getElementById('modalOverlay').classList.remove('open');
}
</script>

</body>
</html>
