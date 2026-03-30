<?php
// menu.php — QR Digital Menu (Live)
// UI: Dark hero → white card with name → sticky filter pills → dish list with + button
require_once __DIR__ . '/includes/db.php';

$restaurant_name = menu_get_setting('restaurant_name', 'My Restaurant');
$restaurant_sub  = menu_get_setting('restaurant_sub',  'Welcome to our digital menu');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="<?= htmlspecialchars($restaurant_name) ?> — Live Menu">
  <title><?= htmlspecialchars($restaurant_name) ?> — Menu</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="icon" type="image/png" href="assets/images/favicon.png">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
      --bg:      #f5f5f5;
      --surface: #ffffff;
      --border:  #e8e8e8;
      --text:    #1a1a1a;
      --muted:   #888888;
    }

    body {
      font-family: 'Inter', sans-serif;
      background: var(--bg);
      color: var(--text);
      min-height: 100vh;
    }

    /* ── Hero (dark gradient, no image needed) ── */
    .hero {
      background: linear-gradient(180deg, #111 0%, #333 100%);
      min-height: 200px;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 40px 20px 80px;
    }

    /* ── White restaurant card (overlapping hero) ── */
    .restaurant-card {
      background: var(--surface);
      border-radius: 18px;
      padding: 28px 36px;
      text-align: center;
      box-shadow: 0 8px 32px rgba(0,0,0,.15);
      max-width: 420px;
      width: 90%;
      margin: -60px auto 0;
      position: relative;
      z-index: 10;
    }
    .restaurant-name {
      font-size: 1.6rem;
      font-weight: 700;
      color: var(--text);
    }
    .restaurant-sub {
      font-size: .88rem;
      color: var(--muted);
      margin-top: 6px;
    }

    /* ── Filter Pills (sticky) ── */
    .filter-row {
      position: sticky;
      top: 0;
      z-index: 50;
      background: var(--surface);
      border-bottom: 1px solid var(--border);
      padding: 14px 20px;
      display: flex;
      gap: 10px;
      overflow-x: auto;
      scrollbar-width: none;
      margin-top: 18px;
    }
    .filter-row::-webkit-scrollbar { display: none; }

    .pill {
      display: inline-flex;
      align-items: center;
      padding: 7px 20px;
      border-radius: 30px;
      font-size: .84rem;
      font-weight: 600;
      cursor: pointer;
      white-space: nowrap;
      background: transparent;
      color: var(--muted);
      border: none;
      outline: none;
      font-family: inherit;
      transition: all .15s;
    }
    .pill:hover { color: var(--text); }
    .pill.active {
      background: #111;
      color: #fff;
      border-radius: 30px;
    }

    /* ── Menu Body ── */
    .menu-body {
      max-width: 800px;
      margin: 0 auto;
      padding: 24px 16px 100px;
    }

    /* ── Category Heading ── */
    .cat-section { margin-bottom: 8px; }
    .cat-section[hidden] { display: none !important; }

    .cat-title {
      font-size: 1.05rem;
      font-weight: 800;
      color: var(--text);
      padding: 18px 16px 10px;
      letter-spacing: -.2px;
    }

    /* ── Dish Row ── */
    .dish-row {
      display: flex;
      align-items: center;
      gap: 16px;
      padding: 16px;
      background: var(--surface);
      border-bottom: 1px solid var(--border);
    }
    .dish-row:first-of-type { border-top: 1px solid var(--border); }
    .dish-row:hover { background: #fafafa; }

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
      font-size: .97rem;
      font-weight: 600;
      color: var(--text);
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
      font-size: 1rem;
      font-weight: 700;
      color: var(--text);
      flex-shrink: 0;
    }

    /* "+" add button */
    .add-btn {
      width: 34px; height: 34px;
      border-radius: 50%;
      background: #111;
      color: #fff;
      border: none;
      font-size: 1.25rem;
      line-height: 1;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      flex-shrink: 0;
      font-family: inherit;
      transition: background .15s, transform .15s;
    }
    .add-btn:hover  { background: #333; transform: scale(1.08); }
    .add-btn:active { transform: scale(.94); }
    .add-btn.na {
      background: #ddd;
      cursor: not-allowed;
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

    /* ── Filter Bar (Advanced) ── */
    .filter-card {
      background: var(--surface);
      border-radius: 12px;
      margin: 20px auto 0;
      max-width: 800px;
      padding: 20px;
      box-shadow: 0 4px 12px rgba(0,0,0,.05);
      border: 1px solid var(--border);
    }
    .filter-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
      gap: 15px;
      align-items: flex-end;
    }
    .filter-group { display: flex; flex-direction: column; gap: 6px; }
    .filter-group label {
      font-size: .75rem;
      font-weight: 700;
      color: var(--text);
      letter-spacing: .2px;
    }
    .filter-group input, .filter-group select {
      padding: 10px 12px;
      border: 1px solid var(--border);
      border-radius: 8px;
      font-family: inherit;
      font-size: .85rem;
      outline: none;
      transition: border-color .15s;
    }
    .filter-group input:focus, .filter-group select:focus {
      border-color: #6c63ff;
    }
    .filter-actions {
      display: flex;
      flex-direction: column;
      gap: 8px;
    }
    .btn-filter {
      background: #6c63ff;
      color: #fff;
      border: none;
      padding: 10px;
      border-radius: 8px;
      font-weight: 600;
      font-size: .85rem;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 6px;
    }
    .btn-reset {
      background: #fff;
      color: #6c63ff;
      border: 1px solid #6c63ff;
      padding: 10px;
      border-radius: 8px;
      font-weight: 600;
      font-size: .85rem;
      cursor: pointer;
      text-align: center;
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

<!-- Hero -->
<div class="hero"></div>

<!-- Restaurant Card -->
<div class="restaurant-card">
  <div class="restaurant-name"><?= htmlspecialchars($restaurant_name) ?></div>
  <div class="restaurant-sub"><?= htmlspecialchars($restaurant_sub) ?></div>
</div>

<!-- Advanced Filter Bar -->
<div class="filter-card">
  <div class="filter-grid">
    <div class="filter-group full-mobile">
      <label>Search</label>
      <input type="text" id="f-search" placeholder="Dish name...">
    </div>
    <div class="filter-group">
      <label>Category</label>
      <select id="f-category">
        <option value="all">All Categories</option>
      </select>
    </div>
    <div class="filter-group">
      <label>Availability</label>
      <select id="f-avail">
        <option value="all">All</option>
        <option value="Available">Available</option>
        <option value="Not Available">Not Available</option>
      </select>
    </div>
    <div class="filter-group">
      <label>Min ₹</label>
      <input type="number" id="f-min" placeholder="0" min="0">
    </div>
    <div class="filter-group">
      <label>Max ₹</label>
      <input type="number" id="f-max" placeholder="999" min="0">
    </div>
    <div class="filter-actions">
      <button id="btn-apply" class="btn-filter">🔍 Filter</button>
      <button id="btn-reset" class="btn-reset">Reset</button>
    </div>
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
const FETCH_URL   = 'api/fetch_dishes.php';
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
      ${btn}
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
        ${rows}
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

// Event Listeners
document.getElementById('btn-apply').onclick = applyAdvancedFilters;
document.getElementById('btn-reset').onclick = () => {
    document.getElementById('f-search').value = '';
    document.getElementById('f-category').value = 'all';
    document.getElementById('f-avail').value = 'all';
    document.getElementById('f-min').value = '';
    document.getElementById('f-max').value = '';
    applyAdvancedFilters();
};

// Also apply on enter key for inputs
['f-search','f-min','f-max'].forEach(id => {
  document.getElementById(id).onkeyup = e => { if (e.key === 'Enter') applyAdvancedFilters(); };
});

fetchMenu();
setInterval(fetchMenu, POLL_MS);
</script>
</body>
</html>

