<?php
// admin/partials/topbar_user.php
$sess_user = $_SESSION['admin_username'] ?? 'Admin';
$avatar_char = strtoupper(substr($sess_user, 0, 1));
?>
<div class="user-profile" id="userProfile">
  <div class="user-avatar"><?= $avatar_char ?></div>
  <div class="user-info">
    <span class="user-name"><?= htmlspecialchars($sess_user) ?></span>
    <span class="user-role">Administrator</span>
  </div>
  <div class="user-chevron">▼</div>
  
  <div class="dropdown-menu" id="userDropdown">
    <div style="padding: 10px 16px">
      <div class="user-name"><?= htmlspecialchars($sess_user) ?></div>
      <div style="font-size: 0.72rem; color: var(--text-light)"><?= htmlspecialchars(menu_get_setting('admin_email', 'admin@vingo.com')) ?></div>
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

<script>
document.addEventListener('DOMContentLoaded', function() {
  const profile = document.getElementById('userProfile');
  const dropdown = document.getElementById('userDropdown');
  
  profile.addEventListener('click', function(e) {
    e.stopPropagation();
    dropdown.classList.toggle('open');
    profile.classList.toggle('active');
  });
  
  document.addEventListener('click', function() {
    dropdown.classList.remove('open');
    profile.classList.remove('active');
  });
});
</script>
