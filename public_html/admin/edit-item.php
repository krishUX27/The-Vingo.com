<?php
// admin/edit-item.php — Edit existing dish
require_once __DIR__ . '/../includes/db.php';
session_start();

$id = intval($_GET['id'] ?? 0);
if (!$id) { header('Location: dashboard.php'); exit; }

$s = $conn->prepare("SELECT * FROM dishes WHERE id = ?");
$s->bind_param('i', $id);
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
    $availability = $_POST['availability']       ?? 'Available';

    if ($name === '')                                           $errors[] = 'Dish name is required.';
    if ($price === '' || !is_numeric($price) || $price < 0)    $errors[] = 'A valid price is required.';
    if ($cat_id === 0)                                         $errors[] = 'Please select a category.';

    $image_name = $dish['image'];
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
                // Remove old image
                if ($dish['image'] && file_exists(__DIR__ . '/../uploads/' . $dish['image']))
                    unlink(__DIR__ . '/../uploads/' . $dish['image']);
                $image_name = $new_img;
            } else {
                $errors[] = 'Upload failed.';
            }
        }
    }

    if (empty($errors)) {
        $upd = $conn->prepare(
            "UPDATE dishes SET name=?,price=?,category_id=?,image=?,availability=? WHERE id=?"
        );
        $upd->bind_param('sdissi', $name, $price, $cat_id, $image_name, $availability, $id);
        if ($upd->execute()) {
            $_SESSION['flash'] = ['type' => 'success', 'msg' => "Dish '{$name}' updated."];
            header('Location: dashboard.php');
            exit;
        }
        $errors[] = 'DB error: ' . $conn->error;
        $upd->close();
    }
    // Reflect changes
    $dish = array_merge($dish, compact('name','price','availability'));
    $dish['category_id'] = $cat_id;
}

$categories = $conn->query("SELECT * FROM categories ORDER BY name")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Edit Dish — Menu Manager</title>
  <link rel="stylesheet" href="../assets/css/menu-style.css">
</head>
<body>

<?php include __DIR__ . '/partials/sidebar.php'; ?>

<div class="main">
  <div class="topbar"><h1>✏️ Edit Dish</h1></div>
  <div class="content">

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
            <label for="price">Price (₹) <span class="req">*</span></label>
            <input type="number" id="price" name="price" required min="0" step="0.01"
                   value="<?= htmlspecialchars($dish['price']) ?>">
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
            <label for="availability">Availability</label>
            <select id="availability" name="availability">
              <option value="Available"     <?= $dish['availability']==='Available'     ? 'selected':'' ?>>Available</option>
              <option value="Not Available" <?= $dish['availability']==='Not Available' ? 'selected':'' ?>>Not Available</option>
            </select>
          </div>

          <!-- Current image -->
          <div class="form-group full">
            <label>Current Image</label>
            <?php if ($dish['image'] && file_exists(__DIR__ . '/../uploads/' . $dish['image'])): ?>
              <img src="../uploads/<?= htmlspecialchars($dish['image']) ?>" alt="" class="img-thumb">
            <?php else: ?>
              <span style="color:var(--muted);font-size:.85rem">No image</span>
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
          <button type="submit" class="btn btn-primary">💾 Update Dish</button>
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

