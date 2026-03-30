<?php
// superadmin/index.php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../includes/db.php';

// System Stats (Mock Data for Demo)
$total_dishes = $conn->query("SELECT COUNT(*) FROM dishes")->fetch_row()[0];
$total_categories = $conn->query("SELECT COUNT(*) FROM categories")->fetch_row()[0];
$total_offers = $conn->query("SELECT COUNT(*) FROM seasonal_offers")->fetch_row()[0];

$cur = 'index.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Master Console | Vingo Platform</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="../assets/css/menu-style.css?v=<?= time() ?>">
  <link rel="icon" type="image/png" href="../assets/images/favicon.png">
  <style>
    :root { 
      --super-accent: #f59e0b; 
      --super-sidebar: #0f172a; 
      --super-sidebar-h: #1e293b;
    }
    
    .sidebar { background: var(--super-sidebar) !important; border-right: 1px solid rgba(255,255,255,0.05) !important; }
    .sidebar-header { color: var(--super-accent) !important; }
    .sidebar-header span:first-child { background: var(--super-accent) !important; box-shadow: 0 4px 12px rgba(245,158,11,0.2) !important; }
    .sidebar nav a:hover { background: var(--super-sidebar-h) !important; color: #fff; }
    .sidebar nav a.active { background: var(--super-accent) !important; color: #0f172a !important; box-shadow: 0 4px 20px rgba(245,158,11,0.2) !important; }
    
    .card-stat { background: #fff; border-radius: 20px; padding: 24px; border: 1px solid var(--border); transition: 0.3s; }
    .card-stat:hover { transform: translateY(-5px); border-color: var(--super-accent); box-shadow: 0 10px 30px rgba(0,0,0,0.05); }
    .stat-val { font-size: 2rem; font-weight: 800; color: #0f172a; margin-top: 10px; }
    .stat-label { font-size: 0.8rem; text-transform: uppercase; color: var(--text-light); font-weight: 700; letter-spacing: 0.05em; }
    
    .system-badge { display: inline-flex; align-items: center; gap: 8px; padding: 6px 12px; border-radius: 30px; background: #ecfdf5; color: #059669; font-size: 0.8rem; font-weight: 700; }
    .system-badge.warn { background: #fffbeb; color: #d97706; }
  </style>
</head>
<body>

<?php include __DIR__ . '/partials/super_sidebar.php'; ?>

<div class="main">
  <div class="topbar">
    <div class="topbar-left" style="display:flex; align-items:center; gap:16px">
      <div class="menu-toggle" id="menuToggle">☰</div>
      <div>
        <h1>🛡️ Master Root Console</h1>
        <p class="meta">Global Platform Administration & Security</p>
      </div>
    </div>
    <div class="topbar-right" style="display:flex; gap:16px; align-items:center">
      <div class="system-badge"><span style="width:8px; height:8px; background:#10b981; border-radius:50%"></span> System: Stable</div>
      <?php include __DIR__ . '/../admin/partials/topbar_user.php'; ?>
    </div>
  </div>

  <div class="content">
    <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap:24px; margin-bottom:30px">
      <div class="card-stat">
        <div class="stat-label">Total Platform Items</div>
        <div class="stat-val"><?= $total_dishes ?></div>
      </div>
      <div class="card-stat">
        <div class="stat-label">Active Categories</div>
        <div class="stat-val"><?= $total_categories ?></div>
      </div>
      <div class="card-stat">
        <div class="stat-label">Active Campaigns</div>
        <div class="stat-val"><?= $total_offers ?></div>
      </div>
      <div class="card-stat">
        <div class="stat-label">System Health</div>
        <div class="stat-val" style="color:#10b981">99.8%</div>
      </div>
    </div>

    <div style="display:grid; grid-template-columns: 2fr 1fr; gap:24px">
      <div class="card">
        <div class="card-title">🛡️ System Security Logs</div>
        <div class="table-wrap">
          <table style="width:100%; border-collapse:collapse">
            <thead>
              <tr style="text-align:left; border-bottom:1px solid var(--border)">
                <th style="padding:16px">Event</th>
                <th style="padding:16px">Source</th>
                <th style="padding:16px">Status</th>
                <th style="padding:16px">Timestamp</th>
              </tr>
            </thead>
            <tbody>
              <tr style="border-bottom:1px solid var(--border)">
                <td style="padding:16px; font-weight:600">Root Login Successful</td>
                <td style="padding:16px; font-family:monospace">127.0.0.1</td>
                <td style="padding:16px"><span style="color:#10b981">Verified</span></td>
                <td style="padding:16px; font-size:0.85rem">Just now</td>
              </tr>
              <tr style="border-bottom:1px solid var(--border)">
                <td style="padding:16px; font-weight:600">API Cache Rebuilt</td>
                <td style="padding:16px; font-family:monospace">System Cron</td>
                <td style="padding:16px"><span style="color:#3b82f6">Complete</span></td>
                <td style="padding:16px; font-size:0.85rem">12 mins ago</td>
              </tr>
              <tr>
                <td style="padding:16px; font-weight:600">DB Schema Verification</td>
                <td style="padding:16px; font-family:monospace">CloudSync</td>
                <td style="padding:16px"><span style="color:#10b981">Healthy</span></td>
                <td style="padding:16px; font-size:0.85rem">1 hour ago</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <div class="card">
        <div class="card-title">💡 System Quick Links</div>
        <div style="display:flex; flex-direction:column; gap:12px">
          <a href="../admin/dashboard.php" class="btn btn-outline" style="justify-content:flex-start; width:100%">📊 Operator Dashboard</a>
          <a href="../menu.php" target="_blank" class="btn btn-outline" style="justify-content:flex-start; width:100%">🌐 Public Live Menu</a>
          <a href="../admin/qr.php" class="btn btn-outline" style="justify-content:flex-start; width:100%">📱 QR Manager</a>
          <div style="height:1px; background:var(--border); margin:10px 0"></div>
          <button class="btn btn-warn" style="justify-content:center; width:100%; border-radius:30px">⚡ Force System Sync</button>
        </div>
      </div>
    </div>
  </div>
</div>



</body>
</html>
