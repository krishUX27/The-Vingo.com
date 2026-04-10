<?php
// admin/partials/sidebar.php
$cur = basename($_SERVER['PHP_SELF']);
function nav_a(string $href, string $icon, string $label, string $cur): string {
    global $prefix;
    $full_href = $prefix . $href;
    $active = ($cur === $href) ? 'active' : '';
    return "<a href=\"{$full_href}\" class=\"{$active}\"><span class=\"nav-icon\">{$icon}</span> {$label}</a>";
}
$prefix = $prefix ?? '';
?>
<aside class="sidebar" id="sidebar">
  <div class="sidebar-close" id="sidebarClose">✕</div>
  <div class="sidebar-header"><span>🍴</span> Vingo Menu</div>
  <nav>
    <?= nav_a('dashboard.php',   '🍳', 'Kitchen menu',   $cur) ?>
    <?= nav_a('add-category.php',    '📂', 'Categories',  $cur) ?>
    <?= nav_a('offer-zone.php', '🎁', 'Offer Zone', $cur) ?>
    <a href="<?= $prefix ?>../menu.php?id=<?= $_SESSION['admin_id'] ?? 0 ?>" target="_blank">
      <span class="nav-icon">🌐</span> Live Menu ↗
    </a>
    <a href="<?= $prefix ?>print-menu.php" target="_blank">
      <span class="nav-icon">🖨️</span> Print Menu ↗
    </a>
    <?= nav_a('qr.php', '📱', 'QR Code', $cur) ?>
    <?= nav_a('menu-import.php', '📥', 'Menu Import', $cur) ?>
    <a href="<?= $prefix ?>generate_pdf.php" target="_blank">
      <span class="nav-icon">📄</span> Download PDF
    </a>
    <?= nav_a('settings.php', '⚙️', 'Restaurant Settings', $cur) ?>
    <?= nav_a('trash.php', '🗑️', 'Trash Bin', $cur) ?>
  </nav>
  <div class="sidebar-footer">Vingo Menu v2</div>
</aside>

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const sidebar = document.getElementById('sidebar');
  const closeBtn = document.getElementById('sidebarClose');
  const overlay = document.getElementById('sidebarOverlay');
  const menuToggle = document.getElementById('menuToggle');

  function openSidebar() {
    sidebar.classList.add('open');
    if(overlay) overlay.classList.add('show');
  }

  function closeSidebar() {
    sidebar.classList.remove('open');
    if(overlay) overlay.classList.remove('show');
  }

  if(menuToggle) menuToggle.addEventListener('click', openSidebar);
  if(closeBtn) closeBtn.addEventListener('click', closeSidebar);
  if(overlay) overlay.addEventListener('click', closeSidebar);
});
</script>

