<?php
// qr.php — Updated to use Reliable QR API with Composer Fallback
require_once __DIR__ . '/includes/db.php';

$qr_dir  = __DIR__ . '/qr/';
$qr_file = $qr_dir . 'menu_qr.png';
$url_file = $qr_dir . 'qr_url.txt';

if (!is_dir($qr_dir)) mkdir($qr_dir, 0755, true);

/* ── Auto-detect the correct public URL for menu.php ── */
$proto    = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host     = $_SERVER['HTTP_HOST'];
$base     = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
$error = null;
$generated = false;
$qr_url = $proto . '://' . $host . $base . '/menu.php';

$force     = isset($_GET['regen']);
$cachedUrl = file_exists($url_file) ? trim(file_get_contents($url_file)) : '';

if ($force || !file_exists($qr_file) || $cachedUrl !== $qr_url) {
    if (file_exists($qr_file)) @unlink($qr_file);

    $generated = false;
    $error     = null;

    /* 1. Try Composer endroid/qr-code (Requires GD enabled + Apache Restart) */
    $autoload = __DIR__ . '/vendor/autoload.php';
    if (file_exists($autoload)) {
        require_once $autoload;
        try {
            $qrCode = new \Endroid\QrCode\QrCode($qr_url);
            $qrCode->setSize(300)->setMargin(10);
            $writer = new \Endroid\QrCode\Writer\PngWriter();
            $writer->write($qrCode)->saveToFile($qr_file);
            $generated = true;
        } catch (\Throwable $e) {
            // Log composer error but move to fallback
        }
    }

    /* 2. Reliable Fallback: qrserver.com API (Works without GD) */
    if (!$generated) {
        $api = 'https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . urlencode($qr_url);
        $img = @file_get_contents($api);
        
        if ($img === false && function_exists('curl_init')) {
            $ch = curl_init($api);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $img = curl_exec($ch);
            curl_close($ch);
        }

        if ($img !== false && !empty($img)) {
            file_put_contents($qr_file, $img);
            $generated = true;
        } else {
            $error = 'QR generation failed. Please ensure internet is accessible or enable "gd" extension in php.ini and restart Apache.';
        }
    }

    if ($generated) {
        file_put_contents($url_file, $qr_url);
    }

    if ($force && $generated) {
        header('Location: qr.php');
        exit;
    }
} else {
    $generated = true;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>QR Code — Menu Manager</title>
  <link rel="stylesheet" href="assets/css/menu-style.css">
</head>
<body>

<aside class="sidebar">
  <div class="sidebar-brand"><span>🍴</span> Menu Manager</div>
  <nav>
    <a href="admin/dashboard.php"><span class="nav-icon">📊</span> Dashboard</a>
    <a href="admin/add-item.php"><span class="nav-icon">➕</span> Add Dish</a>
    <a href="admin/add-category.php"><span class="nav-icon">📂</span> Categories</a>
    <a href="menu.php" target="_blank"><span class="nav-icon">🌐</span> Live Menu ↗</a>
    <a href="print-menu.php" target="_blank"><span class="nav-icon">🖨️</span> Print Menu ↗</a>
    <a href="qr.php" class="active"><span class="nav-icon">📱</span> QR Code</a>
    <a href="generate_pdf.php" target="_blank"><span class="nav-icon">📄</span> Download PDF</a>
  </nav>
  <div class="sidebar-footer">Menu Manager v2</div>
</aside>

<div class="main">
  <div class="topbar"><h1>📱 QR Code</h1></div>
  <div class="content">

    <div class="card" style="max-width:500px;margin:0 auto">
      <div class="card-title">Menu QR Code</div>

      <?php if ($error): ?>
        <div class="flash flash-danger" style="margin-bottom:16px">
          ❌ <?= htmlspecialchars($error) ?>
        </div>
      <?php endif; ?>

      <?php if ($generated && file_exists($qr_file)): ?>

        <div class="qr-center">
          <img src="qr/menu_qr.png?v=<?= filemtime($qr_file) ?>" alt="Menu QR Code">

          <div style="width:100%;text-align:center">
            <p style="font-size:.78rem;color:var(--muted);margin-bottom:4px;font-weight:600">
              Scans to:
            </p>
            <p style="font-size:.82rem;color:var(--accent);word-break:break-all;padding:8px 12px;background:var(--bg);border-radius:8px;font-family:monospace">
              <?= htmlspecialchars($qr_url) ?>
            </p>
          </div>

          <div class="btn-grp" style="justify-content:center">
            <a href="qr/menu_qr.png" download="menu_qr.png" class="btn btn-primary">
              ⬇️ Download QR
            </a>
            <a href="<?= htmlspecialchars($qr_url) ?>" target="_blank" class="btn btn-outline">
              🌐 Open Menu
            </a>
            <a href="qr.php?regen=1" class="btn btn-warn"
               onclick="return confirm('Regenerate QR code with current URL?')">
              🔄 Regenerate
            </a>
          </div>

          <p style="font-size:.74rem;color:var(--muted);text-align:center;margin-top:4px">
            Click <strong>🔄 Regenerate</strong> if QR points to the wrong page.
          </p>
        </div>

      <?php else: ?>
        <div class="no-data">
          <span class="nd-icon">📱</span>
          <p>QR generation failed.</p>
          <a href="qr.php?regen=1" class="btn btn-primary" style="margin-top:16px">Try Again</a>
        </div>
      <?php endif; ?>
    </div>

  </div>
</div>

</body>
</html>

