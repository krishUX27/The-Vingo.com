<?php
// admin/partials/sidebar.php
$cur = basename($_SERVER['PHP_SELF']);
function nav_a(string $href, string $icon, string $label, string $cur): string {
    $active = basename($href) === $cur ? 'active' : '';
    return "<a href=\"{$href}\" class=\"{$active}\"><span class=\"nav-icon\">{$icon}</span> {$label}</a>";
}
?>
<aside class="sidebar">
  <div class="sidebar-brand"><span>🍴</span> Menu Manager</div>
  <nav>
    <?= nav_a('dashboard.php',   '📊', 'Dashboard',   $cur) ?>
    <?= nav_a('add-item.php',     '➕', 'Add Dish',    $cur) ?>
    <?= nav_a('add-category.php',    '📂', 'Categories',  $cur) ?>
    <a href="../menu.php" target="_blank">
      <span class="nav-icon">🌐</span> Live Menu ↗
    </a>
    <a href="../print-menu.php" target="_blank">
      <span class="nav-icon">🖨️</span> Print Menu ↗
    </a>
    <?= nav_a('../qr.php',            '📱', 'QR Code',    $cur) ?>
    <a href="../generate_pdf.php" target="_blank">
      <span class="nav-icon">📄</span> Download PDF
    </a>
  </nav>
  <div class="sidebar-footer">Menu Manager v2</div>
</aside>

