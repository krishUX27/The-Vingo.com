<?php
// superadmin/settings.php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../includes/db.php';

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$error = '';
$success = '';

// Handle Settings Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_settings') {
    foreach ($_POST['settings'] as $key => $value) {
        $key = $conn->real_escape_string($key);
        $value = $conn->real_escape_string($value);

        // INSERT/UPDATE setting
        $conn->query("INSERT INTO settings (setting_key, setting_value) 
                      VALUES ('$key', '$value') 
                      ON DUPLICATE KEY UPDATE setting_value = '$value'");
    }
    $_SESSION['flash'] = ['type' => 'success', 'msg' => "Platform settings updated!"];
    header('Location: settings.php');
    exit;
}

// Fetch all settings
$res = $conn->query("SELECT * FROM settings");
$s = [];
while ($row = $res->fetch_assoc()) {
    $s[$row['setting_key']] = $row['setting_value'];
}

$cur = 'settings.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>System Settings | Vingo Master</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="../assets/css/menu-style.css?v=<?= time() ?>">
  <style>
    :root { 
      --super-accent: #f59e0b; 
      --super-accent-glow: rgba(245, 158, 11, 0.3);
      --super-sidebar: #0f172a; 
      --super-sidebar-h: #1e293b;
    }
    
    .sidebar { background: var(--super-sidebar) !important; border-right: 1px solid rgba(255,255,255,0.05) !important; }
    .sidebar-header { color: var(--super-accent) !important; }
    .sidebar-header span:first-child { background: var(--super-accent) !important; box-shadow: 0 4px 12px var(--super-accent-glow) !important; }
    .sidebar nav a:hover { background: var(--super-sidebar-h) !important; color: var(--super-accent) !important; }
    .sidebar nav a.active { background: var(--super-accent) !important; color: #0f172a !important; box-shadow: 0 4px 20px var(--super-accent-glow) !important; }

    .main { min-height: 100vh; }
    .content { padding: 30px; }
    .settings-table td { padding: 16px 0; vertical-align: middle; border-bottom: 1px solid var(--border); }
    .settings-table .s-label { font-weight: 700; color: #475569; width: 200px; font-size: 0.85rem; text-transform: uppercase; }
    
    .btn-primary { background: var(--super-accent); color: #0f172a; font-weight: 800; border: none; }
    .btn-primary:hover { background: #fbbf24; transform: translateY(-2px); box-shadow: 0 8px 20px var(--super-accent-glow); }
    
    input:focus { border-color: var(--super-accent) !important; box-shadow: 0 0 0 4px var(--super-accent-glow) !important; }
    .user-avatar { background: var(--super-accent) !important; color: #0f172a !important; box-shadow: 0 4px 12px var(--super-accent-glow) !important; }
  </style>
</head>
<body>

<?php include __DIR__ . '/partials/super_sidebar.php'; ?>

<div class="main">
  <div class="topbar">
    <div class="topbar-left" style="display:flex; align-items:center; gap:16px">
      <div class="menu-toggle" id="menuToggle">☰</div>
      <h1>🛠️ Global Platform Settings</h1>
    </div>
    <div class="topbar-right">
      <?php include __DIR__ . '/../admin/partials/topbar_user.php'; ?>
    </div>
  </div>

  <div class="content">
    <?php if ($flash): ?>
      <div class="flash flash-<?= $flash['type'] ?>"><?= $flash['msg'] ?></div>
    <?php endif; ?>

    <div class="card" style="max-width:800px">
      <div class="card-title">⚙️ System Configuration</div>
      <form method="POST">
        <input type="hidden" name="action" value="update_settings">
        
        <table class="settings-table" style="width:100%; border-collapse:collapse">
          <tbody>
            <tr>
              <td class="s-label">Restaurant Name</td>
              <td>
                <input type="text" name="settings[restaurant_name]" value="<?= htmlspecialchars($s['restaurant_name'] ?? 'Vingo Menu') ?>" placeholder="e.g. Tasty Bites">
              </td>
            </tr>
            <tr>
              <td class="s-label">Tagline / Subtitle</td>
              <td>
                <input type="text" name="settings[restaurant_sub]" value="<?= htmlspecialchars($s['restaurant_sub'] ?? '') ?>" placeholder="e.g. Delicious meals, crafted with love">
              </td>
            </tr>
            <tr>
              <td class="s-label">Contact Email</td>
              <td>
                <input type="email" name="settings[contact_email]" value="<?= htmlspecialchars($s['contact_email'] ?? '') ?>" placeholder="e.g. info@restaurant.com">
              </td>
            </tr>
            <tr>
              <td class="s-label">Contact Phone</td>
              <td>
                <input type="text" name="settings[contact_phone]" value="<?= htmlspecialchars($s['contact_phone'] ?? '') ?>" placeholder="e.g. +1 234 567 890">
              </td>
            </tr>
            <tr>
              <td class="s-label">Currency Symbol</td>
              <td>
                <input type="text" name="settings[currency_symbol]" value="<?= htmlspecialchars($s['currency_symbol'] ?? '₹') ?>" style="width:100px">
              </td>
            </tr>
          </tbody>
        </table>

        <div style="margin-top:30px; text-align:right">
          <button type="submit" class="btn btn-primary">💾 Save All Changes</button>
        </div>
      </form>
    </div>
  </div>
</div>

</body>
</html>
