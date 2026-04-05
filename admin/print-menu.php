<?php
require_once __DIR__ . '/partials/auth_check.php';
require_once __DIR__ . '/../includes/db.php';

$admin_id = $_SESSION['admin_id'] ?? 0;
$restaurant_name = menu_get_setting('restaurant_name', 'My Restaurant', $admin_id);
$tagline         = menu_get_setting('restaurant_sub',  'Delicious meals, crafted with love', $admin_id);

$result = $conn->query(
    "SELECT d.name, d.price, d.availability, c.name AS category
     FROM dishes d
     JOIN categories c ON c.id = d.category_id
     WHERE d.availability = 'Available' AND d.user_id = $admin_id AND d.is_deleted = 0
     ORDER BY c.name, d.name"
);

$grouped = [];
while ($row = $result->fetch_assoc()) {
    $grouped[$row['category']][] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($restaurant_name) ?> — Print Menu</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Playfair+Display:wght@700;800&display=swap" rel="stylesheet">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
      --orange: #e8460a;
      --text:   #111111;
      --muted:  #555555;
      --border: #e0e0e0;
    }

    body {
      font-family: 'Inter', sans-serif;
      background: #f0f0f0;
      color: var(--text);
    }

    /* ══ Toolbar (screen only) ══ */
    .toolbar {
      background: #1a1d2e;
      color: #fff;
      padding: 11px 20px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      position: sticky;
      top: 0;
      z-index: 999;
      gap: 12px;
    }
    .toolbar span { font-size: .88rem; font-weight: 500; }
    .t-btns { display: flex; gap: 10px; flex-shrink: 0; }
    .t-btn {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 8px 18px;
      border: none;
      border-radius: 8px;
      font-size: .84rem;
      font-weight: 600;
      cursor: pointer;
      font-family: inherit;
      text-decoration: none;
      transition: opacity .15s;
    }
    .t-btn:hover { opacity: .85; }
    .t-btn-print { background: #e8460a; color: #fff; }
    .t-btn-back  { background: rgba(255,255,255,.12); color: #fff; }

    /* ══ Paper ══ */
    .paper {
      max-width: 760px;
      margin: 28px auto;
      background: #fff;
      box-shadow: 0 4px 20px rgba(0,0,0,.1);
      padding: 50px 54px 60px;
    }

    /* ── Restaurant Header ── */
    .r-header {
      text-align: center;
      border-bottom: 2px solid var(--text);
      padding-bottom: 18px;
      margin-bottom: 32px;
    }
    .r-name {
      font-family: 'Playfair Display', serif;
      font-size: 2.4rem;
      font-weight: 800;
      color: var(--orange);
      line-height: 1;
    }
    .r-tag {
      font-size: .85rem;
      color: var(--muted);
      margin-top: 7px;
      font-style: italic;
    }

    /* ── Category block ── */
    .cat-block { margin-bottom: 28px; }

    /* Bold orange category heading — Johns Kitchen style */
    .cat-heading {
      font-family: 'Playfair Display', serif;
      font-size: 1.45rem;
      font-weight: 700;
      color: var(--orange);
      margin-bottom: 10px;
    }

    /* ── 2-column row grid ── */
    .items-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      border-top: 1px solid #ccc;
    }

    .item {
      display: flex;
      align-items: baseline;
      justify-content: space-between;
      gap: 8px;
      padding: 7px 10px 7px 0;
      border-bottom: 1px solid var(--border);
    }
    /* Right column items */
    .item:nth-child(even) {
      padding-left: 22px;
      border-left: 1px solid var(--border);
    }

    .i-name  { font-size: .9rem; font-weight: 400; flex: 1; }
    .i-price { font-size: .9rem; font-weight: 700; white-space: nowrap; flex-shrink: 0; }

    /* ── Footer ── */
    .r-footer {
      text-align: center;
      margin-top: 36px;
      padding-top: 16px;
      border-top: 1px solid var(--border);
      font-size: .75rem;
      color: var(--muted);
    }

    /* ── Empty ── */
    .empty-state {
      text-align: center;
      padding: 60px 20px;
      color: var(--muted);
      font-size: .95rem;
    }

    /* ════ PRINT ════ */
    @media print {
      body       { background: #fff; }
      .toolbar   { display: none !important; }
      .paper {
        max-width: 100%;
        margin: 0;
        box-shadow: none;
        padding: 16mm 14mm 20mm;
      }
      .r-name    { font-size: 2rem; }
      .cat-heading { font-size: 1.25rem; }
      .i-name, .i-price { font-size: .85rem; }
      .cat-block { page-break-inside: avoid; }
    }

    @media (max-width: 600px) {
      .paper { margin: 0; box-shadow: none; padding: 28px 18px 40px; }
      .items-grid { grid-template-columns: 1fr; }
      .item:nth-child(even) { padding-left: 0; border-left: none; }
      .r-name { font-size: 1.9rem; }
    }
  </style>
</head>
<body>

<!-- Toolbar (hidden on print) -->
<div class="toolbar">
  <span>🖨️ Print Menu — <?= htmlspecialchars($restaurant_name) ?></span>
  <div class="t-btns">
    <a href="../menu.php" class="t-btn t-btn-back">← Live Menu</a>
    <button class="t-btn t-btn-print" onclick="window.print()">🖨️ Print / Save PDF</button>
  </div>
</div>

<!-- Paper -->
<div class="paper">

  <!-- Header -->
  <div class="r-header">
    <div class="r-name"><?= htmlspecialchars($restaurant_name) ?></div>
    <div class="r-tag"><?= htmlspecialchars($tagline) ?></div>
  </div>

  <?php if (empty($grouped)): ?>
    <div class="empty-state">🍽️ No available dishes to print yet.</div>
  <?php else: ?>

  <?php foreach ($grouped as $cat => $items): ?>
  <div class="cat-block">

    <!-- Bold orange category heading (Johns Kitchen style) -->
    <div class="cat-heading"><?= htmlspecialchars($cat) ?></div>

    <!-- 2-column name + price grid -->
    <div class="items-grid">
      <?php foreach ($items as $d): ?>
      <div class="item">
        <span class="i-name"><?= htmlspecialchars($d['name']) ?></span>
        <span class="i-price">₹<?= number_format($d['price'], 0) ?></span>
      </div>
      <?php endforeach; ?>

      <?php if (count($items) % 2 !== 0): ?>
      <!-- Blank cell to balance odd row -->
      <div class="item" style="border-left:1px solid var(--border);padding-left:22px"></div>
      <?php endif; ?>
    </div>

  </div>
  <?php endforeach; ?>
  <?php endif; ?>

  <div class="r-footer">
    <?= htmlspecialchars($restaurant_name) ?> &nbsp;•&nbsp; Printed on <?= date('d M Y') ?>
  </div>

</div>

<script>
const ADMIN_ID = <?= $admin_id ?>;
const FETCH_URL = '../api/fetch_dishes.php?user_id=' + ADMIN_ID;
const POLL_MS = 3000;
let lastHash = '<?= md5(serialize($grouped)) ?>';

async function checkSync() {
  try {
    const r = await fetch(FETCH_URL + '&_=' + Date.now());
    const json = await r.json();
    if (!json.success) return;
    
    const hash = b64EncodeUnicode(JSON.stringify(json.data)); 
    // We use a simple hash comparison to detect changes
    if (lastHash !== '' && lastHash !== hash) {
       window.location.reload();
    }
    lastHash = hash;
  } catch(e) {}
}

function b64EncodeUnicode(str) {
    return btoa(encodeURIComponent(str).replace(/%([0-9A-F]{2})/g, function(match, p1) {
        return String.fromCharCode('0x' + p1);
    }));
}

if (ADMIN_ID > 0) {
  setInterval(checkSync, POLL_MS);
}
</script>
</body>
</html>

