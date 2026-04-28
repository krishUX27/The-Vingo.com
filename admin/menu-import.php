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

// ── Pre-flight Cache Control ──────────────────────────────────
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

$error = '';
$success = '';

// ── Directory Setup & Schema Auto-Fix ──────────────────────────
$upload_dir = __DIR__ . '/../uploads/menu_imports/';
if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

// Robust Schema Auto-Fix: Ensure Admin Data Isolation Columns exist on live DB
$conn->query("ALTER TABLE categories ADD COLUMN IF NOT EXISTS user_id INT DEFAULT 0 AFTER name");
$conn->query("ALTER TABLE dishes ADD COLUMN IF NOT EXISTS user_id INT DEFAULT 0 AFTER id");
$conn->query("ALTER TABLE dishes ADD COLUMN IF NOT EXISTS description TEXT AFTER price");

// Legacy Fix: Drop old 'uq_cat_name' if exists (it blocks multi-admin categories)
$conn->query("ALTER TABLE categories DROP INDEX IF EXISTS uq_cat_name");
// New Fix: Ensure category uniqueness per user (Multi-Tenant Index)
$conn->query("ALTER TABLE categories ADD UNIQUE INDEX IF NOT EXISTS u_cat_user (user_id, name)");

ensure_dish_col($conn, 'is_deleted', 'TINYINT(1) DEFAULT 0', 'currency');
ensure_dish_col($conn, 'deleted_at', 'DATETIME NULL', 'is_deleted');
// Compatible Migration Helper
function ensure_dish_col($conn, $col, $def, $after) {
    $check = $conn->query("SHOW COLUMNS FROM dishes LIKE '$col'");
    if ($check && $check->num_rows === 0) {
        $conn->query("ALTER TABLE dishes ADD COLUMN $col $def AFTER $after");
    }
}
ensure_dish_col($conn, 'name', 'VARCHAR(255)', 'category_id');
ensure_dish_col($conn, 'description', 'TEXT', 'name');
ensure_dish_col($conn, 'available_breakfast', 'TINYINT(1) DEFAULT 1', 'veg_type');
ensure_dish_col($conn, 'available_lunch', 'TINYINT(1) DEFAULT 1', 'available_breakfast');
ensure_dish_col($conn, 'available_dinner', 'TINYINT(1) DEFAULT 1', 'available_lunch');

