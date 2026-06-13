<?php
require_once __DIR__ . '/partials/auth_check.php';
require_once __DIR__ . '/../includes/db.php';

$id = intval($_GET['id'] ?? 0);
if (!$id) { header('Location: dashboard.php'); exit; }

$admin_sess_id = $_SESSION['admin_id'] ?? 0;

$s = $conn->prepare("SELECT d.*, COALESCE(t.name, t_en.name) AS name, COALESCE(t.description, t_en.description) AS description 
                    FROM dishes d 
                    LEFT JOIN dish_translations t ON t.dish_id = d.id AND t.language_code = 'en'
                    LEFT JOIN dish_translations t_en ON t_en.dish_id = d.id AND t_en.language_code = 'en'
                    WHERE d.id = ? AND d.user_id = ?");
$s->bind_param('ii', $id, $admin_sess_id);
$s->execute();
$dish = $s->get_result()->fetch_assoc();
$s->close();

if (!$dish) {
    $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'Dish not found.'];
    header('Location: dashboard.php');
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name         = trim($_POST['name']          ?? '');
    $price        = $_POST['price']              ?? '';
    $cat_id       = intval($_POST['category_id'] ?? 0);
    $break        = isset($_POST['available_breakfast']) ? 1 : 0;
    $lunch        = isset($_POST['available_lunch'])     ? 1 : 0;
    $dinner       = isset($_POST['available_dinner'])    ? 1 : 0;
    $veg_type     = $_POST['veg_type']           ?? 'veg';
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

    // Check for duplicate dish name (excluding current ID) - Case-Insensitive
    if (empty($errors)) {
        $check_stmt = $conn->prepare("SELECT id FROM dishes WHERE LOWER(TRIM(name)) = LOWER(TRIM(?)) AND user_id = ? AND id != ? AND is_deleted = 0");
        $check_stmt->bind_param('sii', $name, $admin_sess_id, $id);
        $check_stmt->execute();
        if ($check_stmt->get_result()->num_rows > 0) {
            $errors[] = "This dish already exists.";
        }
        $check_stmt->close();
    }

    $image_name = $dish['image'];
    $delete_old_image = false;

    // Handle image removal if checked and NO new image is uploaded
    if (isset($_POST['remove_image']) && $_POST['remove_image'] == '1' && empty($_FILES['image']['name'])) {
        $image_name = null;
        $delete_old_image = true;
    }

    if (!empty($_FILES['image']['name'])) {
        $f   = $_FILES['image'];
        $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg','jpeg','png','gif','webp']))
            $errors[] = 'Invalid image type.';
        elseif ($f['size'] > 3 * 1024 * 1024)
            $errors[] = 'Image must be < 3 MB.';
        else {
            $new_img = uniqid('dish_', true) . '.' . $ext;
            $dest    = __DIR__ . '/../uploads/' . $new_img;
            if (move_uploaded_file($f['tmp_name'], $dest)) {
                $image_name = $new_img;
                $delete_old_image = true;
            } else {
                $errors[] = 'Upload failed.';
            }
        }
    }

    if (empty($errors)) {
        $upd = $conn->prepare("UPDATE dishes SET name=?, price=?, category_id=?, veg_type=?, available_breakfast=?, available_lunch=?, available_dinner=?, image=?, currency=?, offer_id=? WHERE id=? AND user_id=?");
        $upd->bind_param('sdisiiissiii', $name, $price, $cat_id, $veg_type, $break, $lunch, $dinner, $image_name, $currency, $offer_id, $id, $admin_sess_id);
        if ($upd->execute()) {
            // Update translation (EN)
            $t_upd = $conn->prepare("INSERT INTO dish_translations (dish_id, language_code, name) VALUES (?, 'en', ?) ON DUPLICATE KEY UPDATE name = ?");
            $t_upd->bind_param('iss', $id, $name, $name);
            $t_upd->execute();
            $t_upd->close();

            if ($delete_old_image && $dish['image'] && file_exists(__DIR__ . '/../uploads/' . $dish['image'])) {
                unlink(__DIR__ . '/../uploads/' . $dish['image']);
            }
            $_SESSION['flash'] = ['type' => 'success', 'msg' => "Dish '{$name}' updated."];
            header('Location: dashboard.php');
            exit;
        }
        $errors[] = 'DB error: ' . $conn->error;
        $upd->close();
    }
    // Reflect changes
    $dish = array_merge($dish, [
        'name' => $name, 
        'price' => $price, 
        'currency' => $currency, 
        'offer_id' => $offer_id, 
        'veg_type' => $veg_type,
        'available_breakfast' => $break,
        'available_lunch' => $lunch,
        'available_dinner' => $dinner
    ]);
    $dish['category_id'] = $cat_id;
}

