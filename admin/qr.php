<?php
require_once __DIR__ . '/partials/auth_check.php';
require_once __DIR__ . '/../includes/db.php';

$admin_sess_id = $_SESSION['admin_id'] ?? 0;
$qr_dir  = __DIR__ . '/../qr/';
$qr_file = $qr_dir . "menu_qr_{$admin_sess_id}.png";
$url_file = $qr_dir . "qr_url_{$admin_sess_id}.txt";

if (!is_dir($qr_dir)) mkdir($qr_dir, 0755, true);

/* ── Auto-detect the correct public URL for menu.php ── */
$proto    = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host     = $_SERVER['HTTP_HOST'];
$base     = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
$error = null;
$generated = false;
$qr_url = $proto . '://' . $host . rtrim(dirname($base), '/\\') . '/menu.php?id=' . $admin_sess_id . '&src=qr';

// Get restaurant name for the footer using the existing helper function
$restaurant_name = menu_get_setting('restaurant_name', 'THE VINGO', $admin_sess_id);

$force     = isset($_GET['regen']);
$cachedUrl = file_exists($url_file) ? trim(file_get_contents($url_file)) : '';

if ($force || !file_exists($qr_file) || $cachedUrl !== $qr_url) {
    if (file_exists($qr_file)) @unlink($qr_file);

    $generated = false;
    $error     = null;

    /* 1. Try Composer endroid/qr-code (Requires GD enabled + Apache Restart) */
    $autoload = __DIR__ . '/../vendor/autoload.php';
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
  <link rel="stylesheet" href="../assets/css/menu-style.css?v=<?= time() ?>">
  <link rel="icon" type="image/png" href="../assets/images/favicon.png">
  <!-- html2canvas for capture -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
  <!-- QR Code Library for high-res generation -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
  <!-- Premium Font -->
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700&display=swap" rel="stylesheet">
  <style>
    body, html { height: 100vh; margin: 0; padding: 0; }
    .main { min-height: 100vh; display: flex; flex-direction: column; }
    .content { flex: 1; display: flex; align-items: start; justify-content: center; padding: 20px; overflow-y: auto; }
    .qr-card { max-width: 560px; width: 100%; margin: 20px auto; }
    .qr-center { display: flex; flex-direction: column; align-items: center; text-align: center; }
    .btn-md { padding: 12px 18px; font-size: 0.9rem; border-radius: 12px; }
    @media (max-width: 600px) {
      .btn-grp { flex-direction: column; width: 100%; }
      .btn-grp .btn { width: 100%; justify-content: center; }
      .qr-center img { width: 100% !important; height: auto !important; max-width: 250px; }
    }

    /* Hidden Printable Area */
    #printable-layout-container {
        position: fixed;
        left: -9999px;
        top: 0;
    }

    #printable-area {
        background: #ffffff;
        width: 500px;
        padding: 80px 60px;
        display: flex;
        flex-direction: column;
        align-items: center;
        text-align: center;
        font-family: 'Outfit', sans-serif;
    }

    #printable-area h1 {
        font-size: 42px;
        font-weight: 700;
        line-height: 1.1;
        margin-bottom: 16px;
        color: #000;
        letter-spacing: -1px;
    }

    #printable-area p {
        font-size: 20px;
        color: #6b7280;
        font-weight: 400;
        margin-bottom: 60px;
        max-width: 300px;
    }

    #printable-area .qr-wrapper {
        background: #fff;
        padding: 32px;
        border: 2.5px solid #000;
        border-radius: 32px;
        display: flex;
        align-items: center;
        justify-content: center;
        margin-bottom: 60px;
    }

    #printable-area .brand-footer {
        margin-top: auto;
        font-size: 14px;
        font-weight: 500;
        color: #000;
        opacity: 0.8;
        letter-spacing: 1px;
        text-transform: uppercase;
    }
  </style>
</head>
<body>

<?php 
$cur = 'qr.php';
include __DIR__ . '/partials/sidebar.php'; 
?>

