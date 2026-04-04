<?php
// superadmin/partials/super_sidebar.php
$cur = $cur ?? basename($_SERVER['PHP_SELF']);
function nav_super(string $href, string $icon, string $label, string $cur): string {
    $active = ($cur === $href) ? 'active' : '';
    return "<a href=\"{$href}\" class=\"{$active}\"><span class=\"nav-icon\">{$icon}</span> {$label}</a>";
}
?>
<aside class="sidebar" id="sidebar">
  <div class="sidebar-header"><span>👑</span> Vingo Master</div>
  <nav>
    <?= nav_super('index.php', '🛡️', 'Root Console', $cur) ?>
    <?= nav_super('manage-admins.php', '👥', 'Manage Admins', $cur) ?>
    <?= nav_super('trash.php', '🗑️', 'Trash Bin', $cur) ?>
    <div style="height:1px; background:rgba(255,255,255,0.05); margin:20px 0"></div>
    <a href="../admin/index.php"><span class="nav-icon">🚪</span> Exit to Admin</a>
    <a href="logout.php" onclick="return confirm('Sign out from Root Console?')">
      <span class="nav-icon">🔒</span> Lock System
    </a>
  </nav>
  <div class="sidebar-footer">Root Engine v1.0</div>
</aside>

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const sidebar = document.getElementById('sidebar');
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
  if(overlay) overlay.addEventListener('click', closeSidebar);
});
</script>
