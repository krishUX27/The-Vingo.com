<?php
// admin/partials/topbar_user.php
$sess_user = $_SESSION['admin_username'] ?? $_SESSION['super_username'] ?? 'User';
$avatar_char = strtoupper(substr($sess_user, 0, 1));

// Fetch real details from DB for the current user
$user_info = $conn->query("SELECT email, role FROM users WHERE username = '$sess_user' LIMIT 1")->fetch_assoc();
$user_email = $user_info['email'] ?? 'N/A';
$user_role  = ($user_info['role'] === 'superadmin') ? 'Super Admin' : 'Admin';
?>
<div class="user-profile" id="userProfile" style="cursor:pointer; display:flex; align-items:center; gap:12px; padding:6px 12px; border-radius:100px; transition:0.2s" onmouseover="this.style.background='rgba(0,0,0,0.03)'" onmouseout="this.style.background='transparent'">
  <div class="user-avatar" style="width:40px; height:40px; background:linear-gradient(135deg, #6366f1, #4f46e5); color:#fff; display:flex; align-items:center; justify-content:center; border-radius:50%; font-weight:800; font-size:1.1rem; box-shadow: 0 4px 12px rgba(99, 102, 241, 0.3)">
    <?= $avatar_char ?>
  </div>
  <div class="user-info" style="text-align:left">
    <div class="user-name" style="font-weight:700; color:#0f172a; line-height:1.2"><?= htmlspecialchars($sess_user) ?></div>
    <div class="user-role" style="font-size:0.75rem; color:#64748b; font-weight:600; text-transform:uppercase; letter-spacing:0.5px"><?= $user_role ?></div>
  </div>
  <div class="user-chevron" style="font-size: 0.6rem; margin-left:8px; opacity:0.5">▼</div>
  
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
