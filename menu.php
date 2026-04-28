<?php
require_once __DIR__ . '/includes/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$user_id = intval($_GET['user_id'] ?? $_GET['id'] ?? 0);

// 1. Initial Identity Check
$is_owner = (isset($_SESSION['admin_id']) && (int)$_SESSION['admin_id'] === $user_id);
$is_super = (isset($_SESSION['super_logged_in']) && $_SESSION['super_logged_in'] === true);

// 2. QR Scan Tracking Logic (Deduplicated per Session)
if ($user_id > 0 && ($_GET['src'] ?? '') === 'qr') {
    if (!$is_owner && !$is_super) {
        $scan_key = "qr_scanned_{$user_id}";

        if (!isset($_SESSION[$scan_key])) {
            // 1. Detailed Logging
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
            $stmt = $conn->prepare("INSERT INTO qr_scan_logs (admin_id, ip_address, device_info) VALUES (?, ?, ?)");
            if ($stmt) {
                $stmt->bind_param("iss", $user_id, $ip, $ua);
                $stmt->execute();
                $stmt->close();
            }

            // 2. Legacy Summary Update
            $conn->query("INSERT INTO qr_scans (user_id, scan_count) VALUES ($user_id, 1) ON DUPLICATE KEY UPDATE scan_count = scan_count + 1");
            
            // Mark as scanned for this session to prevent count on refresh
            $_SESSION[$scan_key] = true;
        }
    }
}

$restaurant_name = menu_get_setting('restaurant_name', 'Vingo Menu', $user_id);
$restaurant_sub  = menu_get_setting('restaurant_sub',  'Premium Digital Selection', $user_id);

// Fetch active offers for this user
$offers = [];
if ($user_id) {
    // Current date check for active offers
    $today = date('Y-m-d');
    $off_res = $conn->query("SELECT * FROM offers WHERE user_id = $user_id AND status = 'active' AND '$today' BETWEEN start_date AND end_date AND is_deleted = 0 ORDER BY created_at DESC");
    if ($off_res) $offers = $off_res->fetch_all(MYSQLI_ASSOC);
}

