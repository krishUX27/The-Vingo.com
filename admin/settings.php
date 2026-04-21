<?php
// admin/settings.php
require_once __DIR__ . '/partials/auth_check.php';
require_once __DIR__ . '/../includes/db.php';

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$admin_sess_id = $_SESSION['admin_id'] ?? 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $settings = [
        'restaurant_name' => $_POST['restaurant_name'] ?? '',
        'restaurant_sub' => $_POST['restaurant_sub'] ?? ''
    ];

    foreach ($settings as $key => $val) {
        $stmt = $conn->prepare("INSERT INTO settings (setting_key, setting_value, user_id) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        $stmt->bind_param('ssi', $key, $val, $admin_sess_id);
        $stmt->execute();
    }
    
    $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Settings updated successfully!'];
    header('Location: settings.php');
    exit;
}

$cur = 'settings.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Global Settings — Vingo Menu</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="../assets/css/menu-style.css?v=<?= time() ?>">
  <link rel="icon" type="image/png" href="../assets/images/favicon.png">
</head>
<body>

<?php include __DIR__ . '/partials/sidebar.php'; ?>

<div class="main">
  <div class="topbar">
    <div class="topbar-left" style="display:flex; align-items:center; gap:16px">
      <div class="menu-toggle" id="menuToggle">☰</div>
      <div>
        <h1>⚙️ Restaurant Settings</h1>
        <p class="meta">Manage your branding and site settings</p>
      </div>
    </div>
    <div class="topbar-right" style="display:flex; gap:16px; align-items:center">
      <?php include __DIR__ . '/partials/topbar_user.php'; ?>
    </div>
  </div>

  <div class="content">
    <?php if ($flash): ?>
      <div class="flash flash-<?= $flash['type'] ?>" style="margin-bottom:20px"><?= $flash['msg'] ?></div>
    <?php endif; ?>

    <div class="card" style="max-width:720px; margin: 0 auto">
      <div class="card-title">Brand Customization</div>
      <form method="POST">
        <div class="form-group">
          <label>Restaurant Name</label>
          <input type="text" name="restaurant_name" value="<?= htmlspecialchars(menu_get_setting('restaurant_name', 'Restaurant Name', $admin_sess_id)) ?>" required>
        </div>
        
        <div class="form-group">
          <label>Menu Subheader (Tagline)</label>
          <input type="text" name="restaurant_sub" value="<?= htmlspecialchars(menu_get_setting('restaurant_sub', 'Welcome to our digital menu', $admin_sess_id)) ?>" placeholder="e.g. Traditional Fine Dining Since 1994">
        </div>        <div style="margin-top:30px; border-top:1px solid var(--border); padding-top:24px">
          <button type="submit" class="btn btn-primary" style="width:100%; justify-content:center">
            💾 Save All Settings
          </button>
        </div>
      </form>
    </div>
    

  </div>
</div>

</body>
</html>
