<?php
// setup-password.php - Secure password activation for new admins
session_start();
require_once __DIR__ . '/includes/db.php';

$token = $_GET['token'] ?? '';
$error = '';
$success = false;
$user_id = null;

if (empty($token)) {
    die("Access Denied: Invalid activation token.");
}

// 1. Validate Token
$stmt = $conn->prepare("SELECT id, username, token_expiry, is_active FROM users WHERE activation_token = ? LIMIT 1");
$stmt->bind_param('s', $token);
$stmt->execute();
$res = $stmt->get_result();
$user = $res->fetch_assoc();

if (!$user) {
    $error = "This activation link is invalid or has already been used.";
} else {
    $expiry = strtotime($user['token_expiry']);
    if ($expiry < time()) {
        $error = "This activation link has expired. Please contact superadmin for a new invite.";
    } elseif ($user['is_active'] == 1) {
        $error = "This account is already active. Please log in normally.";
    } else {
        $user_id = $user['id'];
        $username = $user['username'];
    }
}

// 2. Handle Password Setup
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $user_id) {
    $p1 = $_POST['p1'] ?? '';
    $p2 = $_POST['p2'] ?? '';

    if (strlen($p1) < 8) {
        $error = "Password must be at least 8 characters long.";
    } elseif ($p1 !== $p2) {
        $error = "Passwords do not match.";
    } else {
        $hash = password_hash($p1, PASSWORD_DEFAULT);
        
        // Update user: Set password, activate, and clear token
        $upd = $conn->prepare("UPDATE users SET password = ?, is_active = 1, activation_token = NULL, token_expiry = NULL WHERE id = ?");
        $upd->bind_param('si', $hash, $user_id);
        
        if ($upd->execute()) {
            // Auto-login logic
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_username']  = $username;
            $_SESSION['admin_id']        = $user_id;
            
            $success = true;
        } else {
            $error = "System Error: Unable to save password.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Account Setup | Vingo Platform</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="assets/css/menu-style.css">
    <style>
        body { background: #f8fafc; height: 100vh; display: flex; align-items: center; justify-content: center; font-family: 'Outfit', sans-serif; }
        .setup-card { background: #fff; padding: 40px; border-radius: 28px; box-shadow: 0 25px 60px -12px rgba(99,102,241,0.15); width: min(440px, 95vw); text-align: center; border: 1px solid rgba(99,102,241,0.1); }
        .setup-icon { font-size: 3rem; margin-bottom: 20px; }
        h2 { font-weight: 800; color: #0f172a; margin-bottom: 10px; }
        p.sub { color: #64748b; font-size: 0.9rem; margin-bottom: 30px; }
        .form-group { text-align: left; margin-bottom: 20px; }
        label { display: block; font-weight: 700; font-size: 0.75rem; text-transform: uppercase; margin-bottom: 8px; color: #475569; }
        input { width: 100%; padding: 14px; border-radius: 12px; border: 1.5px solid #f1f5f9; background: #f8fafc; transition: all 0.2s; }
        input:focus { border-color: #6366f1; box-shadow: 0 0 0 4px rgba(99,102,241,0.1); background: #fff; }
        .btn-setup { width: 100%; background: #6366f1; color: #fff; padding: 16px; border-radius: 30px; border: none; font-weight: 800; font-size: 0.95rem; cursor: pointer; transition: 0.3s; box-shadow: 0 10px 20px rgba(99,102,241,0.2); }
        .btn-setup:hover { background: #4f46e5; transform: translateY(-2px); box-shadow: 0 15px 30px rgba(99,102,241,0.3); }
        .error-box { background: #fef2f2; color: #991b1b; padding: 15px; border-radius: 12px; margin-bottom: 20px; font-size: 0.85rem; font-weight: 600; border-left: 4px solid #ef4444; }
    </style>
</head>
<body>

<div class="setup-card">
    <?php if ($success): ?>
        <div class="setup-icon">🎉</div>
        <h2>Setup Complete</h2>
        <p class="sub">Your password has been securely saved. You are now logged in.</p>
        <div style="background: #f0fdf4; border-radius: 16px; padding: 20px; margin-bottom: 25px; border: 1px solid #bbf7d0;">
            <p style="color: #166534; font-weight: 700; font-size: 0.9rem;">Welcome aboard, <?= htmlspecialchars($username) ?>!</p>
        </div>
        <a href="admin/dashboard.php" class="btn-setup" style="display: block; text-decoration: none;">🚀 Enter Dashboard</a>

    <?php elseif ($error): ?>
        <div class="setup-icon">⚠️</div>
        <h2>Access Restricted</h2>
        <div class="error-box"><?= htmlspecialchars($error) ?></div>
        <a href="index.php" style="color: #6366f1; font-weight: 700; font-size: 0.9rem; text-decoration: none;">Return to Homepage</a>

    <?php else: ?>
        <div class="setup-icon">🛡️</div>
        <h2>Secure Your Account</h2>
        <p class="sub">Hello <strong><?= htmlspecialchars($username) ?></strong>, please set a strong password to activate your Vingo Menu access.</p>

        <form method="POST">
            <div class="form-group">
                <label>New Password</label>
                <input type="password" name="p1" required placeholder="Min. 8 characters" autofocus>
            </div>
            <div class="form-group">
                <label>Confirm Password</label>
                <input type="password" name="p2" required placeholder="Repeat password">
            </div>
            <button type="submit" class="btn-setup">Activate Account</button>
        </form>
    <?php endif; ?>
</div>

</body>
</html>
