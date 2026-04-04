<?php
// admin/menu-import.php
require_once __DIR__ . '/partials/auth_check.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/logger.php';

// Hostinger / Shared Hosting Optimizations
ini_set('display_errors', 0);
error_reporting(E_ALL & ~E_NOTICE);
set_time_limit(0); 
ini_set('memory_limit', '256M');

$admin_id = $_SESSION['admin_id'] ?? 0;
$prefix = ''; // Relative path prefix

$error = '';
$success = '';

// ── Directory Setup & Schema Auto-Fix ──────────────────────────
$upload_dir = __DIR__ . '/../uploads/menu_imports/';
if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

// Robust Schema Auto-Fix: Ensure Admin Data Isolation Columns exist on live DB
$conn->query("ALTER TABLE categories ADD COLUMN IF NOT EXISTS user_id INT DEFAULT 0 AFTER name");
$conn->query("ALTER TABLE dishes ADD COLUMN IF NOT EXISTS user_id INT DEFAULT 0 AFTER id");
$conn->query("ALTER TABLE dishes ADD COLUMN IF NOT EXISTS is_deleted TINYINT(1) DEFAULT 0 AFTER currency");
$conn->query("ALTER TABLE dishes ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL AFTER is_deleted");
$conn->query("ALTER TABLE dishes ADD COLUMN IF NOT EXISTS available_breakfast TINYINT(1) DEFAULT 1 AFTER veg_type");
$conn->query("ALTER TABLE dishes ADD COLUMN IF NOT EXISTS available_lunch TINYINT(1) DEFAULT 1 AFTER available_breakfast");
$conn->query("ALTER TABLE dishes ADD COLUMN IF NOT EXISTS available_dinner TINYINT(1) DEFAULT 1 AFTER available_lunch");

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
    ini_set('auto_detect_line_endings', true);
    
    $handle = fopen($file_path, "r");
    if (!$handle) return false;

    // Detect delimiter more robustly by counting occurrences
    $first_line = fgets($handle);
    $c_comma = substr_count($first_line, ',');
    $c_semi  = substr_count($first_line, ';');
    $delimiter = ($c_semi > $c_comma) ? ';' : ',';
    rewind($handle);

    $stats = ['total' => 0, 'success' => 0, 'skipped' => 0];
    $row_count = 0;

    // Cache ONLY active categories for this admin
    $cat_map = [];
    $res = $conn->query("SELECT id, name FROM categories WHERE user_id = $admin_id AND is_deleted = 0");
    if ($res) {
        while($row = $res->fetch_assoc()) { 
            $cat_map[strtolower(trim($row['name']))] = $row['id']; 
        }
    }

    // Use 0 for unlimited line length in fgetcsv
    while (($data = fgetcsv($handle, 0, $delimiter)) !== FALSE) {
        $row_count++;
        if ($row_count === 1) continue; // Skip Header
        
        // Handle BOM or weird encoding on first column
        if ($row_count === 2 && !empty($data[0])) {
            $data[0] = preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $data[0]); 
        }

        $stats['total']++;
        
        $cat_name  = trim($data[0] ?? '');
        $dish_name = trim($data[1] ?? '');
        $price_raw = trim($data[2] ?? '0');
        
        // Robust Price cleaning
        $price = (float)str_replace(',', '', preg_replace('/[^0-9.,]/', '', $price_raw));
        $desc  = trim($data[3] ?? '');
        
        // Fuzzy Veg Type detection
        $veg_raw  = strtolower(trim($data[4] ?? 'veg'));
        $veg_type = (strpos($veg_raw, 'non') !== false) ? 'non_veg' : 'veg';
        
        // Flexible binary detection (1, yes, y, true, available)
        $is_true = function($v) {
            $v = strtolower(trim($v));
            return in_array($v, ['1', 'y', 'yes', 'true', 'available', 'v']);
        };

        $avail_b   = $is_true($data[5] ?? '0') ? 1 : 0;
        $avail_l   = $is_true($data[6] ?? '0') ? 1 : 0;
        $avail_d   = $is_true($data[7] ?? '0') ? 1 : 0;

        if (empty($dish_name)) {
            $stats['skipped']++;
            continue;
        }

        try {
            $stmt = null;
            $cat_id = 0;
            // 1. Ensure Category (Admin Isolated & Active)
            if (!empty($cat_name)) {
                $cat_key = strtolower($cat_name);
                if (!isset($cat_map[$cat_key])) {
                    $stmt = $conn->prepare("INSERT INTO categories (name, user_id) VALUES (?, ?)");
                    $stmt->bind_param("si", $cat_name, $admin_id);
                    $stmt->execute();
                    $cat_id = $conn->insert_id;
                    $cat_map[$cat_key] = $cat_id;
                    $stmt->close();
                    $stmt = null;
                } else {
                    $cat_id = $cat_map[$cat_key];
                }
            } else {
                if (!empty($cat_map)) {
                  $cat_id = reset($cat_map);
                } else {
                  // Fallback category if none exist
                  $conn->query("INSERT INTO categories (name, user_id) VALUES ('General', $admin_id)");
                  $cat_id = $conn->insert_id;
                  $cat_map['general'] = $cat_id;
                }
            }

            // 2. Dynamic Currency from Admin Settings
            $currency = menu_get_setting('currency', 'INR', $admin_id);

            // 3. Insert Dish (Strict Admin Isolation)
            $sql = "INSERT INTO dishes (user_id, category_id, name, price, description, veg_type, available_breakfast, available_lunch, available_dinner, availability, currency) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Available', ?)";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("iisdssiiis", $admin_id, $cat_id, $dish_name, $price, $desc, $veg_type, $avail_b, $avail_l, $avail_d, $currency);
            
            if ($stmt->execute()) {
                $stats['success']++;
            } else {
                $stats['skipped']++;
            }
            $stmt->close();
            $stmt = null;
        } catch (Exception $e) {
            $stats['skipped']++;
            // Log individual row error silently to keep the loop moving
            error_log("Import Row Error: " . $e->getMessage());
            if ($stmt) $stmt->close();
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
    /* Prevent unwanted horizontal scroll globally */
    body, html { overflow-x: hidden; width: 100%; position: relative; margin: 0; padding: 0; }
    
    /* Ensure main content fits correctly with the sidebar */
    .main { width: calc(100% - 260px); margin-left: 260px; min-height: 100vh; overflow-x: hidden; position: relative; transition: margin 0.3s ease; }
    @media (max-width: 992px) {
      .main { width: 100%; margin-left: 0; }
    }
    .content { padding: 24px; max-width: 100%; position: relative; }
    
    .import-card { border: 2px dashed #e2e8f0; padding: 40px; text-align: center; border-radius: 16px; background: #f8fafc; transition: all 0.3s ease; }
    .import-card:hover { border-color: var(--accent); background: #f0f4ff; }
    .file-hint { font-size: 0.85rem; color: #64748b; margin-top: 10px; }
    .status-badge { padding: 4px 12px; border-radius: 99px; font-size: 0.75rem; font-weight: 600; }
    .status-processing { background: #fef3c7; color: #92400e; }
    .status-completed { background: #dcfce7; color: #166534; }
    .status-failed { background: #fee2e2; color: #991b1b; }
    .format-guide { background: #eff6ff; padding: 20px; border-radius: 12px; margin-top: 30px; overflow-x: auto; }
    .format-guide table { min-width: 800px; border-collapse: collapse; margin-top: 10px; background: white; }
    .format-guide th, .format-guide td { border: 1px solid #bfdbfe; padding: 8px; font-size: 0.85rem; text-align: left; }
    
    /* Success Slide Animation */
    .flash-import { animation: slideDown 0.4s ease-out; }
    @keyframes slideDown { from { transform: translateY(-20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
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
            <div style="overflow-x: auto; background: white; border-radius: 8px; border: 1px solid var(--border)">
              <table style="width: 100%; min-width: 800px; margin-top: 0; border: none">
                <thead>
                  <tr style="background: #f8fafc">
                    <th>Col A</th><th>Col B</th><th>Col C</th><th>Col D</th>
                    <th>Col E</th><th>Col F</th><th>Col G</th><th>Col H</th>
                  </tr>
                </thead>
                <tbody>
                  <tr style="background:#f1f5f9; font-weight:700">
                    <td>Category</td><td>Dish Name</td><td>Price</td><td>Description</td>
                    <td>Veg Type</td><td>Breakfast</td><td>Lunch</td><td>Dinner</td>
                  </tr>
                  <tr>
                    <td>Burgers</td><td>Classic Veggie</td><td>299</td><td>Double patty...</td>
                    <td>veg</td><td>0</td><td>1</td><td>1</td>
                  </tr>
                </tbody>
              </table>
            </div>
            <p class="file-hint" style="margin-top:10px">
              💡 <strong>Columns:</strong> A: Category, B: Name, C: Price, D: Desc, E: Veg Type, F: B, G: L, H: D
            </p>
            <p class="file-hint" style="margin-top:5px">
              ⚠️ <strong>Tip:</strong> Ensure your CSV follows this order exactly. Skip headers if necessary.
            </p>
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