// ── Admin Drag & Drop Check ──────────────────────────────────────────────────
$is_admin_view = (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true);
$can_reorder   = ($is_admin_view && ($is_owner || $is_super));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $restaurant_name ?> — Vingo Menu</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="icon" type="image/png" href="assets/images/favicon.png">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
      --bg:      #fcfcfc;
      --surface: #ffffff;
      --border:  rgba(0,0,0,0.05);
      --text:    #1a1a1a;
      --muted:   #888888;
      --header-bg: #2b45b0; /* Deep blue from reference */
      --card-blue: #6386ff; /* Offer card blue */
      --card-red:  #e4635d;  /* Offer card red accent */
    }

    html, body {
      overflow-x: hidden;
      width: 100%;
      scroll-behavior: smooth;
    }

    body {
      font-family: 'Inter', sans-serif;
      background-color: #ffffff;
      color: var(--text);
      min-height: 100vh;
    }

    /* ── Reference Redesign Header ── */
    .header-section {
      background: var(--header-bg);
      padding: 60px 20px 40px;
      text-align: center;
      color: #fff;
    }
    .restaurant-name {
      font-size: 2.8rem;
      font-weight: 800;
      margin-bottom: 5px;
      letter-spacing: -1px;
    }
    .sticky-controls {
      position: -webkit-sticky;
      position: sticky;
      top: 0;
      background: var(--header-bg);
      z-index: 1000;
      padding: 15px 0 20px;
      margin-top: -1px;
      box-shadow: 0 10px 25px rgba(0,0,0,0.2);
      border-bottom: 1px solid rgba(255,255,255,0.05);
      transition: all 0.3s ease;
    }
    @media(max-width: 768px) {
      .sticky-controls { padding: 12px 0 15px; }
    }
    .search-filter-row {
      display: flex;
      gap: 12px;
      align-items: center;
      max-width: 650px;
      margin: 0 auto 18px;
      padding: 0 20px;
    }
    .search-input-wrap {
      flex: 1;
      position: relative;
    }
    .search-inner-btn {
      position: absolute;
      right: 6px;
      top: 50%;
      transform: translateY(-50%);
      background: #3b82f6;
      color: #fff;
      border: none;
      width: 38px;
      height: 38px;
      border-radius: 50%;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 0.85rem;
      transition: all 0.2s;
      z-index: 2;
    }
    .search-inner-btn:hover { background: #2563eb; transform: translateY(-50%) scale(1.05); }
    .search-input-wrap input {
      width: 100%;
      padding: 14px 55px 14px 22px;
      border-radius: 50px;
      border: none;
      font-size: 1rem;
      outline: none;
      box-shadow: 0 4px 10px rgba(0,0,0,0.1);
    }
    .ref-filter-btn {
      background: #334155;
      color: #fff;
      border: none;
      padding: 12px 18px;
      border-radius: 50px;
      font-weight: 600;
      font-size: 0.9rem;
      display: flex;
      align-items: center;
      gap: 6px;
      cursor: pointer;
      box-shadow: 0 4px 10px rgba(0,0,0,0.1);
    }

    /* ── Reference Offer Cards ── */
    .offers-container {
      margin-top: -15px;
      padding: 0 20px;
      max-width: 600px;
      margin-left: auto;
      margin-right: auto;
    }
    .offer-carousel-ref {
      display: flex;
      gap: 0;
      overflow-x: auto;
      scrollbar-width: none;
      padding: 10px 0;
      scroll-snap-type: x mandatory;
    }
    .offer-carousel-ref::-webkit-scrollbar { display: none; }
    
    .offer-card-ref {
      flex: 0 0 100%;
      min-width: 100%;
      height: 160px;
      background: var(--card-blue);
      border-radius: 24px;
      position: relative;
      overflow: hidden;
      color: #fff;
      scroll-snap-align: center;
      padding: 30px;
      display: flex;
      align-items: center; /* Align items horizontally */
      justify-content: flex-start; /* Start from left */
      box-shadow: 0 8px 20px rgba(0,0,0,0.2);
    }
    .offer-card-ref .accent-shape {
      position: absolute;
      top: -30px;
      left: -30px;
      width: 140px;
      height: 140px;
      background: var(--card-red);
      border-radius: 50%;
      z-index: 1;
    }
    .offer-content-ref {
      position: relative;
      z-index: 2;
      display: flex;
      flex-direction: column;
      gap: 2px;
      margin-left: 90px;
      text-align: left;
    }
    @media(max-width: 500px) {
      .offer-card-ref { padding: 20px; height: 140px; border-radius: 20px; }
      .offer-content-ref { margin-left: 70px; }
      .offer-discount-text { font-size: 2.2rem; }
      .offer-title-text { font-size: 1.05rem; }
      .offer-sub-text { font-size: 0.8rem; }
      .offer-card-ref .accent-shape { width: 110px; height: 110px; top: -20px; left: -20px; }
    }
    .offer-discount-text {
      font-size: 2.8rem;
      font-weight: 900;
      line-height: 1;
      margin-bottom: 2px;
    }
    .offer-title-text {
      font-size: 1.25rem;
      font-weight: 800;
      line-height: 1.2;
    }
    .offer-sub-text {
      font-size: 0.9rem;
      opacity: 0.85;
      font-weight: 500;
    }

    .dots-container {
      display: flex;
      justify-content: center;
      gap: 8px;
      margin-top: 15px;
    }
    .ref-dot {
      width: 8px;
      height: 8px;
      background: rgba(255,255,255,0.3);
      border-radius: 50%;
      transition: 0.3s;
    }
    .ref-dot.active {
      background: #fff;
    }

    /* ── Filter Pills (Original kept for logic but hidden/restyled) ── */
    .filter-row {
      display: none; /* We use the modal now */
    }

    /* ── Dish Grid ── */
    .dish-list {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 20px;
      padding: 0;
    }
    
    @media(min-width: 1024px) {
      .dish-list { grid-template-columns: repeat(3, 1fr); }
    }

    @media(max-width: 768px) {
      .dish-list { grid-template-columns: 1fr 1fr; gap: 12px; }
      .menu-body { padding: 30px 15px 80px; }
    }

    @media(max-width: 480px) {
      .dish-list { grid-template-columns: 1fr; }
    }

    /* ── Menu Body ── */
    .menu-body {
      max-width: 900px;
      margin: 0 auto;
      padding: 40px 30px 100px; /* Added side padding for margins below header */
    }

    /* ── Category Heading ── */
    .cat-section { margin-bottom: 8px; }
    .cat-section[hidden] { display: none !important; }

    .cat-title {
      font-size: 1.05rem;
      font-weight: 800;
      color: var(--text);
      padding: 12px 16px 6px;
      letter-spacing: -.2px;
    }

    /* ── Dish Row ── */
    .dish-row {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 16px;
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 16px;
      transition: all 0.2s;
    }
    .dish-row:hover { background: #fafafa; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.03); }

    .dish-img-wrap {
      width: 70px;
      height: 70px;
      border-radius: 12px;
      overflow: hidden;
      flex-shrink: 0;
      background: #f0f0f0;
      display: flex;
      align-items: center;
      justify-content: center;
      border: 1px solid var(--border);
    }
    @media(max-width: 400px) {
      .dish-img-wrap { width: 60px; height: 60px; }
      .dish-row { gap: 8px; padding: 12px; }
      .dish-name { font-size: 0.82rem; }
      .dish-price { font-size: 0.85rem; }
    }
    .dish-img-wrap img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }
    .dish-picon { font-size: 1.5rem; opacity: .4; }

    .dish-info { flex: 1; min-width: 0; }

    .dish-name {
      font-size: .88rem;
      font-weight: 700;
      color: var(--text);
      line-height: 1.3;
      word-break: break-word;
      white-space: normal;
    }
    .dish-desc {
      font-size: 0.72rem;
      color: var(--muted);
      font-weight: 400;
      margin-top: 2px;
      line-height: 1.3;
      word-break: break-word;
      white-space: normal;
    }
    .dish-badge {
      display: inline-flex;
      align-items: center;
      gap: 5px;
      margin-top: 3px;
      font-size: .76rem;
      color: var(--muted);
    }
    .bdot {
      width: 8px; height: 8px;
      border-radius: 50%;
      flex-shrink: 0;
    }
    .dish-price {
      font-size: 0.95rem;
      font-weight: 800;
      color: var(--text);
      flex-shrink: 0;
      white-space: nowrap;
    }



    /* ── Skeleton ── */
    #skeleton-wrap .skel-line {
      background: linear-gradient(90deg,#ececec 25%,#f8f8f8 50%,#ececec 75%);
      background-size: 200% 100%;
      animation: sk 1.3s infinite;
      border-radius: 4px;
    }
    @keyframes sk { to { background-position: -200% 0; } }
    .sk-row {
      display: flex;
      align-items: center;
      gap: 16px;
      padding: 16px;
      background: #fff;
      border-bottom: 1px solid var(--border);
    }

    /* ── Empty ── */
    .empty {
      text-align: center;
      padding: 60px 20px;
      color: var(--muted);
    }

    /* ── Toast ── */
    #toast {
      position: fixed;
      bottom: 22px; left: 50%;
      transform: translateX(-50%) translateY(60px);
      background: rgba(17,17,17,.9);
      color: #fff;
      padding: 10px 22px;
      border-radius: 30px;
      font-size: .8rem;
      font-weight: 600;
      z-index: 999;
      opacity: 0;
      transition: transform .25s, opacity .25s;
      pointer-events: none;
    }
    #toast.show { transform: translateX(-50%) translateY(0); opacity: 1; }

    /* ── Inline Filter Pill ── */
    .filter-trigger {
      width: auto;
      height: 40px;
      background: #6366f1;
      color: #fff;
      padding: 0 18px;
      border-radius: 100px;
      display: flex;
      align-items: center;
      gap: 8px;
      font-weight: 700;
      font-size: 0.75rem;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3);
      cursor: pointer;
      flex-shrink: 0;
      transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
    }
    .filter-trigger:hover { transform: scale(1.05); background: #4f46e5; }
    .filter-trigger:active { transform: scale(0.95); }

    /* ── Filter Modal ── */
    #modalOverlay {
      position: fixed;
      inset: 0;
      background: rgba(0,0,0,0.5);
      backdrop-filter: blur(8px);
      z-index: 2000;
      display: flex;
      align-items: flex-end;
      justify-content: center;
      opacity: 0;
      pointer-events: none;
      transition: opacity 0.3s ease;
    }
    #modalOverlay.show { opacity: 1; pointer-events: auto; }

    .modal-content {
      background: #fff;
      width: 100%;
      max-width: 500px;
      border-radius: 24px 24px 0 0;
      padding: 32px 24px;
      transform: translateY(100%);
      transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1);
    }
    #modalOverlay.show .modal-content { transform: translateY(0); }

    .modal-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 24px;
    }
    .modal-title { font-size: 1.25rem; font-weight: 800; }
    .close-modal { font-size: 1.5rem; cursor: pointer; color: var(--muted); }

    .filter-group { display: flex; flex-direction: column; gap: 8px; margin-bottom: 18px; }
    .filter-group label { font-size: .75rem; font-weight: 700; color: var(--text); letter-spacing: 0.5px; text-transform: uppercase; }
    .filter-group input, .filter-group select {
      padding: 14px 16px;
      border: 1px solid var(--border);
      border-radius: 12px;
      font-family: inherit;
      font-size: 1rem;
      background: #f8fafc;
      outline: none;
      transition: border-color .15s;
    }
    .filter-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }

    .btn-apply {
      background: #111;
      color: #fff;
      width: 100%;
      padding: 16px;
      border-radius: 14px;
      font-weight: 700;
      font-size: 1rem;
      border: none;
      cursor: pointer;
      margin-top: 10px;
    }
    .btn-reset-light {
      background: #f1f5f9;
      color: #64748b;
      width: 100%;
      padding: 14px;
      border-radius: 14px;
      font-weight: 600;
      font-size: 0.9rem;
      border: none;
      margin-top: 12px;
      cursor: pointer;
    }

    /* Dot color pool */
    .dc-0{background:#27ae60} .dc-1{background:#6c63ff} .dc-2{background:#e67e22}
    .dc-3{background:#e74c3c} .dc-4{background:#3498db} .dc-5{background:#9b59b6}
    .dc-6{background:#1abc9c} .dc-7{background:#f39c12}

    @media(max-width:600px){
      .filter-grid { 
        grid-template-columns: 1fr !important; 
        gap: 15px;
      }
      .filter-group.full-mobile { grid-column: span 1; }
      .filter-actions { margin-top: 10px; }
      .restaurant-card { width: 95%; padding: 20px 15px; }
      .dish-row { gap: 12px; padding: 14px; }
      .dish-img-wrap { width: 60px; height: 60px; }
      .dish-name { font-size: 0.9rem; }
      .dish-price { font-size: 0.9rem; }
    }
    
    @media(max-width:480px){
      .restaurant-name { font-size: 1.25rem; }
      .cat-title { font-size: 0.95rem; }
    }

    /* ── Meal Tabs ── */
    .meal-tabs {
      display: flex;
      justify-content: flex-start;
      gap: 12px;
      margin-bottom: 0;
      padding: 5px 0 0;
      overflow-x: auto;
      scrollbar-width: none;
      -webkit-overflow-scrolling: touch;
    }
    .meal-tabs::before, .meal-tabs::after {
      content: '';
      flex: 0 0 20px;
    }
    @media(min-width: 769px) {
      .meal-tabs { justify-content: center; }
      .meal-tabs::before, .meal-tabs::after { display: none; }
    }
    .meal-tabs::-webkit-scrollbar { display: none; }
    .meal-tab {
      padding: 11px 22px;
      border-radius: 50px;
      background: rgba(255,255,255,0.1); /* Glassmorphism style */
      color: #fff;
      font-weight: 700;
      font-size: 0.82rem;
      cursor: pointer;
      border: 1px solid rgba(255,255,255,0.1);
      transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
      white-space: nowrap;
      flex-shrink: 0;
    }
    .meal-tab:hover { background: rgba(255,255,255,0.2); transform: translateY(-1px); }
    .meal-tab.active {
      background: #fff !important;
      color: var(--header-bg) !important;
      box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    }

    /* ── Admin Drag Handle ── */
    <?php if ($can_reorder): ?>
    .drag-handle {
      cursor: grab;
      padding: 0 8px;
      color: #ccc;
      font-size: 1.2rem;
      display: flex;
      align-items: center;
      user-select: none;
      transition: color 0.2s;
    }
    .drag-handle:hover { color: #6366f1; }
    .drag-handle:active { cursor: grabbing; }
    .sortable-ghost { opacity: 0.3; background: #eef2ff !important; border: 1px dashed #6366f1 !important; }
    .sortable-chosen { box-shadow: 0 10px 25px rgba(0,0,0,0.1); }
    .dish-row.dragging { background: #f8fafc; }
    
    #admin-reorder-msg {
      position: fixed;
      top: 20px;
      left: 50%;
      transform: translateX(-50%);
      background: rgba(99, 102, 241, 0.95);
      color: #fff;
      padding: 8px 20px;
      border-radius: 50px;
      font-size: 0.85rem;
      font-weight: 600;
      z-index: 9999;
      box-shadow: 0 10px 25px rgba(99, 102, 241, 0.3);
      display: flex;
      align-items: center;
      gap: 10px;
      backdrop-filter: blur(5px);
    }
    <?php endif; ?>
  </style>
  <?php if ($can_reorder): ?>
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
  <?php endif; ?>
</head>
<body>

<!-- Redesigned Header per Reference -->
<div class="header-section">
  <div class="restaurant-name"><?= $restaurant_name ?></div>
  
  <!-- Dynamic Offer Carousel -->
  <div class="offers-container" id="offers-container" style="display:none">
    <div class="offer-carousel-ref" id="refCarousel">
       <!-- JS will inject offer-card-ref here -->
    </div>
    
    <div class="dots-container" id="refDots">
       <!-- JS will inject dots here -->
    </div>
  </div>
</div>

<div class="sticky-controls">
  <!-- Search & Filter Row -->
  <div class="search-filter-row">
    <div class="search-input-wrap">
      <input type="text" id="refSearch" placeholder="Search for dishes..." onkeyup="syncSearch(this.value)">
      <button class="search-inner-btn" onclick="applyAdvancedFilters()" type="button">🔍</button>
    </div>
    <select id="langSwitch" style="background:#334155; color:#fff; border:none; padding:12px 10px; border-radius:50px; font-weight:600; font-size:0.85rem; cursor:pointer; outline:none; box-shadow:0 4px 10px rgba(0,0,0,0.1)">
      <option value="en">🇬🇧 EN</option>
      <option value="ta">🇮🇳 TA</option>
    </select>
    <button class="ref-filter-btn" id="openFilter">
      Filter <span style="font-size: 0.7rem">▼</span>
    </button>
  </div>

  <!-- Meal Selections -->
  <div class="meal-tabs">
    <button class="meal-tab active" data-meal="all">All Menu</button>
    <button class="meal-tab" data-meal="breakfast">🌅 Breakfast</button>
    <button class="meal-tab" data-meal="lunch">☀️ Lunch</button>
    <button class="meal-tab" data-meal="dinner">🌙 Dinner</button>
  </div>
</div>

<script>
// Sync reference search with the hidden advanced filter search
function syncSearch(val) {
  document.getElementById('f-search').value = val;
  applyAdvancedFilters();
}
</script>

<!-- Filter Modal Overlay -->
<div id="modalOverlay">
  <div class="modal-content">
    <div class="modal-header">
      <div class="modal-title">Filter Options</div>
      <div class="close-modal" id="closeFilter">✕</div>
    </div>
    
    <!-- Hidden search field for filter logic -->
    <input type="hidden" id="f-search" value="">

    <div class="filter-group">
      <label>Category</label>
      <select id="f-category">
        <option value="all">All Categories</option>
      </select>
    </div>

    <div class="filter-group">
      <label>Dietary Preference</label>
      <select id="f-diet">
        <option value="all">All Dishes</option>
        <option value="veg">Veg Only 🟩</option>
        <option value="non_veg">Non-Veg Only 🟥</option>
      </select>
    </div>

    <div class="filter-grid-2">
      <div class="filter-group">
        <label>Price Range</label>
        <div style="display:flex; align-items:center; gap:8px">
          <input type="number" id="f-min" placeholder="Min" style="width:50%; padding: 10px">
          <input type="number" id="f-max" placeholder="Max" style="width:50%; padding: 10px">
        </div>
      </div>
    </div>

    <button id="btn-apply" class="btn-apply">Show Results</button>
    <button id="btn-reset" class="btn-reset-light">Reset All</button>
  </div>
</div>

<!-- Filter Pills -->
<div class="filter-row" id="filter-row" style="display:none">
  <button class="pill active" data-cat="all">All</button>
</div>

<!-- Menu Body -->
<div class="menu-body" id="menu-body">
  <!-- Skeleton -->
  <div id="skeleton-wrap">
    <?php for ($i = 0; $i < 5; $i++): ?>
    <div class="sk-row">
      <div class="skel-line" style="width:70px;height:70px;border-radius:12px;flex-shrink:0"></div>
      <div style="flex:1">
        <div class="skel-line" style="height:14px;width:55%;margin-bottom:7px"></div>
        <div class="skel-line" style="height:10px;width:30%"></div>
      </div>
      <div class="skel-line" style="height:14px;width:60px"></div>
      <div class="skel-line" style="width:34px;height:34px;border-radius:50%"></div>
    </div>
    <?php endfor; ?>
  </div>
</div>

<div id="toast">⚡ Menu updated</div>

<?php if ($can_reorder): ?>
<div id="admin-reorder-msg">
  <span style="font-size:1.2rem">⠿</span> 
  Admin: Drag dishes to reorder the live menu
</div>
<?php endif; ?>

<script>
const URL_PARAMS      = new URLSearchParams(window.location.search);
const MENU_ID         = URL_PARAMS.get('user_id') || URL_PARAMS.get('id') || 0;
const POLL_MS         = 6000;

// Language persistence
let currentLang = localStorage.getItem('vingo_lang') || 'en';
const langSwitch = document.getElementById('langSwitch');
if(langSwitch) {
    langSwitch.value = currentLang;
    langSwitch.addEventListener('change', (e) => {
        currentLang = e.target.value;
        localStorage.setItem('vingo_lang', currentLang);
        syncVingoMenu(true); // Force update
    });
}

/* Dot color pool */
const DOT_COLORS = ['dc-0','dc-1','dc-2','dc-3','dc-4','dc-5','dc-6','dc-7'];
const catColorMap = {};
let   catColorIdx = 0;
function catDot(name) {
  if (!catColorMap[name]) {
    catColorMap[name] = DOT_COLORS[catColorIdx++ % DOT_COLORS.length];
  }
  return catColorMap[name];
}

let lastHash     = '';
let fullData     = null; // Unified menu data
let activeMeal   = 'all';
const CAN_REORDER = <?php echo $can_reorder ? 'true' : 'false'; ?>;

async function syncVingoMenu(force = false) {
  try {
    const r = await fetch(`api/get_menu_data.php?user_id=${MENU_ID}&lang=${currentLang}&_=${Date.now()}`);
    const json = await r.json();
    if (!json.success) return;

    const hash = JSON.stringify(json.data);
    if (!force && hash === lastHash) return;
    lastHash = hash;

    const first = fullData === null;
    fullData = json.data;

    // 1. Render Offers Slider
    renderOffers(fullData.offers);
    
    // 2. Render Dishes
    renderMenu(fullData.dishes, first);

    // Re-apply filters if not first load
    if (!first) {
      showToast();
      applyAdvancedFilters(); 
    }
  } catch(e) { console.error('[Menu Sync]', e); }
}

function renderOffers(offers) {
    const container = document.getElementById('offers-container');
    const carousel  = document.getElementById('refCarousel');
    const dots      = document.getElementById('refDots');

    if (!offers || offers.length === 0) {
       container.style.display = 'none';
       return;
    }

    container.style.display = 'block';
    carousel.innerHTML = offers.map(o => {
        let discountHtml = '';
        if (o.offer_type === 'seasonal') {
          discountHtml = `${o.discount_percentage}% OFF`;
        } else {
          discountHtml = `Combo ₹${parseFloat(o.combo_price).toFixed(0)}`;
        }

        let subText = o.description || '';
        if (o.offer_type === 'combo' && o.combo_items && o.combo_items.length > 0) {
           subText = `<div style="font-size:0.75rem; margin-top:5px; opacity:0.9">Includes: ${o.combo_items.join(' + ')}</div>`;
        }

        return `
          <div class="offer-card-ref">
            <div class="accent-shape"></div>
            <div class="offer-content-ref">
              <div class="offer-discount-text">${discountHtml}</div>
              <div class="offer-title-text">${esc(o.title)}</div>
              <div class="offer-sub-text">${subText}</div>
            </div>
          </div>`;
    }).join('');

    dots.innerHTML = offers.map((_, i) => `<div class="ref-dot ${i===0?'active':''}" data-idx="${i}"></div>`).join('');
    initCarouselEvents();
}

function initCarouselEvents() {
    const carousel = document.getElementById('refCarousel');
    const dots     = document.querySelectorAll('#refDots .ref-dot');
    if (!carousel || dots.length === 0) return;

    const getSlideWidth = () => {
       const slide = carousel.querySelector('.offer-card-ref');
       return slide ? slide.offsetWidth : 0;
    };

    carousel.onscroll = () => {
        const sw = getSlideWidth();
        const index = Math.round(carousel.scrollLeft / sw);
        dots.forEach((d, i) => d.classList.toggle('active', i === index));
    };

    dots.forEach(dot => {
        dot.onclick = () => {
            const idx = parseInt(dot.getAttribute('data-idx'));
            carousel.scrollTo({ left: idx * getSlideWidth(), behavior: 'smooth' });
        };
    });
}

/* ── HTML escape ── */
function esc(s) {
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;')
    .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

/* ── Build dish row ── */
function dishRow(d, dotClass) {
  const symbol = d.currency === 'USD' ? '$' : '₹';
  const imgUrl = d.image ? `uploads/${d.image}` : 'assets/images/dish-placeholder.png';
  
  const imgHtml = `<img src="${esc(imgUrl)}" alt="${esc(d.name)}" onerror="this.src='assets/images/dish-placeholder.png'; this.onerror=null;">`;

  let priceHtml = `<span class="dish-price" style="flex-shrink:0">${symbol}${parseFloat(d.price).toFixed(2)}</span>`;
  
  if (d.offer_discount) {
    const origPrice = parseFloat(d.price);
    let finalPrice = origPrice;
    
    // Parse discount (e.g. "20%")
    if (d.offer_discount.includes('%')) {
       const pct = parseFloat(d.offer_discount.replace('%',''));
       finalPrice = origPrice * (1 - (pct/100));
    } else {
       const amt = parseFloat(d.offer_discount);
       finalPrice = origPrice - amt;
    }
    if (finalPrice < 0) finalPrice = 0;

    priceHtml = `
      <div style="display:flex; flex-direction:column; align-items:flex-end; gap:2px; flex-shrink:0">
        <span style="font-size:0.65rem; background:#dcfce7; color:#166534; padding:2px 6px; border-radius:4px; font-weight:800">${esc(d.offer_discount)} OFF</span>
        <span style="font-size:0.75rem; text-decoration:line-through; color:var(--muted)">${symbol}${origPrice.toFixed(2)}</span>
        <span class="dish-price" style="color:#16a34a">${symbol}${finalPrice.toFixed(2)}</span>
      </div>`;
  }

  return `
    <div class="dish-row" ${CAN_REORDER ? `data-id="${d.id}"` : ''}>
      ${CAN_REORDER ? '<div class="drag-handle" title="Drag to reorder">⠿</div>' : ''}
      <div class="dish-img-wrap">
        ${imgHtml}
      </div>
      <div class="dish-info">
        <div class="dish-name">
          ${d.veg_type === 'veg' ? '<span style="color:#16a34a; font-size:0.75rem">🟩</span>' : '<span style="color:#dc2626; font-size:0.75rem">🟥</span>'}
          ${esc(d.name)}
          ${d.description ? `<div class="dish-desc" style="font-size:0.72rem; color:var(--muted); font-weight:400; margin-top:2px; line-height:1.2">${esc(d.description)}</div>` : ''}
        </div>
        <div class="dish-badge">
          <span class="bdot ${dotClass}"></span>${esc(d.category)}
        </div>
        ${d.offer_title ? `<div style="font-size:0.7rem; color:#16a34a; font-weight:700; margin-top:2px">🎁 ${esc(d.offer_title)}</div>` : ''}
        ${d.combo_names ? `<div style="font-size:0.65rem; color:#6366f1; font-weight:700; border-top:1px solid rgba(0,0,0,0.03); margin-top:4px; padding-top:4px">🍱 Part of: ${esc(d.combo_names)}</div>` : ''}
      </div>
      ${priceHtml}
    </div>`;
}

/* ── Build Categories for Select ── */
function populateCategories(cats) {
  const sel = document.getElementById('f-category');
  const current = sel.value;
  sel.innerHTML = '<option value="all">All Categories</option>';
  cats.forEach(c => {
    const opt = document.createElement('option');
    opt.value = c;
    opt.textContent = c;
    if (c === current) opt.selected = true;
    sel.appendChild(opt);
  });
}

/* ── Client-side Filter Logic ── */
function applyAdvancedFilters() {
  if (!fullData || !fullData.dishes) return;
  const data  = fullData.dishes;
  const q     = (document.getElementById('f-search').value || '').toLowerCase().trim();
  const cat   = document.getElementById('f-category').value;
  const diet  = document.getElementById('f-diet').value;
  const min   = parseFloat(document.getElementById('f-min').value) || 0;
  const max   = parseFloat(document.getElementById('f-max').value) || 999999;

  const filtered = {};
  
  // data is grouped by category: { "Main": [...], "Drinks": [...] }
  Object.keys(data).forEach(categoryName => {
    // 1. Category check
    if (cat !== 'all' && categoryName !== cat) return;

    const matches = data[categoryName].filter(d => {
      // 2. Search
      if (q !== '' && !d.name.toLowerCase().includes(q)) return false;
      // 3. Diet Preference
      if (diet !== 'all' && d.veg_type !== diet) return false;
      // 4. Meal Time
      if (activeMeal !== 'all') {
        const mealKey = `available_${activeMeal}`;
        if (!d[mealKey]) return false;
      }
      // 5. Price
      if (d.price < min || d.price > max) return false;
      return true;
    });

    if (matches.length > 0) {
      filtered[categoryName] = matches;
    }
  });

  renderMenu(filtered, false); // false = don't rebuild categories
}

/* ── Render full menu ── */
function renderMenu(grouped, isFirstLoad = true) {
  const body = document.getElementById('menu-body');
  const cats = Object.keys(grouped);

  if (!cats.length) {
    body.innerHTML = `<div class="empty">🍽️ No dishes match your filters.</div>`;
    return;
  }

  // Pre-assign colors
  cats.forEach(c => catDot(c));

  if (isFirstLoad) {
    populateCategories(cats);
  }

  body.innerHTML = cats.map(cat => {
    const dot  = catDot(cat);
    const rows = grouped[cat].map(d => dishRow(d, dot)).join('');
    return `
      <div class="cat-section" data-cat="${esc(cat)}">
        <div class="cat-title">${esc(cat)}</div>
        <div class="dish-list">${rows}</div>
      </div>`;
  }).join('');

  if (CAN_REORDER) {
    initDragAndDrop();
  }
}

/* ── Admin Drag & Drop Initialization ── */
function initDragAndDrop() {
  if (typeof Sortable === 'undefined') return;
  
  const lists = document.querySelectorAll('.dish-list');
  lists.forEach(el => {
    Sortable.create(el, {
      handle: '.drag-handle',
      animation: 200,
      ghostClass: 'sortable-ghost',
      chosenClass: 'sortable-chosen',
      onEnd: function() {
        const orderedIds = [];
        // Since dishes might be across different category containers, 
        // we collect them globally from the whole body to maintain the absolute sequence.
        document.querySelectorAll('.dish-row[data-id]').forEach(row => {
          orderedIds.push(parseInt(row.getAttribute('data-id')));
        });
        saveNewOrder(orderedIds);
      }
    });
  });
}

async function saveNewOrder(order) {
  const toast = document.getElementById('toast');
  toast.innerText = '⏳ Saving order...';
  toast.classList.add('show');
  
  try {
    const res = await fetch('api/update_order.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ order })
    });
    const json = await res.json();
    if (json.success) {
      toast.innerText = '✅ Order updated';
      // We don't want the poll to overwrite the UI immediately if the user is still dragging,
      // but syncVingoMenu will eventually update it.
    } else {
      toast.innerText = '❌ Failed to save';
    }
  } catch (e) {
    toast.innerText = '❌ Network error';
  }
  
  setTimeout(() => toast.classList.remove('show'), 2000);
}

/* ── Toast ── */
function showToast() {
  const t = document.getElementById('toast');
  if (!t) return;
  t.classList.add('show');
  clearTimeout(t._tid);
  t._tid = setTimeout(() => t.classList.remove('show'), 2200);
}

/* ── Fetch & poll ── */


// Modal Control
const overlay = document.getElementById('modalOverlay');
const openBtn = document.getElementById('openFilter');
const closeBtn = document.getElementById('closeFilter');

const toggleModal = (show) => overlay.classList.toggle('show', show);
openBtn.onclick = () => toggleModal(true);
closeBtn.onclick = () => toggleModal(false);
overlay.onclick = (e) => { if(e.target === overlay) toggleModal(false); };
if(document.getElementById('refSearchIcon')) {
    document.getElementById('refSearchIcon').onclick = applyAdvancedFilters;
}

/* ── Event Listeners ── */
document.getElementById('btn-apply').onclick = () => {
    applyAdvancedFilters();
    toggleModal(false);
};

document.getElementById('btn-reset').onclick = () => {
    document.getElementById('f-search').value = '';
    document.getElementById('refSearch').value = '';
    document.getElementById('f-category').value = 'all';
    document.getElementById('f-diet').value = 'all';
    document.getElementById('f-min').value = '';
    document.getElementById('f-max').value = '';
    applyAdvancedFilters();
    toggleModal(false);
};

// Also apply on enter key for all inputs
['f-min','f-max','refSearch'].forEach(id => {
  const el = document.getElementById(id);
  if (!el) return;
  el.onkeyup = e => { 
    if (id === 'refSearch') syncSearch(el.value);
    if (e.key === 'Enter') { 
      applyAdvancedFilters(); 
      toggleModal(false); 
    } 
  };
});

// Meal Tab Clicks
document.querySelectorAll('.meal-tab').forEach(tab => {
  tab.onclick = function() {
    document.querySelectorAll('.meal-tab').forEach(t => t.classList.remove('active'));
    this.classList.add('active');
    activeMeal = this.getAttribute('data-meal');
    applyAdvancedFilters();
  };
});

syncVingoMenu();
setInterval(syncVingoMenu, POLL_MS);
</script>
</body>
</html>