$categories = $conn->query("SELECT * FROM categories WHERE user_id = $admin_sess_id AND is_deleted = 0 ORDER BY name")->fetch_all(MYSQLI_ASSOC);
$offers     = $conn->query("SELECT id, title FROM offers WHERE user_id = $admin_sess_id AND status='active' AND offer_type='seasonal' AND is_deleted=0 ORDER BY title")->fetch_all(MYSQLI_ASSOC);

$page_title = 'Edit Dish — Menu Manager';
?>

<?php include __DIR__ . '/partials/header.php'; ?>


    <div class="card" style="max-width:720px">
      <div class="card-title">Edit: <?= htmlspecialchars($dish['name']) ?></div>

      <?php foreach ($errors as $e): ?>
        <div class="flash flash-danger">❌ <?= htmlspecialchars($e) ?></div>
      <?php endforeach; ?>

      <form method="POST" enctype="multipart/form-data" novalidate>
        <div class="form-grid">

          <div class="form-group">
            <label for="name">Dish Name <span class="req">*</span></label>
            <input type="text" id="name" name="name" required value="<?= htmlspecialchars($dish['name']) ?>">
          </div>

          <div class="form-group">
            <label>Price *</label>
            <div style="display:grid; grid-template-columns: 80px 1fr; gap: 10px">
              <select name="currency" required>
                <option value="INR" <?= $dish['currency'] === 'INR' ? 'selected' : '' ?>>INR (₹)</option>
                <option value="USD" <?= $dish['currency'] === 'USD' ? 'selected' : '' ?>>USD ($)</option>
              </select>
              <input type="number" name="price" required min="0" step="0.01" value="<?= htmlspecialchars($dish['price']) ?>">
            </div>
          </div>

          <div class="form-group">
            <label for="category_id">Category <span class="req">*</span></label>
            <div class="input-row">
              <select id="category_id" name="category_id" required>
                <option value="">— Select —</option>
                <?php foreach ($categories as $c): ?>
                  <option value="<?= $c['id'] ?>" <?= $dish['category_id'] == $c['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($c['name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <button type="button" id="btn-add-category" class="btn btn-outline btn-sm">＋ Add Category</button>
            </div>
          </div>

          <div class="form-group">
            <label for="veg_type">Dish Type (Veg / Non-Veg) <span class="req">*</span></label>
            <select id="veg_type" name="veg_type" required>
              <option value="veg"     <?= ($dish['veg_type'] ?? 'veg') === 'veg'     ? 'selected' : '' ?>>🟢 Veg (Vegetarian)</option>
              <option value="non_veg" <?= ($dish['veg_type'] ?? 'veg') === 'non_veg' ? 'selected' : '' ?>>🔴 Non-Veg (Non-Vegetarian)</option>
            </select>
          </div>

          <div class="form-group full">
            <label>Available During (Meal Times)</label>
            <div style="display:flex; flex-wrap:wrap; gap:15px 25px; background:#f8fafc; padding:15px; border-radius:12px; border:1px solid var(--border)">
              <label style="display:flex; align-items:center; gap:8px; font-weight:normal; margin:0; cursor:pointer; min-width:100px">
                <input type="checkbox" name="available_breakfast" <?= ($dish['available_breakfast'] ?? 0) ? 'checked' : '' ?>> Breakfast
              </label>
              <label style="display:flex; align-items:center; gap:8px; font-weight:normal; margin:0; cursor:pointer; min-width:100px">
                <input type="checkbox" name="available_lunch" <?= ($dish['available_lunch'] ?? 0) ? 'checked' : '' ?>> Lunch
              </label>
              <label style="display:flex; align-items:center; gap:8px; font-weight:normal; margin:0; cursor:pointer; min-width:100px">
                <input type="checkbox" name="available_dinner" <?= ($dish['available_dinner'] ?? 0) ? 'checked' : '' ?>> Dinner
              </label>
            </div>
          </div>

          <div class="form-group">
            <label for="offer_id">Seasonal Offer</label>
            <select id="offer_id" name="offer_id">
              <option value="">No Active Offer</option>
              <?php foreach ($offers as $o): ?>
                <option value="<?= $o['id'] ?>" <?= ($dish['offer_id'] == $o['id']) ? 'selected' : '' ?>>
                  <?= htmlspecialchars($o['title']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- Current image -->
          <div class="form-group full">
            <label>Current Image</label>
            <?php if ($dish['image'] && file_exists(__DIR__ . '/../uploads/' . $dish['image'])): ?>
              <div style="display:flex; flex-wrap:wrap; align-items:start; gap:16px">
                <img src="../uploads/<?= htmlspecialchars($dish['image']) ?>" alt="" class="img-thumb">
                <label style="display:flex; align-items:center; gap:8px; font-weight:normal; margin-top:10px; cursor:pointer; color:#ef4444; font-size:.85rem; background: #fff1f2; padding: 10px 15px; border-radius: 10px; border: 1px solid #fee2e2;">
                  <input type="checkbox" name="remove_image" value="1"> 🗑️ Remove current image
                </label>
              </div>
            <?php else: ?>
              <span style="color:var(--muted);font-size:.85rem">No image uploaded</span>
            <?php endif; ?>
          </div>

          <div class="form-group full">
            <label for="image">Replace Image <span class="hint">(leave blank to keep current)</span></label>
            <input type="file" id="image" name="image" accept="image/*">
          </div>

          <div class="form-group full" id="preview-wrap" style="display:none">
            <label>New Preview</label>
            <img id="img-preview" src="" alt="" class="img-thumb">
          </div>

        </div>

        <div class="btn-grp" style="margin-top:22px">
          <button type="submit" class="btn btn-primary">
            <span style="font-size:1rem">💾</span> Update Dish
          </button>
          <a href="dashboard.php" class="btn btn-outline">Cancel</a>
        </div>
      </form>
    </div>

  </div>
</div>

<!-- Category Modal (same HTML structure as add.php) -->
<div id="cat-overlay" class="modal-overlay"></div>
<div id="cat-modal"   class="modal" role="dialog" aria-modal="true">
  <div class="modal-header">
    <h3>📂 New Category</h3>
    <button id="cat-modal-close" class="modal-close">&times;</button>
  </div>
  <div class="modal-body">
    <div class="form-group">
      <label for="cat-modal-input">Category Name</label>
      <input type="text" id="cat-modal-input" placeholder="e.g. Soups" maxlength="100" autocomplete="off">
    </div>
    <div id="cat-modal-msg" class="m-msg" role="alert"></div>
  </div>
  <div class="modal-footer">
    <button id="cat-modal-save"   class="btn btn-primary">Save Category</button>
    <button id="cat-modal-cancel" class="btn btn-outline">Cancel</button>
  </div>
</div>

<script>
  window.MENU_CONFIG = {
    addCategoryUrl : '../api/add_category.php',
    fetchDishesUrl : '../api/fetch_dishes.php',
    uploadsBase    : '../uploads/'
  };
</script>
<script src="../assets/js/menu-script.js"></script>
<script>
  document.getElementById('image').addEventListener('change', function () {
    const f = this.files[0]; if (!f) return;
    const r = new FileReader();
    r.onload = e => { document.getElementById('img-preview').src = e.target.result; document.getElementById('preview-wrap').style.display='block'; };
    r.readAsDataURL(f);
  });
</script>

</body>
</html>

