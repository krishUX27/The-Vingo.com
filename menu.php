<?php
require_once __DIR__ . '/includes/db.php';
$user_id = intval($_GET['id'] ?? 0);

// QR Scan Tracking Logic
if ($user_id > 0) {
    $conn->query("INSERT INTO qr_scans (user_id, scan_count) VALUES ($user_id, 1) ON DUPLICATE KEY UPDATE scan_count = scan_count + 1");
}

$restaurant_name = menu_get_setting('restaurant_name', 'Vingo Menu', $user_id);
$restaurant_sub  = menu_get_setting('restaurant_sub',  'Premium Digital Selection', $user_id);

// Fetch active offers for this user
$offers = [];
if ($user_id) {
    $off_res = $conn->query("SELECT * FROM seasonal_offers WHERE user_id = $user_id ORDER BY created_at DESC");
    if ($off_res) $offers = $off_res->fetch_all(MYSQLI_ASSOC);
}
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
      padding: 30px 20px 50px;
      text-align: center;
      color: #fff;
      /* Full width, no border radius */
    }
    .restaurant-name {
      font-size: 2.4rem;
      font-weight: 800;
      margin-bottom: 20px;
      letter-spacing: -0.5px;
    }
    .search-filter-row {
      display: flex;
      gap: 12px;
      align-items: center;
      max-width: 500px;
      margin: 0 auto 30px;
    }
    .search-input-wrap {
      flex: 1;
      position: relative;
    }
    .search-input-wrap input {
      width: 100%;
      padding: 12px 20px;
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
      margin-left: 90px; /* Shift everything past the red circle */
      text-align: left; /* Ensure text is left aligned */
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
      gap: 16px;
      padding: 0 16px;
    }
    
    @media(max-width: 768px) {
      .dish-list { grid-template-columns: 1fr 1fr; gap: 12px; }
      .menu-body { padding: 40px 15px 100px; }
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
  </style>
</head>
<body>

<!-- Redesigned Header per Reference -->
<div class="header-section">
  <div class="restaurant-name"><?= $restaurant_name ?></div>
  
  <!-- Search & Filter Row -->
  <div class="search-filter-row">
    <div class="search-input-wrap">
      <input type="text" id="refSearch" placeholder="Search for dishes..." onkeyup="syncSearch(this.value)">
    </div>
    <button class="ref-filter-btn" id="openFilter">
      Filter <span style="font-size: 0.7rem">▼</span>
    </button>
  </div>

  <!-- Offer Carousel per Reference -->
  <?php if (!empty($offers)): ?>
  <div class="offers-container">
    <div class="offer-carousel-ref" id="refCarousel">
      <?php foreach ($offers as $off): ?>
        <div class="offer-card-ref">
          <div class="accent-shape"></div>
          <div class="offer-content-ref">
            <div class="offer-discount-text"><?= htmlspecialchars($off['discount']) ?></div>
            <div class="offer-title-text"><?= htmlspecialchars($off['title']) ?></div>
            <div class="offer-sub-text"><?= htmlspecialchars($off['description'] ?: 'Summer Offer') ?></div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
    
    <div class="dots-container" id="refDots">
      <?php foreach ($offers as $i => $off): ?>
        <div class="ref-dot <?= $i===0?'active':'' ?>" data-idx="<?= $i ?>"></div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>
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

    <div class="filter-grid-2">
      <div class="filter-group">
        <label>Status</label>
        <select id="f-avail">
          <option value="all">All</option>
          <option value="Available">Available</option>
        </select>
      </div>
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

<script>
const URL_PARAMS  = new URLSearchParams(window.location.search);
const MENU_ID     = URL_PARAMS.get('id') || 0;
const FETCH_URL   = 'api/fetch_dishes.php?user_id=' + MENU_ID;
const POLL_MS     = 3000;

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
let fullData     = null; // All dishes from API
let activeFilter = 'all';

/* ── HTML escape ── */
function esc(s) {
  return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;')
    .replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

/* ── Build dish row ── */
function dishRow(d, dotClass) {
  const symbol = d.currency === 'USD' ? '$' : '₹';
  const avail = d.availability === 'Available';
  const imgUrl = d.image ? `uploads/${d.image}` : '';
  
  const imgHtml = imgUrl 
    ? `<img src="${esc(imgUrl)}" alt="${esc(d.name)}">`
    : `<span class="dish-picon">🍽️</span>`;

  const btn = avail
    ? `<button class="add-btn" title="Add ${esc(d.name)}">+</button>`
    : `<button class="add-btn na" disabled title="Not available">+</button>`;

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
    <div class="dish-row">
      <div class="dish-img-wrap">
        ${imgHtml}
      </div>
      <div class="dish-info">
        <div class="dish-name">${esc(d.name)}</div>
        <div class="dish-badge">
          <span class="bdot ${dotClass}"></span>${esc(d.category)}
        </div>
        ${d.offer_title ? `<div style="font-size:0.7rem; color:#16a34a; font-weight:700; margin-top:2px">🎁 ${esc(d.offer_title)}</div>` : ''}
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
  const q     = document.getElementById('f-search').value.toLowerCase().trim();
  const cat   = document.getElementById('f-category').value;
  const avail = document.getElementById('f-avail').value;
  const min   = parseFloat(document.getElementById('f-min').value) || 0;
  const max   = parseFloat(document.getElementById('f-max').value) || 999999;

  const filtered = {};
  
  // fullData is grouped by category: { "Main": [...], "Drinks": [...] }
  Object.keys(fullData).forEach(categoryName => {
    // 1. Category check
    if (cat !== 'all' && categoryName !== cat) return;

    const matches = fullData[categoryName].filter(d => {
      // 2. Search
      if (q !== '' && !d.name.toLowerCase().includes(q)) return false;
      // 3. Availability
      if (avail !== 'all' && d.availability !== avail) return false;
      // 4. Price
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
async function fetchMenu() {
  try {
    const r    = await fetch(FETCH_URL + '?_=' + Date.now());
    const json = await r.json();
    if (!json.success) return;

    const hash = JSON.stringify(json.data);
    if (hash === lastHash) return;

    const first = lastHash === '';
    document.getElementById('skeleton-wrap')?.remove();
    
    fullData = json.data;
    renderMenu(fullData, first);
    
    if (!first) {
      showToast();
      applyAdvancedFilters(); // re-apply current filters to new data
    }
    lastHash = hash;
  } catch(e) {
    console.warn('[Menu]', e);
  }
}

// Modal Control
const overlay = document.getElementById('modalOverlay');
const openBtn = document.getElementById('openFilter');
const closeBtn = document.getElementById('closeFilter');

const toggleModal = (show) => overlay.classList.toggle('show', show);
openBtn.onclick = () => toggleModal(true);
closeBtn.onclick = () => toggleModal(false);
overlay.onclick = (e) => { if(e.target === overlay) toggleModal(false); };

/* ── Event Listeners ── */
document.getElementById('btn-apply').onclick = () => {
    applyAdvancedFilters();
    toggleModal(false);
};

document.getElementById('btn-reset').onclick = () => {
    document.getElementById('f-search').value = '';
    document.getElementById('refSearch').value = '';
    document.getElementById('f-category').value = 'all';
    document.getElementById('f-avail').value = 'all';
    document.getElementById('f-min').value = '';
    document.getElementById('f-max').value = '';
    applyAdvancedFilters();
    toggleModal(false);
};

// Also apply on enter key for numeric inputs
['f-min','f-max'].forEach(id => {
  document.getElementById(id).onkeyup = e => { if (e.key === 'Enter') { applyAdvancedFilters(); toggleModal(false); } };
});

// Carousel Logic for Reference Redesign
document.addEventListener('DOMContentLoaded', () => {
    const carousel = document.getElementById('refCarousel');
    const dots     = document.querySelectorAll('#refDots .ref-dot');
    if (!carousel || dots.length === 0) return;

    const getSlideWidth = () => {
       const slide = carousel.querySelector('.offer-card-ref');
       return slide ? slide.offsetWidth : 0;
    };

    carousel.addEventListener('scroll', () => {
        const sw = getSlideWidth();
        const index = Math.round(carousel.scrollLeft / sw);
        dots.forEach((d, i) => d.classList.toggle('active', i === index));
    });

    dots.forEach(dot => {
        dot.onclick = () => {
            const idx = parseInt(dot.getAttribute('data-idx'));
            carousel.scrollTo({ left: idx * getSlideWidth(), behavior: 'smooth' });
        };
    });
});

fetchMenu();
setInterval(fetchMenu, POLL_MS);
</script>
</body>
</html>

