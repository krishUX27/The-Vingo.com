<?php
// admin/profile.php
require_once __DIR__ . '/partials/auth_check.php';
require_once __DIR__ . '/../includes/db.php';

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
$admin_sess_id = $_SESSION['admin_id'] ?? 0;
$sess_username = $_SESSION['admin_username'] ?? 'admin';

// Fetch current user details from DB
$u_stmt = $conn->prepare("SELECT email FROM users WHERE id = ?");
$u_stmt->bind_param('i', $admin_sess_id);
$u_stmt->execute();
$u_res = $u_stmt->get_result();
$user_data = $u_res->fetch_assoc();
$admin_email = $user_data['email'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $email = trim($_POST['email'] ?? '');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'Please enter a valid email address.'];
        } else {
            $stmt = $conn->prepare("UPDATE users SET email = ? WHERE id = ?");
            $stmt->bind_param('si', $email, $admin_sess_id);
            if ($stmt->execute()) {
                $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Profile updated successfully!'];
                header('Location: profile.php'); exit;
            } else {
                $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'Failed to update email.'];
            }
        }
    } elseif ($action === 'update_password') {
        // Security Check: Must have verified OTP in this session
        if (!isset($_SESSION['otp_verified']) || $_SESSION['otp_verified'] !== true || $_SESSION['reset_email'] !== $admin_email) {
            $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'Unauthorized password reset. Please verify OTP first.'];
            header('Location: profile.php'); exit;
        }

        $new_pass = $_POST['new_password'] ?? '';
        $conf_pass = $_POST['confirm_password'] ?? '';

        if (strlen($new_pass) < 6) {
            $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'Password must be at least 6 characters.'];
        } elseif ($new_pass !== $conf_pass) {
            $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'Passwords do not match.'];
        } else {
            $hashed = password_hash($new_pass, PASSWORD_BCRYPT);
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->bind_param('si', $hashed, $admin_sess_id);
            if ($stmt->execute()) {
                // Clear reset session
                unset($_SESSION['otp_verified']);
                unset($_SESSION['reset_email']);
                
                $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Password updated successfully!'];
                header('Location: profile.php'); exit;
            } else {
                $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'Failed to update password.'];
            }
        }
    }
}

$page_title = 'My Profile — Vingo Menu';
$cur = 'profile.php';
?>

<?php include __DIR__ . '/partials/header.php'; ?>

    <?php if ($flash): ?>
      <div class="flash flash-<?= $flash['type'] ?>" style="margin-bottom:20px"><?= $flash['msg'] ?></div>
    <?php endif; ?>

    <div style="display:grid; grid-template-columns:1fr 1fr; gap:24px">
      <div class="card">
        <div class="card-title">Profile Identity</div>
        <form method="POST">
          <input type="hidden" name="action" value="update_profile">
          <div class="form-group">
            <label>Username</label>
            <input type="text" value="<?= htmlspecialchars($sess_username) ?>" disabled style="background:#f1f5f9; cursor:not-allowed">
            <p style="font-size:0.75rem; color:var(--text-light); margin-top:4px">Username cannot be changed in this version.</p>
          </div>
          
          <div class="form-group" style="margin-top:20px">
            <label>Support Email</label>
            <input type="email" name="email" value="<?= htmlspecialchars($admin_email) ?>" required>
          </div>

          <div style="margin-top:24px">
            <button type="submit" class="btn btn-primary">Update Profile</button>
          </div>
        </form>
      </div>

      <div class="card" style="border-color:rgba(239, 68, 68, 0.1)">
        <div class="card-title" style="color:var(--danger)">Security & Access</div>
        
        <!-- Alert for AJAX -->
        <div id="security-alert" class="flash" style="display:none; margin-bottom:15px; font-size:0.85rem; padding:10px"></div>

        <!-- Step 1: Send OTP -->
        <div id="sec-step-1">
          <p style="font-size:0.85rem; color:var(--text-light); margin-bottom:20px">
            To update your password, we need to verify your identity. An OTP will be sent to <strong><?= htmlspecialchars($admin_email) ?></strong>.
          </p>
          <button type="button" id="btn-sec-send" class="btn btn-outline" style="width:100%; justify-content:center">📩 Send Verification OTP</button>
        </div>

        <!-- Step 2: Verify OTP -->
        <div id="sec-step-2" style="display:none">
          <p style="font-size:0.85rem; color:var(--text-light); text-align:center; margin-bottom:10px">Enter the 6-digit code sent to your email</p>
          <div class="otp-container" style="display:flex; gap:8px; justify-content:center; margin-bottom:20px">
            <input type="text" class="otp-box" maxlength="1" pattern="\d*">
            <input type="text" class="otp-box" maxlength="1" pattern="\d*">
            <input type="text" class="otp-box" maxlength="1" pattern="\d*">
            <input type="text" class="otp-box" maxlength="1" pattern="\d*">
            <input type="text" class="otp-box" maxlength="1" pattern="\d*">
            <input type="text" class="otp-box" maxlength="1" pattern="\d*">
          </div>
          <button type="button" id="btn-sec-verify" class="btn btn-primary" style="width:100%; justify-content:center">✅ Verify OTP</button>
          <p style="text-align:center; margin-top:15px">
            <button type="button" onclick="resetSecSteps()" style="background:none; border:none; color:var(--text-light); font-size:0.75rem; cursor:pointer">Back</button>
          </p>
        </div>

        <!-- Step 3: New Password Form -->
        <div id="sec-step-3" style="display:none">
          <p style="font-size:0.85rem; color:var(--text-light); margin-bottom:20px">OTP Verified! You can now set your new password.</p>
          <form method="POST">
            <input type="hidden" name="action" value="update_password">
            <div class="form-group">
              <label>New Password</label>
              <input type="password" name="new_password" placeholder="••••••••" required minlength="6">
            </div>
            <div class="form-group" style="margin-top:20px">
              <label>Confirm Password</label>
              <input type="password" name="confirm_password" placeholder="••••••••" required minlength="6">
            </div>
            <div style="margin-top:24px">
              <button type="submit" class="btn btn-primary" style="width:100%; justify-content:center">🔒 Update Password</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>

