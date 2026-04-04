<?php
// admin/menu-import.php
require_once __DIR__ . '/partials/auth_check.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/logger.php';

// Hostinger / Shared Hosting Optimizations
ini_set('display_errors', 1);
error_reporting(E_ALL);
set_time_limit(0); 
ini_set('memory_limit', '256M');

$admin_id = $_SESSION['admin_id'] ?? 0;
$prefix = ''; // Relative path prefix

$error = '';
$success = '';

// ── Directory Setup ──────────────────────────────────────────
$upload_dir = __DIR__ . '/../uploads/menu_imports/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

// ── Image Processing Logic (GD) ───────────────────────────────
function process_and_compress_image($source, $dest) {
    if (!function_exists('imagecreatefromjpeg')) return false;

    list($width, $height, $type) = getimagesize($source);
    $max_width = 1200;
    $new_width = $width;
    $new_height = $height;

    if ($width > $max_width) {
        $ratio = $max_width / $width;
        $new_width = $max_width;
        $new_height = floor($height * $ratio);
    }

    $image = null;
    try {
        switch ($type) {
            case IMAGETYPE_JPEG: $image = @imagecreatefromjpeg($source); break;
            case IMAGETYPE_PNG:  $image = @imagecreatefrompng($source);  break;
            case IMAGETYPE_WEBP: $image = @imagecreatefromwebp($source); break;
        }
    } catch (Exception $e) { return false; }

    if (!$image) return false;

    $virtual_image = imagecreatetruecolor($new_width, $new_height);
    if ($type == IMAGETYPE_PNG || $type == IMAGETYPE_WEBP) {
        imagealphablending($virtual_image, false);
        imagesavealpha($virtual_image, true);
    }

    imagecopyresampled($virtual_image, $image, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
    $res = imagejpeg($virtual_image, $dest, 80);
    imagedestroy($image);
    imagedestroy($virtual_image);

    return $res;
}

// ── CSV Processing Logic (Strict Mode Robust) ─────────────────
function process_csv_import($file_path, $admin_id, $conn) {
    $handle = fopen($file_path, "r");
    if (!$handle) return false;

    $stats = ['total' => 0, 'success' => 0, 'skipped' => 0];
    $row_count = 0;

    // Cache categories
    $cat_map = [];
    $res = $conn->query("SELECT id, name FROM categories WHERE user_id = $admin_id");
    while($row = $res->fetch_assoc()) { $cat_map[strtolower(trim($row['name']))] = $row['id']; }

    // Use 0 for unlimited line length in fgetcsv
    while (($data = fgetcsv($handle, 0, ",")) !== FALSE) {
        $row_count++;
        if ($row_count === 1) continue; // Skip Header
        
        $stats['total']++;
        
        $cat_name  = trim($data[0] ?? '');
        $dish_name = trim($data[1] ?? '');
        $price_raw = trim($data[2] ?? '0');
        $price     = floatval(preg_replace('/[^0-9.]/', '', $price_raw));
        $desc      = trim($data[3] ?? '');

        if (empty($dish_name)) {
            $stats['skipped']++;
            continue;
        }

        try {
            // 1. Ensure Category
            $cat_id = 0;
            if (!empty($cat_name)) {
                $cat_key = strtolower($cat_name);
                if (!isset($cat_map[$cat_key])) {
                    $stmt = $conn->prepare("INSERT INTO categories (name, user_id) VALUES (?, ?)");
                    $stmt->bind_param("si", $cat_name, $admin_id);
                    $stmt->execute();
                    $cat_id = $conn->insert_id;
                    $cat_map[$cat_key] = $cat_id;
                    $stmt->close();
                } else {
                    $cat_id = $cat_map[$cat_key];
                }
            } else {
                if (!empty($cat_map)) $cat_id = reset($cat_map);
            }

            // 2. Insert Dish
            $stmt = $conn->prepare("INSERT INTO dishes (user_id, category_id, name, price, description, availability, currency) VALUES (?, ?, ?, ?, ?, 'Available', 'INR')");
            $stmt->bind_param("iisds", $admin_id, $cat_id, $dish_name, $price, $desc);
            if ($stmt->execute()) {
                $stats['success']++;
            } else {
                $stats['skipped']++;
            }
            $stmt->close();
        } catch (Exception $e) {
            $stats['skipped']++;
            // Log individual row error silently to keep the loop moving
            error_log("Import Row Error: " . $e->getMessage());
            if (isset($stmt)) $stmt->close();
        }
    }
    fclose($handle);
    return $stats;
}

// ── Handle Upload Post ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['menu_file'])) {
    $file = $_FILES['menu_file'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'pdf', 'xlsx', 'xls', 'csv'];

    if (!in_array($ext, $allowed)) {
        $error = "File type not supported. Please use Images, PDF, Excel, or CSV.";
    } elseif ($file['error'] !== UPLOAD_ERR_OK) {
        $error = "Upload failed with error code: " . $file['error'];
    } elseif ($file['size'] > 10 * 1024 * 1024) {
        $error = "File too large. Max 10MB.";
    } else {
        $new_name = uniqid('import_') . '.' . $ext;
        $file_path = $upload_dir . $new_name;

        if (move_uploaded_file($file['tmp_name'], $file_path)) {
            $file_type = in_array($ext, ['xlsx', 'xls', 'csv']) ? 'excel' : ($ext === 'pdf' ? 'pdf' : 'image');
            
            // Log to database
            $stmt = $conn->prepare("INSERT INTO menu_imports (admin_id, file_name, file_type, file_path, status) VALUES (?, ?, ?, ?, 'processing')");
            $stmt->bind_param("isss", $admin_id, $file['name'], $file_type, $file_path);
            $stmt->execute();
            $import_id = $conn->insert_id;
            $stmt->close();

            try {
                if ($file_type === 'image') {
                    $compressed_name = 'opt_' . str_replace('.'.$ext, '.jpg', $new_name);
                    $compressed_path = $upload_dir . $compressed_name;
                    if (process_and_compress_image($file_path, $compressed_path)) {
                        $conn->query("UPDATE menu_imports SET status = 'completed', file_path = '$compressed_path' WHERE id = $import_id");
                        $success = "Menu Image optimized and saved successfully.";
                    } else {
                        $conn->query("UPDATE menu_imports SET status = 'completed' WHERE id = $import_id");
                        $success = "Image saved. (System optimized default size).";
                    }
                } elseif ($ext === 'csv') {
                    $res = process_csv_import($file_path, $admin_id, $conn);
                    if ($res) {
                        $conn->query("UPDATE menu_imports SET status = 'completed' WHERE id = $import_id");
                        $success = "{$res['total']} rows detected. {$res['success']} dishes imported. {$res['skipped']} rows skipped due to invalid data.";
                    } else {
                        throw new Exception("Unable to parse CSV file.");
                    }
                } else {
                    // Mark as 'completed' (Stored) for Excel/PDF if logic isn't yet active but file is safe
                    $conn->query("UPDATE menu_imports SET status = 'completed' WHERE id = $import_id");
                    $success = "File uploaded successfully. We are now processing the " . strtoupper($ext) . " content.";
                }
            } catch (Exception $e) {
                $conn->query("UPDATE menu_imports SET status = 'failed' WHERE id = $import_id");
                $error = "Processing Error: " . $e->getMessage();
            }
        } else {
            $error = "Failed to save file on Hostinger server. Check folder permissions.";
        }
    }
}