// ── CSV Processing Logic (Dynamic Multi-Language Mode) ─────────
function process_csv_import($file_path, $admin_id, $conn) {
    ini_set('auto_detect_line_endings', true);
    $handle = fopen($file_path, "r");
    if (!$handle) return false;

    // 1. Detect delimiter and parse header
    $first_line = fgets($handle);
    $delimiter = (substr_count($first_line, ';') > substr_count($first_line, ',')) ? ';' : ',';
    rewind($handle);
    
    $header = fgetcsv($handle, 0, $delimiter);
    if (!$header) { fclose($handle); return false; }

    // 2. Identify dynamic column mapping
    $col_map = [];
    $langs = [];
    foreach($header as $idx => $col) {
        $col = strtolower(trim($col));
        if ($col === 'category') $col_map['cat'] = $idx;
        elseif ($col === 'price')    $col_map['price'] = $idx;
        elseif ($col === 'veg_type' || $col === 'veg') $col_map['veg'] = $idx;
        elseif ($col === 'breakfast' || $col === 'b')  $col_map['b'] = $idx;
        elseif ($col === 'lunch' || $col === 'l')      $col_map['l'] = $idx;
        elseif ($col === 'dinner' || $col === 'd')     $col_map['d'] = $idx;
        elseif ($col === 'image' || $col === 'img')    $col_map['img'] = $idx;
        elseif (preg_match('/^name_(.+)$/', $col, $m)) {
            $lang_code = $m[1];
            $langs[$lang_code]['name'] = $idx;
        }
        elseif (preg_match('/^(description|desc)_(.+)$/', $col, $m)) {
            $lang_code = $m[2];
            $langs[$lang_code]['desc'] = $idx;
        }
    }

    $stats = ['total' => 0, 'success' => 0, 'skipped' => 0];
    
    // 3. Cache categories
    $cat_map = [];
    $res = $conn->query("SELECT id, name FROM categories WHERE user_id = $admin_id AND is_deleted = 0");
    if ($res) { while($row = $res->fetch_assoc()) { $cat_map[strtolower(trim($row['name']))] = $row['id']; } }

    // 4. Process Rows
    while (($data = fgetcsv($handle, 0, $delimiter)) !== FALSE) {
        $stats['total']++;
        
        $cat_name    = isset($col_map['cat']) ? trim($data[$col_map['cat']] ?? '') : '';
        $price_raw   = isset($col_map['price']) ? trim($data[$col_map['price']] ?? '0') : '0';
        $veg_raw     = isset($col_map['veg']) ? strtolower(trim($data[$col_map['veg']] ?? 'veg')) : 'veg';
        $avail_b_raw = isset($col_map['b']) ? strtolower(trim($data[$col_map['b']] ?? 'no')) : 'no';
        $avail_l_raw = isset($col_map['l']) ? strtolower(trim($data[$col_map['l']] ?? 'no')) : 'no';
        $avail_d_raw = isset($col_map['d']) ? strtolower(trim($data[$col_map['d']] ?? 'no')) : 'no';
        $image       = isset($col_map['img']) ? trim($data[$col_map['img']] ?? '') : '';

        // Validation: name_en is mandatory as per rules
        $name_en_idx = $langs['en']['name'] ?? -1;
        if ($name_en_idx === -1 || empty(trim($data[$name_en_idx] ?? ''))) {
            $stats['skipped']++;
            continue;
        }

        $price    = (float)str_replace(',', '', preg_replace('/[^0-9.,]/', '', $price_raw));
        $veg_type = (strpos($veg_raw, 'non') !== false) ? 'non_veg' : 'veg';
        
        $is_yes = function($v) { return in_array(strtolower(trim($v)), ['1', 'y', 'yes', 'true', 'available']); };
        $avail_b = $is_yes($avail_b_raw) ? 1 : 0;
        $avail_l = $is_yes($avail_l_raw) ? 1 : 0;
        $avail_d = $is_yes($avail_d_raw) ? 1 : 0;

        try {
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
                } else { $cat_id = $cat_map[$cat_key]; }
            }

            // Insert Base Dish
            $currency = menu_get_setting('currency', 'INR', $admin_id);
            $name_en  = trim($data[$name_en_idx] ?? '');
            $stmt = $conn->prepare("INSERT INTO dishes (user_id, category_id, name, price, veg_type, available_breakfast, available_lunch, available_dinner, image, currency) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("iisdsiiiss", $admin_id, $cat_id, $name_en, $price, $veg_type, $avail_b, $avail_l, $avail_d, $image, $currency);
            
            if ($stmt->execute()) {
                $dish_id = $conn->insert_id;
                
                // Insert Translations Dynamically
                $t_stmt = $conn->prepare("INSERT INTO dish_translations (dish_id, language_code, name, description) VALUES (?, ?, ?, ?)");
                foreach($langs as $lang_code => $indices) {
                    $name_val = trim($data[$indices['name']] ?? '');
                    if ($name_val === '' && $lang_code !== 'en') continue; // Skip optional empty translations
                    
                    $desc_idx = $indices['desc'] ?? -1;
                    $desc_val = ($desc_idx !== -1) ? trim($data[$desc_idx] ?? '') : '';
                    
                    $t_stmt->bind_param("isss", $dish_id, $lang_code, $name_val, $desc_val);
                    $t_stmt->execute();
                }
                $t_stmt->close();
                $stats['success']++;
            } else {
                $stats['skipped']++;
                file_put_contents(__DIR__ . '/import_errors.log', "[" . date('Y-m-d H:i:s') . "] Insert Failed: " . $conn->error . "\n", FILE_APPEND);
            }
            $stmt->close();
        } catch (Exception $e) {
            $stats['skipped']++;
            $stats['last_err'] = $e->getMessage();
            file_put_contents(__DIR__ . '/import_errors.log', "[" . date('Y-m-d H:i:s') . "] Exception: " . $e->getMessage() . "\n", FILE_APPEND);
        }
        }
    }
    fclose($handle);
    return $stats;
}

