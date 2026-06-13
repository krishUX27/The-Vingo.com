<?php
/**
 * admin/partials/header.php
 * 
 * Common header partial for all admin pages.
 * Implements the Figma design: TheVingo.com brand header with user profile.
 *
 * Required variables (set before including this file):
 *   $page_title  — string — Page title for the <title> tag (e.g. "Dashboard — Menu Manager")
 *
 * Optional variables:
 *   $page_styles  — string — Extra <style> block content (page-specific CSS)
 *   $page_head    — string — Extra <head> content (scripts, meta, etc.)
 */

// Ensure these are available
if (!isset($page_title)) $page_title = 'Vingo Admin';

// Session user info for the header profile
$_header_user = $_SESSION['admin_username'] ?? $_SESSION['super_username'] ?? 'User';
$_header_id   = $_SESSION['admin_id'] ?? $_SESSION['super_id'] ?? 0;
$_header_char = strtoupper(substr($_header_user, 0, 1));

// Fetch real details from DB for the current user
$_header_stmt = $conn->prepare("SELECT email, role FROM users WHERE id = ? LIMIT 1");
$_header_stmt->bind_param('i', $_header_id);
$_header_stmt->execute();
$_header_info = $_header_stmt->get_result()->fetch_assoc();
$_header_stmt->close();

$_header_email = $_header_info['email'] ?? 'N/A';
$_header_role  = (($_header_info['role'] ?? '') === 'superadmin') ? 'Super Admin' : 'Admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= htmlspecialchars($page_title) ?></title>
  <link rel="stylesheet" href="../assets/css/menu-style.css?v=<?= time() ?>">
  <link rel="stylesheet" href="../assets/css/header.css?v=<?= time() ?>">
  <link rel="icon" type="image/png" href="../assets/images/favicon.png">
  <?php if (!empty($page_head)): ?>
    <?= $page_head ?>
  <?php endif; ?>
  <?php if (!empty($page_styles)): ?>
    <style><?= $page_styles ?></style>
  <?php endif; ?>
</head>
<body>

<?php include __DIR__ . '/sidebar.php'; ?>

<div class="main">
  <div class="topbar">
    <div class="topbar-left">
      <div class="burger-icon" id="menuToggle">
        <div class="burger-icon-bars">
          <span></span>
          <span></span>
          <span></span>
        </div>
      </div>
      <span class="topbar-brand">TheVingo.com</span>
    </div>

    <div class="topbar-right">
      <div class="header-user" id="headerUserProfile">
        <div class="header-user-info">
          <div class="header-user-name"><?= htmlspecialchars($_header_user) ?></div>
          <div class="header-user-role"><?= $_header_role ?></div>
        </div>
        <div class="header-user-avatar">
          <?= $_header_char ?>
        </div>

        <!-- User Dropdown -->
        <div class="dropdown-menu" id="headerUserDropdown">
          <div class="dropdown-header">
            <div class="dropdown-header-name"><?= htmlspecialchars($_header_user) ?></div>
            <div class="dropdown-header-email"><?= htmlspecialchars($_header_email) ?></div>
          </div>
          <div class="dropdown-divider"></div>
          <a href="profile.php" class="dropdown-item">👤 My Profile</a>
          <a href="settings.php" class="dropdown-item">⚙️ Settings</a>
          <div class="dropdown-divider"></div>
          <a href="logout.php" class="dropdown-item danger" onclick="return confirm('Sign out from Vingo Menu?')">
            🚪 Sign Out
          </a>
        </div>
      </div>
    </div>
  </div>

  <div class="content">

<script>
// Header User Dropdown Toggle
document.addEventListener('DOMContentLoaded', function() {
  const profile = document.getElementById('headerUserProfile');
  const dropdown = document.getElementById('headerUserDropdown');
  
  if (!profile || !dropdown) return;

  window.closeUserDropdown = function() {
    dropdown.classList.remove('open');
  };

  window.toggleUserDropdown = function() {
    const isOpen = dropdown.classList.contains('open');
    if (!isOpen) {
      if (window.closeSidebar) window.closeSidebar();
      dropdown.classList.add('open');
    } else {
      closeUserDropdown();
    }
  };

  profile.addEventListener('click', function(e) {
    e.stopPropagation();
    toggleUserDropdown();
  });

  document.addEventListener('click', function() {
    closeUserDropdown();
  });
});
</script>