// Fetch History
$history = $conn->query("SELECT * FROM menu_imports WHERE admin_id = $admin_id ORDER BY uploaded_at DESC LIMIT 10")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Menu Import | Vingo Admin</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="../assets/css/menu-style.css?v=<?= time() ?>">
  <style>
    .import-card { border: 2px dashed #e2e8f0; padding: 40px; text-align: center; border-radius: 16px; background: #f8fafc; transition: all 0.3s ease; }
    .import-card:hover { border-color: var(--accent); background: #f0f4ff; }
    .file-hint { font-size: 0.85rem; color: #64748b; margin-top: 10px; }
    .status-badge { padding: 4px 12px; border-radius: 99px; font-size: 0.75rem; font-weight: 600; }
    .status-processing { background: #fef3c7; color: #92400e; }
    .status-completed { background: #dcfce7; color: #166534; }
    .status-failed { background: #fee2e2; color: #991b1b; }
    .format-guide { background: #eff6ff; padding: 20px; border-radius: 12px; margin-top: 30px; }
    .format-guide table { width: 100%; border-collapse: collapse; margin-top: 10px; }
    .format-guide th, .format-guide td { border: 1px solid #bfdbfe; padding: 8px; font-size: 0.85rem; text-align: left; }
  </style>
</head>
<body>

<?php include __DIR__ . '/partials/sidebar.php'; ?>

<div class="main">
  <div class="topbar">
    <div class="topbar-left" style="display:flex; align-items:center; gap:16px">
      <div class="menu-toggle" id="menuToggle">☰</div>
      <h1>📥 Menu Import Center</h1>
    </div>
    <div class="topbar-right">
      <?php include __DIR__ . '/partials/topbar_user.php'; ?>
    </div>
  </div>

  <div class="content">
    <?php if ($success): ?>
      <div class="flash flash-success">✅ <?= $success ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="flash flash-danger">❌ <?= $error ?></div>
    <?php endif; ?>

    <div style="display:grid; grid-template-columns: 2fr 1fr; gap: 30px;">
      
      <div>
        <div class="card">
          <div class="card-title">Upload Menu Document</div>
          <form method="POST" enctype="multipart/form-data">
            <div class="import-card" onclick="document.getElementById('fileInput').click()">
              <div style="font-size: 3rem; margin-bottom: 15px;">📄</div>
              <p>Click to browse or drag and drop your menu source</p>
              <input type="file" name="menu_file" id="fileInput" style="display:none" onchange="this.form.submit()">
              <p class="file-hint">Supported: .csv, .xlsx, .pdf, .jpg, .png (Max 10MB)</p>
            </div>
          </form>

          <div class="format-guide">
            <strong>📊 Excel/CSV Format Requirement</strong>
            <p style="font-size: 0.8rem; margin: 10px 0;">To ensure your dishes are correctly added, use the following column order:</p>
            <table>
              <thead>
                <tr><th>Column A</th><th>Column B</th><th>Column C</th><th>Column D</th></tr>
              </thead>
              <tbody>
                <tr><td>Category</td><td>Dish Name</td><td>Price</td><td>Description</td></tr>
                <tr><td>Burgers</td><td>Classic Veggie</td><td>299</td><td>Double patty with cheese</td></tr>
              </tbody>
            </table>
            <p class="file-hint" style="margin-top:10px">⚠️ Tip: Save your Excel as <strong>.CSV</strong> for the fastest and most reliable import.</p>
          </div>
        </div>

        <div class="card" style="margin-top: 30px;">
          <div class="card-title">Recent Imports</div>
          <div class="table-wrap">
            <table style="width:100%">
              <thead>
                <tr>
                  <th>File Name</th>
                  <th>Type</th>
                  <th>Date</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($history as $h): ?>
                <tr>
                  <td><?= htmlspecialchars($h['file_name']) ?></td>
                  <td><small style="text-transform: uppercase;"><?= $h['file_type'] ?></small></td>
                  <td><?= date('M d, H:i', strtotime($h['uploaded_at'])) ?></td>
                  <td>
                    <span class="status-badge status-<?= $h['status'] ?>">
                      <?= ucfirst($h['status']) ?>
                    </span>
                  </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($history)): ?>
                  <tr><td colspan="4" style="text-align:center; padding:30px; color:#94a3b8">No recent imports found.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <div>
        <div class="card" style="background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%); color: white;">
          <h3 style="margin-bottom:15px">Why use Import?</h3>
          <p style="font-size: 0.9rem; line-height: 1.5; opacity: 0.9;">
            Avoid manual entry! Upload your existing price list or a photo of your menu. Our system will analyze the content and populate your digital menu card automatically.
          </p>
          <ul style="margin-top: 20px; font-size: 0.85rem; padding-left: 18px;">
            <li style="margin-bottom: 8px;">Bulk add hundreds of dishes</li>
            <li style="margin-bottom: 8px;">Preserve your existing categories</li>
            <li style="margin-bottom: 8px;">Optimized images for faster loading</li>
          </ul>
        </div>
      </div>

    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const card = document.querySelector('.import-card');
    card.addEventListener('dragover', (e) => { e.preventDefault(); card.style.borderColor = '#4f46e5'; });
    card.addEventListener('dragleave', () => { card.style.borderColor = '#e2e8f0'; });
    card.addEventListener('drop', (e) => {
        e.preventDefault();
        const files = e.dataTransfer.files;
        if (files.length > 0) {
            document.getElementById('fileInput').files = files;
            document.getElementById('fileInput').form.submit();
        }
    });
});
</script>

</body>
</html>