// ── Handle Upload Post ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['menu_file'])) {
    $file = $_FILES['menu_file'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if ($ext !== 'csv') {
        $error = "Please use a CSV file.";
    } elseif ($file['error'] !== UPLOAD_ERR_OK) {
        $error = "Upload failed.";
    } else {
        $file_path = $upload_dir . uniqid('import_') . '.csv';
        if (move_uploaded_file($file['tmp_name'], $file_path)) {
            $stmt = $conn->prepare("INSERT INTO menu_imports (admin_id, file_name, file_type, file_path, status) VALUES (?, ?, 'csv', ?, 'processing')");
            $stmt->bind_param("iss", $admin_id, $file['name'], $file_path);
            $stmt->execute();
            $import_id = $conn->insert_id;
            $stmt->close();

            try {
                $res = process_csv_import($file_path, $admin_id, $conn);
                if ($res) {
                    $conn->query("UPDATE menu_imports SET status = 'completed' WHERE id = $import_id");
                    $success = "{$res['success']} dishes imported successfully!";
                }
            } catch (Exception $e) {
                $conn->query("UPDATE menu_imports SET status = 'failed' WHERE id = $import_id");
                $error = $e->getMessage();
            }
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
  <link rel="icon" type="image/png" href="../assets/images/favicon.png">
  <style>
    /* ── BASE RESPONSIVE OVERRIDES ── */
    *, *::before, *::after { box-sizing: border-box; }
    
    .content { 
      padding: 30px 40px; 
      max-width: 1400px; 
      width: 100%; 
      margin: 0 auto; 
      transition: all 0.3s ease; 
    }
    
    @media (max-width: 1024px) {
      .main { margin-left: 0 !important; width: 100% !important; min-width: 0 !important; }
      .content { padding: 20px 10px; width: 100% !important; }
      .import-grid { grid-template-columns: 1fr !important; gap: 20px !important; }
      .import-grid > div { min-width: 0 !important; overflow: hidden; } /* Prevent grid items from pushing width */
      
      /* Card Sizing */
      .card { 
        padding: 20px 15px !important; 
        margin: 0 auto 20px auto !important;
        width: 100% !important; 
        max-width: 100% !important;
        border-radius: 12px !important;
      }
      
      /* Upload Area */
      .import-card { 
        padding: 20px !important; 
        width: 100% !important; 
        margin: 0 auto !important;
        min-height: 140px !important;
      }
      
      /* Table Scroll Scaling */
      .scroll-mobile { 
        width: 100%;
        max-width: 100%;
        overflow-x: auto !important; 
        -webkit-overflow-scrolling: touch;
        display: block;
        border: 1px solid var(--border);
        border-radius: 8px;
        margin: 10px 0;
        background: #fff;
      }
      .scroll-mobile table { min-width: 600px; width: 100%; border-collapse: collapse; }
    }
    
    @media (max-width: 480px) {
      .topbar { padding: 0 10px !important; height: 65px !important; }
      .topbar h1 { font-size: 1rem !important; }
    }
    
    /* ── DESKTOP & SHARED STYLES ── */
    .import-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 30px; }
    .import-grid > div { min-width: 0; }
    
    .import-card { 
      border: 2px dashed #e2e8f0; 
      padding: 40px; 
      text-align: center; 
      border-radius: 16px; 
      background: #f8fafc; 
      transition: all 0.3s ease; 
      cursor: pointer; 
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      max-width: 100%;
    }
    .import-card:hover { border-color: var(--accent); background: #f0f4ff; transform: translateY(-2px); }
    
    .file-hint { font-size: 0.85rem; color: #64748b; margin-top: 10px; }
    .status-badge { padding: 4px 12px; border-radius: 99px; font-size: 0.75rem; font-weight: 600; }
    
    .format-guide { background: #eff6ff; padding: 20px; border-radius: 12px; margin-top: 25px; border: 1px solid #dbeafe; width: 100%; overflow: hidden; box-sizing: border-box; }
    .format-guide table { border-collapse: collapse; margin-top: 10px; background: white; width: 100%; min-width: 600px; }
    .format-guide th, .format-guide td { border: 1px solid #bfdbfe; padding: 10px; font-size: 0.8rem; text-align: left; white-space: nowrap; }
    .scroll-mobile { overflow-x: auto; width: 100%; margin-bottom: 15px; border-radius: 8px; border: 1px solid #dbeafe; }
    
    /* Success Slide Animation */
    .flash-import { animation: slideDown 0.4s ease-out; }
    @keyframes slideDown { from { transform: translateY(-20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
    
    .hide-mobile { display: table-cell; }
    @media (max-width: 768px) {
        .hide-mobile { display: none !important; }
    }
    
    /* Strict body containment */
    body { width: 100%; min-height: 100vh; overflow-x: hidden; margin: 0; padding: 0; position: relative; }
    .main { min-width: 0; }
  </style>
</head>
<body>

<?php include __DIR__ . '/partials/sidebar.php'; ?>

<div class="main">
  <div class="topbar">
    <div class="topbar-left" style="display:flex; align-items:center; gap:16px; min-width:0; flex:1;">
      <div class="menu-toggle" id="menuToggle">☰</div>
      <h1 style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">📥 Menu Import Center</h1>
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

    <div class="import-grid">
      
      <div>
        <div class="card">
          <div class="card-title">Upload Menu Document</div>
          <form method="POST" enctype="multipart/form-data">
            <div class="import-card" onclick="document.getElementById('fileInput').click()">
              <div style="font-size: 3rem; margin-bottom: 15px;">📄</div>
              <p>Click to browse or drag and drop your CSV file</p>
              <input type="file" name="menu_file" id="fileInput" style="display:none" accept=".csv" onchange="this.form.submit()">
              <p class="file-hint">Supported: .csv (Max 10MB)</p>
            </div>
          </form>

          <div class="format-guide">
            <strong>📊 CSV Format Requirement</strong>
            <p style="font-size: 0.8rem; margin: 10px 0;">To ensure your dishes are correctly added, use the following column order:</p>
            <div class="scroll-mobile">
              <table style="width: 100%; border: none">
                <thead>
            <div class="scroll-mobile">
              <table style="width: 100%; border: none">
                <thead>
                  <tr style="background: #f8fafc">
                    <th>A</th><th>B</th><th>C</th><th>D</th><th>E</th><th>F</th><th>G</th><th>H</th><th>I</th><th>...</th>
                  </tr>
                </thead>
                <tbody>
                  <tr style="background:#f1f5f9; font-weight:700">
                    <td>Category</td><td>Price</td><td>Veg</td><td>B</td><td>L</td><td>D</td><td>Img</td><td>name_en</td><td>desc_en</td><td>name_XX</td>
                  </tr>
                  <tr>
                    <td>Burgers</td><td>299</td><td>veg</td><td>No</td><td>Yes</td><td>Yes</td><td>b.jpg</td><td>Veg Burger</td><td>...</td><td>Other Lang</td>
                  </tr>
                </tbody>
              </table>
            </div>
            <p class="file-hint" style="margin-top:10px">
              💡 <strong>Requirement:</strong> name_en is mandatory. You can add unlimited languages by adding columns like <strong>name_ta, description_ta, name_hi, etc.</strong>
            </p>
          </div>
        </div>

        <div class="card" style="margin-top: 30px;">
          <div class="card-title">Recent Imports</div>
          <div class="table-wrap scroll-mobile">
            <table style="width:100%; border-collapse: collapse;">
              <thead>
                <tr>
                  <th>File Name</th>
                  <th class="hide-mobile">Type</th>
                  <th>Date</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($history as $h): ?>
                <tr>
                  <td><?= htmlspecialchars($h['file_name']) ?></td>
                  <td class="hide-mobile"><small style="text-transform: uppercase;"><?= $h['file_type'] ?></small></td>
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
          <h3 style="margin-bottom:15px">Why use CSV Import?</h3>
          <p style="font-size: 0.9rem; line-height: 1.5; opacity: 0.9;">
            Avoid manual entry! Upload your formatted CSV price list and our system will populate your digital menu card instantly.
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

<script src="../assets/js/menu-script.js?v=<?= time() ?>"></script>
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
