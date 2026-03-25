<?php
// admin/add-item.php — Add new dish (with inline AJAX category modal)
require_once __DIR__ . '/../includes/db.php';
session_start();

$errors = [];
$old    = ['name' => '', 'price' => '', 'category_id' => '', 'availability' => 'Available'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name         = trim($_POST['name']          ?? '');
    $price        = $_POST['price']              ?? '';
    $cat_id       = intval($_POST['category_id'] ?? 0);
    $availability = $_POST['availability']       ?? 'Available';
    $old = compact('name', 'price', 'cat_id', 'availability');

    if ($name === '')                                           $errors[] = 'Dish name is required.';
    if ($price === '' || !is_numeric($price) || $price < 0)    $errors[] = 'A valid price is required.';
    if ($cat_id === 0)                                         $errors[] = 'Please select a category.';
    if (!in_array($availability, ['Available','Not Available']))$errors[] = 'Invalid availability.';

    $image_name = null;
    if (!empty($_FILES['image']['name'])) {
        $f = $_FILES['image'];
        $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg','jpeg','png','gif','webp']))
            $errors[] = 'Invalid image type (jpg/png/gif/webp).';
        elseif ($f['size'] > 3 * 1024 * 1024)
            $errors[] = 'Image must be < 3 MB.';
        else {
            $image_name = uniqid('dish_', true) . '.' . $ext;
            if (!move_uploaded_file($f['tmp_name'], __DIR__ . '/../uploads/' . $image_name)) {
                $errors[] = 'Upload failed — check uploads/ permissions.';
                $image_name = null;
            }
        }
    }

    if (empty($errors)) {
        $s = $conn->prepare("INSERT INTO dishes (name,price,category_id,image,availability) VALUES (?,?,?,?,?)");
        $s->bind_param('sdiss', $name, $price, $cat_id, $image_name, $availability);
        if ($s->execute()) {
            $_SESSION['flash'] = ['type' => 'success', 'msg' => "Dish '{$name}' added!"];
            header('Location: dashboard.php');
            exit;
        }
        $errors[] = 'DB error: ' . $conn->error;
        $s->close();
    }
}

$categories = $conn->query("SELECT * FROM categories ORDER BY name")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Add Dish — Menu Manager</title>
  <link rel="stylesheet" href="../assets/css/menu-style.css">
</head>
<body>

<?php include __DIR__ . '/partials/sidebar.php'; ?>

<div class="main">
  <div class="topbar"><h1>➕ Add Dish</h1></div>
  <div class="content">

    <div class="card" style="max-width:720px">
      <div class="card-title">New Dish Details</div>

      <?php foreach ($errors as $e): ?>
        <div class="flash flash-danger">❌ <?= htmlspecialchars($e) ?></div>
      <?php endforeach; ?>

      <form method="POST" enctype="multipart/form-data" novalidate>
        <div class="form-grid">

          <div class="form-group">
            <label for="name">Dish Name <span class="req">*</span></label>
            <input type="text" id="name" name="name" required placeholder="e.g. Butter Chicken"
                   value="<?= htmlspecialchars($old['name']) ?>">
          </div>

          <div class="form-group">
            <label for="price">Price (₹) <span class="req">*</span></label>
            <input type="number" id="price" name="price" required min="0" step="0.01" placeholder="0.00"
                   value="<?= htmlspecialchars($old['price']) ?>">
          </div>

          <!-- Category with inline add -->
          <div class="form-group">
            <label for="category_id">Category <span class="req">*</span></label>
            <div class="input-row">
              <select id="category_id" name="category_id" required>
                <option value="">— Select —</option>
                <?php foreach ($categories as $c): ?>
                  <option value="<?= $c['id'] ?>" <?= $old['category_id'] == $c['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($c['name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <button type="button" id="btn-add-category" class="btn btn-outline btn-sm">＋ Add Category</button>
            </div>
          </div>

          <div class="form-group">
            <label for="availability">Availability <span class="req">*</span></label>
            <select id="availability" name="availability">
              <option value="Available"     <?= $old['availability']==='Available'     ? 'selected':'' ?>>Available</option>
              <option value="Not Available" <?= $old['availability']==='Not Available' ? 'selected':'' ?>>Not Available</option>
            </select>
          </div>

          <div class="form-group full">
            <label for="image">Dish Image <span class="hint">(optional · max 3 MB · jpg/png/gif/webp)</span></label>
            <input type="file" id="image" name="image" accept="image/*">
          </div>

          <div class="form-group full" id="preview-wrap" style="display:none">
            <label>Preview</label>
            <img id="img-preview" src="" alt="" class="img-thumb">
          </div>

        </div>

        <div class="btn-grp" style="margin-top:22px">
          <button type="submit" class="btn btn-primary">💾 Save Dish</button>
          <a href="dashboard.php" class="btn btn-outline">Cancel</a>
        </div>
      </form>
    </div>

  </div>
</div>

<!-- ── Category Modal ── -->
<div id="cat-overlay" class="modal-overlay"></div>
<div id="cat-modal"   class="modal" role="dialog" aria-modal="true">
  <div class="modal-header">
    <h3>📂 New Category</h3>
    <button id="cat-modal-close" class="modal-close" aria-label="Close">&times;</button>
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
  // Image preview
  document.getElementById('image').addEventListener('change', function () {
    const f = this.files[0];
    if (!f) return;
    const r = new FileReader();
    r.onload = e => {
      document.getElementById('img-preview').src = e.target.result;
      document.getElementById('preview-wrap').style.display = 'block';
    };
    r.readAsDataURL(f);
  });
</script>

</body>
</html>

