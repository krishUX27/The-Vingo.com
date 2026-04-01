<?php
// superadmin/index.php - Platform Overview Dashboard
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/logger.php';

// 1. Fetch Global Stats
// Ensure Table Exists (Auto-Migration)
$conn->query("CREATE TABLE IF NOT EXISTS qr_scans (user_id INT PRIMARY KEY, scan_count INT DEFAULT 0)");

// Total Hotels (Admins)
$hotel_res = $conn->query("SELECT COUNT(*) FROM users WHERE role = 'admin'");
$total_hotels = $hotel_res ? $hotel_res->fetch_row()[0] : 0;

// Total Dishes
$dish_res = $conn->query("SELECT COUNT(*) FROM dishes");
$total_dishes = ($dish_res && $res = $dish_res->fetch_row()) ? $res[0] : 0;

// Total QR Scans
$scan_res = $conn->query("SELECT SUM(scan_count) FROM qr_scans");
$total_scans = ($scan_res && $res = $scan_res->fetch_row()) ? (int)$res[0] : 0;

// Total Active Menus (Hotels with at least 1 dish)
$active_res = $conn->query("SELECT COUNT(DISTINCT user_id) FROM dishes");
$total_active = ($active_res && $res = $active_res->fetch_row()) ? $res[0] : 0;

// Admin accounts for the modal
$admin_res = $conn->query("SELECT id, username, email FROM users WHERE role = 'admin' ORDER BY username");
$all_admins = $admin_res ? $admin_res->fetch_all(MYSQLI_ASSOC) : [];

