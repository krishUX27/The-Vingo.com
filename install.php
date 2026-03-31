<?php
/**
 * install.php — Premium Setup Wizard for Vingo Menu
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'menu_project';

$conn = new mysqli($db_host, $db_user, $db_pass);
$db_exists = false;
if (!$conn->connect_error) {
    $res = $conn->query("SHOW DATABASES LIKE '$db_name'");
    if ($res && $res->num_rows > 0) $db_exists = true;
}

$step = 1;
$error = '';
$success = '';

// Step 1: Initialize Database
if (isset($_POST['init_db'])) {
    if ($conn->query("CREATE DATABASE IF NOT EXISTS $db_name CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci")) {
        $conn->select_db($db_name);
        // Create Tables
        $conn->query("CREATE TABLE IF NOT EXISTS users (id INT AUTO_INCREMENT PRIMARY KEY, username VARCHAR(50) UNIQUE, email VARCHAR(100), password VARCHAR(255), role ENUM('admin','superadmin') DEFAULT 'admin', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
        $conn->query("CREATE TABLE IF NOT EXISTS categories (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100) UNIQUE)");
        $conn->query("CREATE TABLE IF NOT EXISTS dishes (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(150), price DECIMAL(10,2), category_id INT, image VARCHAR(255) DEFAULT NULL, availability ENUM('Available','Not Available') DEFAULT 'Available', currency VARCHAR(10) DEFAULT 'INR', offer_id INT DEFAULT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB");
        $conn->query("CREATE TABLE IF NOT EXISTS settings (id INT AUTO_INCREMENT PRIMARY KEY, setting_key VARCHAR(50) UNIQUE, setting_value TEXT)");
        $conn->query("CREATE TABLE IF NOT EXISTS system_logs (id INT AUTO_INCREMENT PRIMARY KEY, event VARCHAR(100), source VARCHAR(50), status VARCHAR(20), created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)");
        $conn->query("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES ('restaurant_name', 'Vingo Menu'), ('restaurant_sub', 'Digital Excellence')");
        $success = "Database initialized! Proceed to create your master account.";
        $step = 2;
    } else {
        $error = "Failed to create database: " . $conn->error;
    }
}

// Step 2: Create Master Account
if (isset($_POST['create_master'])) {
    $conn->select_db($db_name);
    $u = trim($_POST['master_user'] ?? '');
    $p = $_POST['master_pass'] ?? '';
    if (strlen($p) < 6) {
        $error = "Password must be at least 6 characters.";
        $step = 2;
    } else {
        $hash = password_hash($p, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, 'superadmin')");
        $stmt->bind_param('ss', $u, $hash);
        if ($stmt->execute()) {
            $success = "Master account created! System ready.";
            $step = 3;
        } else {
            $error = "Setup failed: " . $conn->error;
            $step = 2;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Setup Wizard | Vingo</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --p: #6366f1; --bg: #f8fafc; }
        body { font-family: 'Inter', sans-serif; background: var(--bg); display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; }
        .card { background: #fff; padding: 40px; border-radius: 24px; box-shadow: 0 20px 50px rgba(0,0,0,0.05); width: 450px; text-align: center; }
        h1 { font-weight: 800; color: #0f172a; margin-bottom: 8px; }
        p { color: #64748b; font-size: 0.9rem; margin-bottom: 30px; }
        .step-indicator { display: flex; justify-content: center; gap: 10px; margin-bottom: 30px; }
        .dot { width: 10px; height: 10px; border-radius: 50%; background: #e2e8f0; }
        .dot.active { background: var(--p); transform: scale(1.2); }
        .form-group { text-align: left; margin-bottom: 20px; }
        label { display: block; font-size: 0.75rem; font-weight: 700; color: #475569; text-transform: uppercase; margin-bottom: 8px; }
        input { width: 100%; padding: 12px; border: 1px solid #e2e8f0; border-radius: 10px; outline: none; }
        input:focus { border-color: var(--p); }
        .btn { width: 100%; padding: 14px; background: var(--p); color: #fff; border: none; border-radius: 12px; font-weight: 800; cursor: pointer; transition: 0.2s; }
        .btn:hover { background: #4f46e5; }
        .alert { padding: 15px; border-radius: 10px; font-size: 0.85rem; margin-bottom: 20px; }
        .alert-success { background: #f0fdf4; color: #166534; }
        .alert-error { background: #fef2f2; color: #991b1b; }
    </style>
</head>
<body>
<div class="card">
    <h1>Vingo Setup</h1>
    <p>Welcome to your new digital menu system.</p>

    <div class="step-indicator">
        <div class="dot <?= $step >= 1 ? 'active' : '' ?>"></div>
        <div class="dot <?= $step >= 2 ? 'active' : '' ?>"></div>
        <div class="dot <?= $step >= 3 ? 'active' : '' ?>"></div>
    </div>

    <?php if ($error): ?> <div class="alert alert-error"><?= $error ?></div> <?php endif; ?>
    <?php if ($success): ?> <div class="alert alert-success"><?= $success ?></div> <?php endif; ?>

    <?php if ($step == 1): ?>
        <form method="POST">
            <button type="submit" name="init_db" class="btn">🚀 Initialize Database</button>
        </form>
    <?php elseif ($step == 2): ?>
        <form method="POST">
            <div class="form-group">
                <label>Master Username</label>
                <input type="text" name="master_user" value="superadmin" required>
            </div>
            <div class="form-group">
                <label>Initial Password</label>
                <input type="password" name="master_pass" placeholder="At least 6 characters" required>
            </div>
            <button type="submit" name="create_master" class="btn">🛡️ Finalize Setup</button>
        </form>
    <?php elseif ($step == 3): ?>
        <div class="alert alert-success">System Configured Successfully!</div>
        <p>Please delete <b>install.php</b> for security.</p>
        <a href="superadmin/login.php" class="btn" style="text-decoration:none; display:block">Go to Master Console →</a>
    <?php endif; ?>
</div>
</body>
</html>
