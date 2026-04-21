<?php
// admin/partials/topbar_user.php
$sess_user = $_SESSION['admin_username'] ?? $_SESSION['super_username'] ?? 'User';
$avatar_char = strtoupper(substr($sess_user, 0, 1));

// Fetch real details from DB for the current user
$user_info = $conn->query("SELECT email, role FROM users WHERE username = '$sess_user' LIMIT 1")->fetch_assoc();
$user_email = $user_info['email'] ?? 'N/A';
$user_role  = ($user_info['role'] === 'superadmin') ? 'Super Admin' : 'Admin';
?>
<div class="user-profile" id="userProfile">
  <div class="user-avatar">
    <?= $avatar_char ?>
  </div>
  <div class="user-info">
    <div class="user-name"><?= htmlspecialchars($sess_user) ?></div>
    <div class="user-role"><?= $user_role ?></div>
  </div>
  <div class="user-chevron">▼</div>
  
  <div class="dropdown-menu" id="userDropdown">
    <div style="padding: 10px 16px; border-bottom:1px solid #eee">
      <div class="user-name" style="font-weight:700"><?= htmlspecialchars($sess_user) ?></div>
      <div style="font-size: 0.72rem; color: #64748b"><?= htmlspecialchars($user_email) ?></div>
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
  
  window.closeUserDropdown = function() {
    if(!dropdown) return;
    dropdown.classList.remove('open');
    profile.classList.remove('active');
  };

  window.toggleUserDropdown = function() {
    const isOpen = dropdown.classList.contains('open');
    if(!isOpen) {
      if(window.closeSidebar) window.closeSidebar();
      dropdown.classList.add('open');
      profile.classList.add('active');
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