$cur = 'index.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Master Overview | Vingo Master</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="../assets/css/menu-style.css?v=<?= time() ?>">
  <link rel="icon" type="image/png" href="../assets/images/favicon.png">
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
    
    .btn-primary { background: var(--super-accent) !important; color: #0f172a !important; font-weight: 800 !important; }
    .btn-outline:hover { border-color: var(--super-accent) !important; color: var(--super-accent) !important; }
    .user-avatar { background: var(--super-accent) !important; color: #0f172a !important; box-shadow: 0 4px 12px var(--super-accent-glow) !important; }

    /* Stats Dashboard */
    .overview-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 24px; margin-bottom: 40px; }
    .stat-card-master { background: #fff; padding: 24px; border-radius: 20px; border: 1px solid var(--border); display: flex; align-items: center; gap: 20px; transition: 0.3s; }
    .stat-card-master:hover { transform: translateY(-5px); box-shadow: 0 12px 30px rgba(0,0,0,0.05); }
    .m-stat-icon { width: 60px; height: 60px; border-radius: 16px; background: #f8fafc; font-size: 1.8rem; display: flex; align-items: center; justify-content: center; }
    .m-stat-info { display: flex; flex-direction: column; }
    .m-stat-val { font-size: 1.8rem; font-weight: 900; color: #0f172a; line-height: 1; }
    .m-stat-label { font-size: 0.8rem; color: #64748b; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; margin-top: 4px; }
    
    /* Yellow accents for icons */
    .icon-yellow { background: #fffbeb; color: var(--super-accent); }

    /* Modal Overlay */
    .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(15, 23, 42, 0.4); backdrop-filter: blur(4px); z-index: 2000; }
    .modal-overlay.open { display: block; animation: fadeIn 0.3s; }
    
    /* Modal Content */
    .modal { display: none; position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); width: min(450px, 95vw); background: #fff; border-radius: 24px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); z-index: 2100; overflow: hidden; }
    .modal.open { display: block; animation: modalIn 0.3s ease-out; }
    
    .modal-header { padding: 20px 24px; background: #f8fafc; display: flex; align-items: center; justify-content: space-between; border-bottom: 1px solid var(--border); }
    .modal-header h3 { font-size: 1.1rem; font-weight: 800; color: #0f172a; margin: 0; }
    .modal-close { background: none; border: none; font-size: 1.5rem; color: var(--text-light); cursor: pointer; line-height: 1; transition: 0.2s; }
    .modal-close:hover { color: var(--super-accent); transform: rotate(90deg); }

    @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
    @keyframes modalIn { from { opacity: 0; transform: translate(-50%, -60%); } to { opacity: 1; transform: translate(-50%, -50%); } }
    @keyframes pulse { 0% { opacity: 1; } 50% { opacity: 0.5; } 100% { opacity: 1; } }
  </style>
</head>
<body>

<?php include __DIR__ . '/partials/super_sidebar.php'; ?>

<div class="main">
  <div class="topbar">
    <div class="topbar-left" style="display:flex; align-items:center; gap:16px">
      <div class="menu-toggle" id="menuToggle">☰</div>
      <h1>🛡️ Platform Overview</h1>
    </div>
    <div class="topbar-right" style="display:flex; gap:16px; align-items:center">
      <div style="background:#f0fdf4; color:#16a34a; padding:6px 16px; border-radius:30px; font-size:0.75rem; font-weight:700; display:flex; align-items:center; gap:6px">
        <span style="width:8px; height:8px; background:#16a34a; border-radius:50%"></span> System Stable
      </div>
      <?php include __DIR__ . '/../admin/partials/topbar_user.php'; ?>
    </div>
  </div>

  <div class="content">
    
    <!-- Platform Analytics Cards -->
    <div class="overview-grid">
      <div class="stat-card-master">
        <div class="m-stat-icon icon-yellow">🏨</div>
        <div class="m-stat-info">
          <div class="m-stat-val"><?= $total_hotels ?></div>
          <div class="m-stat-label">Total Hotels</div>
        </div>
      </div>
      <div class="stat-card-master">
        <div class="m-stat-icon icon-yellow">🍽️</div>
        <div class="m-stat-info">
          <div class="m-stat-val"><?= $total_dishes ?></div>
          <div class="m-stat-label">Total Dishes</div>
        </div>
      </div>
      <div class="stat-card-master">
        <div class="m-stat-icon icon-yellow">📱</div>
        <div class="m-stat-info">
          <div class="m-stat-val"><?= $total_scans ?></div>
          <div class="m-stat-label">Total QR Scans</div>
        </div>
      </div>
      <div class="stat-card-master">
        <div class="m-stat-icon icon-yellow">🔗</div>
        <div class="m-stat-info">
          <div class="m-stat-val"><?= $total_active ?></div>
          <div class="m-stat-label">Active Menus</div>
        </div>
      </div>
    </div>

    <div style="display:grid; grid-template-columns: 2fr 1fr; gap:30px; align-items:start">
      
      <div class="card">
        <div class="card-title">📡 Global Operations Update</div>
        <div style="padding:40px; text-align:center">
          <div style="font-size:3rem">🚀</div>
          <h2 style="margin:20px 0; color:#0f172a">Vingo Platform is Healthy</h2>
          <p style="color:#64748b; font-size:0.95rem; max-width:500px; margin:0 auto">All restaurant systems are operational across the cloud. Master Root Console is monitoring real-time dish availability and QR engagement metrics.</p>
          <div style="margin-top:30px; display:inline-flex; align-items:center; gap:8px; background:#f8fafc; color:#475569; padding:12px 24px; border-radius:50px; font-weight:700; border:1px solid var(--border)">
            Live Security Monitoring Active
          </div>
        </div>
      </div>

      <div class="card">
        <div class="card-title">💡 System Quick Links</div>
        <div style="display:flex; flex-direction:column; gap:12px">
          <button class="btn btn-outline" onclick="openAccessModal('console')" style="justify-content:flex-start; width:100%">🔑 Access Admin Console</button>
          <button class="btn btn-outline" onclick="openAccessModal('dashboard')" style="justify-content:flex-start; width:100%">📊 Admin Dashboard</button>
          <button class="btn btn-outline" onclick="openAccessModal('menu')" style="justify-content:flex-start; width:100%">🌐 Public Live Menu</button>
          <button class="btn btn-outline" onclick="openAccessModal('qr')" style="justify-content:flex-start; width:100%">📱 QR Manager</button>
          <div style="height:1px; background:var(--border); margin:10px 0"></div>
          <button class="btn btn-warn" style="justify-content:center; width:100%; border-radius:30px">⚡ Force System Sync</button>
        </div>
      </div>
    </div>

  </div>
</div>

<!-- Admin Access Modal -->
<div id="accessModalOverlay" class="modal-overlay"></div>
<div id="accessModal" class="modal" style="width: min(500px, 95vw)">
  <div class="modal-header">
    <h3 id="modalTitle">🔑 Select Account / Hotel</h3>
    <button type="button" class="modal-close" id="closeAccessModal">&times;</button>
  </div>
  <div style="padding: 16px 20px; border-bottom: 1px solid var(--border); background: #f8fafc">
    <div style="position:relative">
      <span style="position:absolute; left:12px; top:50%; transform:translateY(-50%); opacity:0.4">🔍</span>
      <input type="text" id="hotelSearch" placeholder="Search accounts or hotels..." 
             style="width:100%; padding:10px 10px 10px 36px; border-radius:12px; border: 1px solid var(--border); outline:none; font-size:0.9rem">
    </div>
  </div>
  <div class="modal-body" style="padding:0">
    <div id="adminListWrap" style="max-height:400px; overflow-y:auto">
      <?php if (empty($all_admins)): ?>
        <p style="padding:40px; text-align:center; color:var(--text-light)">No admin accounts found.</p>
      <?php else: ?>
        <table style="width:100%; border-collapse:collapse" id="hotelTable">
          <tbody>
            <?php foreach ($all_admins as $adm): ?>
              <tr class="hotel-row" data-username="<?= strtolower(htmlspecialchars($adm['username'])) ?>" style="border-bottom:1px solid var(--border)">
                <td style="padding:16px 20px">
                  <div style="font-weight:700; color:#0f172a"><?= htmlspecialchars($adm['username']) ?></div>
                  <div style="font-size:0.75rem; color:var(--text-light)"><?= htmlspecialchars($adm['email'] ?: 'No email') ?></div>
                </td>
                <td style="padding:16px 20px; text-align:right">
                  <button onclick="accessAccount(<?= $adm['id'] ?>)" class="btn btn-primary btn-sm" style="border-radius:30px">🚀 Access</button>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
  const accessModal = document.getElementById('accessModal');
  const accessOverlay = document.getElementById('accessModalOverlay');
  const closeAccessBtn = document.getElementById('closeAccessModal');
  const hotelSearch = document.getElementById('hotelSearch');
  
  let currentTarget = 'console';

  function openAccessModal(target) {
    currentTarget = target;
    const titles = {
      'console': '🔑 Select Account Console',
      'dashboard': '📊 Select Hotel Dashboard',
      'menu': '🌐 Select Hotel Menu',
      'qr': '📱 Select Hotel QR Manager'
    };
    document.getElementById('modalTitle').innerText = titles[target] || '🔑 Select Account';
    
    accessModal.classList.add('open');
    accessOverlay.classList.add('open');
    document.body.style.overflow = 'hidden';
    hotelSearch.value = '';
    filterHotels('');
    setTimeout(() => hotelSearch.focus(), 100);
  }

  function hideAccessModal() {
    accessModal.classList.remove('open');
    accessOverlay.classList.remove('open');
    document.body.style.overflow = 'auto';
  }

  function filterHotels(q) {
    q = q.toLowerCase();
    const rows = document.querySelectorAll('.hotel-row');
    rows.forEach(row => {
      const match = row.getAttribute('data-username').includes(q);
      row.style.display = match ? '' : 'none';
    });
  }

  function accessAccount(adminId) {
    window.location.href = `impersonate.php?id=${adminId}&target=${currentTarget}`;
  }

  if(hotelSearch) hotelSearch.addEventListener('input', (e) => filterHotels(e.target.value));
  if(closeAccessBtn) closeAccessBtn.addEventListener('click', hideAccessModal);
  if(accessOverlay) accessOverlay.addEventListener('click', hideAccessModal);
</script>

</body>
</html>