<div class="main">
  <div class="topbar">
    <div class="topbar-left" style="display:flex; align-items:center; gap:16px">
      <div class="menu-toggle" id="menuToggle">☰</div>
      <div>
        <h1>QR Code</h1>
        <p class="meta">Download or print your menu QR</p>
      </div>
    </div>
    <div class="topbar-right" style="display:flex; gap:16px; align-items:center">
      <?php include __DIR__ . '/partials/topbar_user.php'; ?>
    </div>
  </div>
  <div class="content">

    <div class="card qr-card">
      <div class="card-title">Menu QR Code</div>

      <?php if ($error): ?>
        <div class="flash flash-danger" style="margin-bottom:16px">
          ❌ <?= htmlspecialchars($error) ?>
        </div>

      <?php endif; ?>

      <?php if ($generated && file_exists($qr_file)): ?>

        <div class="qr-center">
          <div style="background:#fff; padding:20px; border-radius:24px; box-shadow: 0 10px 30px rgba(0,0,0,0.08); margin-bottom:24px">
            <img src="../qr/<?= basename($qr_file) ?>?v=<?= filemtime($qr_file) ?>" alt="Menu QR Code" style="width:280px; height:280px; display:block">
          </div>

          <div style="width:100%;text-align:center">
            <p style="font-size:.78rem;color:var(--muted);margin-bottom:4px;font-weight:600">
              Scans to:
            </p>
            <a href="<?= htmlspecialchars($qr_url) ?>" target="_blank" 
               style="display:block; font-size:.82rem; color:var(--accent); word-break:break-all; padding:10px 14px; background:var(--bg); border-radius:10px; font-family:monospace; text-decoration:none; border:1px solid rgba(59,130,246,0.1); transition:var(--transition)">
              <?= htmlspecialchars($qr_url) ?>
            </a>
          </div>

          <div class="btn-grp" style="justify-content:center; margin-top:20px; gap:10px; flex-wrap:wrap">
            <button id="download-full" class="btn btn-primary btn-md" style="display:flex; align-items:center; gap:8px">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg>
              Download Full Layout
            </button>
            <button id="download-qr-only" class="btn btn-outline btn-md" style="display:flex; align-items:center; gap:8px">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><path d="M7 7h3v3H7z"></path><path d="M14 7h3v3h-3z"></path><path d="M7 14h3v3H7z"></path><path d="M14 14h3v3h-3z"></path></svg>
              Download QR Only
            </button>
            <a href="<?= htmlspecialchars($qr_url) ?>" target="_blank" class="btn btn-outline btn-md">
              🌐 Open Menu
            </a>
          </div>
        </div>

        <!-- Hidden elements for capture -->
        <div id="printable-layout-container">
            <div id="printable-area">
                <h1>Scan the QR<br>For Menu</h1>
                <p>Point your camera to view our menu</p>
                <div class="qr-wrapper">
                    <div id="qr-target"></div>
                </div>
                <div class="brand-footer"><?= htmlspecialchars($restaurant_name) ?></div>
            </div>
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

<script>
document.addEventListener('DOMContentLoaded', () => {
    const qrUrl = "<?= $qr_url ?>";
    const resName = "<?= addslashes($restaurant_name) ?>";
    const qrTarget = document.getElementById('qr-target');
    const printableArea = document.getElementById('printable-area');
    const qrWrapper = document.querySelector('.qr-wrapper');
    const downloadFullBtn = document.getElementById('download-full');
    const downloadQrBtn = document.getElementById('download-qr-only');

    // Generate high-res QR for download
    new QRCode(qrTarget, {
        text: qrUrl,
        width: 256,
        height: 256,
        colorDark: "#000000",
        colorLight: "#ffffff",
        correctLevel: QRCode.CorrectLevel.H
    });

    // Full Layout Download
    downloadFullBtn.addEventListener('click', async () => {
        downloadFullBtn.disabled = true;
        downloadFullBtn.textContent = 'Generating...';
        
        try {
            await document.fonts.ready;
            await new Promise(r => setTimeout(r, 300));
            
            const canvas = await html2canvas(printableArea, {
                scale: 4,
                backgroundColor: '#ffffff',
                useCORS: true
            });
            
            const link = document.createElement('a');
            link.download = `Menu_QR_Full_${resName.replace(/\s+/g, '_')}.png`;
            link.href = canvas.toDataURL("image/png");
            link.click();
        } finally {
            downloadFullBtn.disabled = false;
            downloadFullBtn.innerHTML = `<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v4"></path><polyline points="7 10 12 15 17 10"></polyline><line x1="12" y1="15" x2="12" y2="3"></line></svg> Download Full Layout`;
        }
    });

    // QR Only Download
    downloadQrBtn.addEventListener('click', async () => {
        downloadQrBtn.disabled = true;
        downloadQrBtn.textContent = 'Generating...';
        
        try {
            const canvas = await html2canvas(qrWrapper, {
                scale: 4,
                backgroundColor: '#ffffff',
                useCORS: true
            });
            
            const link = document.createElement('a');
            link.download = `Menu_QR_Only_${resName.replace(/\s+/g, '_')}.png`;
            link.href = canvas.toDataURL("image/png");
            link.click();
        } finally {
            downloadQrBtn.disabled = false;
            downloadQrBtn.innerHTML = `<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><path d="M7 7h3v3H7z"></path><path d="M14 7h3v3h-3z"></path><path d="M7 14h3v3H7z"></path><path d="M14 14h3v3h-3z"></path></svg> Download QR Only`;
        }
    });
});
</script>

</body>
</html>

