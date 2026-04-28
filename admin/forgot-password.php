<?php
session_start();
// If already logged in, go to dashboard
if (isset($_SESSION['admin_logged_in'])) {
    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Reset Password — Vingo Menu Manager</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="stylesheet" href="../assets/css/menu-style.css?v=<?= time() ?>">
  <link rel="icon" type="image/png" href="../assets/images/favicon.png">
  <style>
    .step-box { display: none; }
    .step-box.active { display: block; animation: fadeIn 0.4s; }
    
    .otp-input-group {
      display: flex;
      gap: 10px;
      justify-content: center;
      margin: 20px 0;
    }
    .otp-field {
      width: 45px;
      height: 55px;
      text-align: center;
      font-size: 1.5rem;
      font-weight: 800;
      border: 2px solid var(--border);
      border-radius: 12px;
      background: #f8fafc;
      transition: all 0.2s;
    }
    .otp-field:focus {
      border-color: var(--accent);
      background: #fff;
      box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
    }
    
    #timer-text { font-size: 0.85rem; color: var(--text-light); text-align: center; margin-top: 15px; }
    #resend-btn { background: none; border: none; color: var(--accent); font-weight: 700; cursor: pointer; padding: 0; font-size: 0.85rem; }
    #resend-btn:disabled { color: var(--muted); cursor: not-allowed; }
  </style>
</head>

<body class="login-screen">

  <div class="login-card">
    <div class="login-header">
      <div class="logo">🔑</div>
      <h2>Reset Password</h2>
      <p id="header-subtitle">Enter your email to receive an OTP</p>
    </div>

    <div id="alert-box" class="flash" style="display:none; margin-bottom:20px; text-align:center"></div>

    <!-- Step 1: Email Input -->
    <div id="step-email" class="step-box active">
      <div class="form-group" style="margin-bottom:24px">
        <label for="email">Registered Email Address</label>
        <input type="email" id="email" placeholder="name@example.com" required autofocus>
      </div>
      <button type="button" id="btn-send-otp" class="btn btn-primary" style="width:100%; justify-content:center; padding:16px">
        📩 Send OTP
      </button>
      <div style="text-align:center; margin-top:20px">
        <a href="login.php" style="font-size:0.85rem; color:var(--text-light); font-weight:600">Back to Login</a>
      </div>
    </div>

    <!-- Step 2: OTP Verification -->
    <div id="step-otp" class="step-box">
      <p style="text-align:center; font-size:0.9rem; color:var(--text-light); margin-bottom:10px">
        Enter the 6-digit code sent to <br><strong id="display-email"></strong>
      </p>
      
      <div class="otp-input-group">
        <input type="text" class="otp-field" maxlength="1" pattern="\d*">
        <input type="text" class="otp-field" maxlength="1" pattern="\d*">
        <input type="text" class="otp-field" maxlength="1" pattern="\d*">
        <input type="text" class="otp-field" maxlength="1" pattern="\d*">
        <input type="text" class="otp-field" maxlength="1" pattern="\d*">
        <input type="text" class="otp-field" maxlength="1" pattern="\d*">
      </div>

      <button type="button" id="btn-verify-otp" class="btn btn-primary" style="width:100%; justify-content:center; padding:16px">
        ✅ Verify OTP
      </button>

      <div id="timer-text">
        Didn't get code? <button id="resend-btn" disabled>Resend in <span id="timer-sec">30</span>s</button>
      </div>
      
      <div style="text-align:center; margin-top:20px">
        <button type="button" onclick="changeEmail()" style="background:none; border:none; font-size:0.85rem; color:var(--text-light); font-weight:600; cursor:pointer">Change Email</button>
      </div>
    </div>

    <!-- Step 3: New Password -->
    <div id="step-reset" class="step-box">
      <div class="form-group" style="margin-bottom:20px">
        <label for="new-password">New Password</label>
        <input type="password" id="new-password" placeholder="••••••••" required>
      </div>
      <div class="form-group" style="margin-bottom:24px">
        <label for="confirm-password">Confirm New Password</label>
        <input type="password" id="confirm-password" placeholder="••••••••" required>
      </div>
      <button type="button" id="btn-reset-password" class="btn btn-primary" style="width:100%; justify-content:center; padding:16px">
        🔒 Update Password
      </button>
    </div>

    <!-- Step 4: Success -->
    <div id="step-success" class="step-box" style="text-align:center">
      <div style="font-size:3rem; margin-bottom:20px">🎉</div>
      <h3 style="margin-bottom:10px; color:#0f172a">Password Updated!</h3>
      <p style="font-size:0.9rem; color:var(--text-light); margin-bottom:25px">Your password has been reset successfully. You can now log in with your new credentials.</p>
      <a href="login.php" class="btn btn-primary" style="width:100%; justify-content:center; padding:16px">
        🚀 Go to Login
      </a>
    </div>

    <p style="text-align:center; font-size:0.75rem; color:var(--text-light); margin-top:30px">
      © <?= date('Y') ?> Vingo Security
    </p>
  </div>

  <script>
    const alertBox = document.getElementById('alert-box');
    const headerSubtitle = document.getElementById('header-subtitle');
    
    let userEmail = '';
    let resendTimer = 30;
    let timerInterval;

    function showAlert(msg, type = 'danger') {
      alertBox.textContent = (type === 'danger' ? '❌ ' : '✅ ') + msg;
      alertBox.className = `flash flash-${type}`;
      alertBox.style.display = 'block';
      setTimeout(() => { alertBox.style.display = 'none'; }, 5000);
    }

    // ── STEP 1: SEND OTP ──
    document.getElementById('btn-send-otp').addEventListener('click', async () => {
      const email = document.getElementById('email').value.trim();
      if (!email) return showAlert('Please enter your email.');

      const btn = document.getElementById('btn-send-otp');
      btn.disabled = true;
      btn.textContent = '⌛ Sending...';

      try {
        const res = await fetch('../api/send_otp.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ email })
        });
        const json = await res.json();
        if (json.success) {
          userEmail = email;
          document.getElementById('display-email').textContent = email;
          switchStep('otp');
          headerSubtitle.textContent = 'Verify your identity';
          startResendTimer();
          showAlert(json.message, 'success');
        } else {
          showAlert(json.error);
        }
      } catch (err) {
        showAlert('Network error. Please try again.');
      } finally {
        btn.disabled = false;
        btn.textContent = '📩 Send OTP';
      }
    });

    // ── STEP 2: VERIFY OTP ──
    const otpFields = document.querySelectorAll('.otp-field');
    otpFields.forEach((field, index) => {
      field.addEventListener('input', (e) => {
        if (e.target.value && index < otpFields.length - 1) {
          otpFields[index + 1].focus();
        }
      });
      field.addEventListener('keydown', (e) => {
        if (e.key === 'Backspace' && !e.target.value && index > 0) {
          otpFields[index - 1].focus();
        }
      });
    });

    document.getElementById('btn-verify-otp').addEventListener('click', async () => {
      const otp = Array.from(otpFields).map(f => f.value).join('');
      if (otp.length < 6) return showAlert('Please enter the 6-digit OTP.');

      const btn = document.getElementById('btn-verify-otp');
      btn.disabled = true;
      btn.textContent = '⌛ Verifying...';

      try {
        const res = await fetch('../api/verify_otp.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ email: userEmail, otp })
        });
        const json = await res.json();
        if (json.success) {
          switchStep('reset');
          headerSubtitle.textContent = 'Set your new password';
          showAlert(json.message, 'success');
        } else {
          showAlert(json.error);
        }
      } catch (err) {
        showAlert('Network error. Please try again.');
      } finally {
        btn.disabled = false;
        btn.textContent = '✅ Verify OTP';
      }
    });

    // ── STEP 3: RESET PASSWORD ──
    document.getElementById('btn-reset-password').addEventListener('click', async () => {
      const password = document.getElementById('new-password').value;
      const confirm_password = document.getElementById('confirm-password').value;

      if (password.length < 6) return showAlert('Password must be at least 6 characters.');
      if (password !== confirm_password) return showAlert('Passwords do not match.');

      const btn = document.getElementById('btn-reset-password');
      btn.disabled = true;
      btn.textContent = '⌛ Updating...';

      try {
        const res = await fetch('../api/reset_password.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ password, confirm_password })
        });
        const json = await res.json();
        if (json.success) {
          switchStep('success');
          headerSubtitle.textContent = 'Account Recovered';
        } else {
          showAlert(json.error);
        }
      } catch (err) {
        showAlert('Network error. Please try again.');
      } finally {
        btn.disabled = false;
        btn.textContent = '🔒 Update Password';
      }
    });

    // ── HELPERS ──
    function switchStep(stepId) {
      document.querySelectorAll('.step-box').forEach(box => box.classList.remove('active'));
      document.getElementById('step-' + stepId).classList.add('active');
    }

    function changeEmail() {
      switchStep('email');
      headerSubtitle.textContent = 'Enter your email to receive an OTP';
      clearInterval(timerInterval);
    }

    function startResendTimer() {
      resendTimer = 30;
      const btn = document.getElementById('resend-btn');
      const sec = document.getElementById('timer-sec');
      btn.disabled = true;
      
      clearInterval(timerInterval);
      timerInterval = setInterval(() => {
        resendTimer--;
        sec.textContent = resendTimer;
        if (resendTimer <= 0) {
          clearInterval(timerInterval);
          btn.disabled = false;
          document.getElementById('timer-text').innerHTML = `<button id="resend-btn" onclick="resendOTP()" style="background:none; border:none; color:var(--accent); font-weight:700; cursor:pointer; padding:0; font-size:0.85rem;">Resend OTP</button>`;
        }
      }, 1000);
    }

    async function resendOTP() {
      const btn = document.getElementById('btn-send-otp'); // reuse logic
      btn.click();
    }
  </script>
</body>
</html>