<style>
.otp-box {
  width: 40px; height: 50px; text-align: center; font-size: 1.25rem; font-weight: 700;
  border: 1px solid #cbd5e1; border-radius: 8px; background: #f8fafc; outline: none;
}
.otp-box:focus { border-color: var(--accent); background: #fff; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1); }
</style>

<script>
const secAlert = document.getElementById('security-alert');
const adminEmail = '<?= $admin_email ?>';

function showSecAlert(msg, type = 'danger') {
  secAlert.textContent = (type === 'danger' ? '❌ ' : '✅ ') + msg;
  secAlert.className = `flash flash-${type}`;
  secAlert.style.display = 'block';
  setTimeout(() => { secAlert.style.display = 'none'; }, 5000);
}

function resetSecSteps() {
  document.getElementById('sec-step-1').style.display = 'block';
  document.getElementById('sec-step-2').style.display = 'none';
  document.getElementById('sec-step-3').style.display = 'none';
}

// ── STEP 1: SEND OTP ──
document.getElementById('btn-sec-send').addEventListener('click', async function() {
  this.disabled = true;
  this.textContent = '⌛ Sending...';
  
  try {
    const res = await fetch('../api/send_otp.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ email: adminEmail })
    });
    const json = await res.json();
    if (json.success) {
      document.getElementById('sec-step-1').style.display = 'none';
      document.getElementById('sec-step-2').style.display = 'block';
      showSecAlert(json.message, 'success');
    } else {
      showSecAlert(json.error);
    }
  } catch (err) {
    showSecAlert('Network error.');
  } finally {
    this.disabled = false;
    this.textContent = '📩 Send Verification OTP';
  }
});

// ── STEP 2: VERIFY OTP ──
const otpBoxes = document.querySelectorAll('.otp-box');
otpBoxes.forEach((box, i) => {
  box.addEventListener('input', (e) => {
    if (e.target.value && i < otpBoxes.length - 1) otpBoxes[i + 1].focus();
  });
  box.addEventListener('keydown', (e) => {
    if (e.key === 'Backspace' && !e.target.value && i > 0) otpBoxes[i - 1].focus();
  });
});

document.getElementById('btn-sec-verify').addEventListener('click', async function() {
  const otp = Array.from(otpBoxes).map(b => b.value).join('');
  if (otp.length < 6) return showSecAlert('Enter 6-digit OTP');
  
  this.disabled = true;
  this.textContent = '⌛ Verifying...';
  
  try {
    const res = await fetch('../api/verify_otp.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ email: adminEmail, otp: otp })
    });
    const json = await res.json();
    if (json.success) {
      document.getElementById('sec-step-2').style.display = 'none';
      document.getElementById('sec-step-3').style.display = 'block';
      showSecAlert(json.message, 'success');
    } else {
      showSecAlert(json.error);
    }
  } catch (err) {
    showSecAlert('Network error.');
  } finally {
    this.disabled = false;
    this.textContent = '✅ Verify OTP';
  }
});
</script>

</body>
</html>
