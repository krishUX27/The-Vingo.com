<?php
// install.php - Universal Vingo Setup Wizard
require_once __DIR__ . '/includes/db.php';

$message = '';
$step = isset($_POST['step']) ? (int)$_POST['step'] : 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($step === 1) {
        // Step 1: Core Tables Initialization
        $tables = [
            "users" => "CREATE TABLE IF NOT EXISTS users (
                id INT AUTO_INCREMENT PRIMARY KEY, 
                username VARCHAR(50) UNIQUE, 
                email VARCHAR(100), 
                password VARCHAR(255), 
                role ENUM('superadmin', 'admin') DEFAULT 'admin', 
                status ENUM('active','hold') DEFAULT 'active', 
                is_deleted TINYINT(1) DEFAULT 0,
                deleted_at DATETIME NULL,
                activation_token VARCHAR(128) DEFAULT NULL, 
                token_expiry DATETIME DEFAULT NULL, 
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )",
            "categories" => "CREATE TABLE IF NOT EXISTS categories (
                id INT AUTO_INCREMENT PRIMARY KEY, 
                user_id INT, 
                name VARCHAR(100), 
                is_deleted TINYINT(1) DEFAULT 0,
                deleted_at DATETIME NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )",
            "dishes" => "CREATE TABLE IF NOT EXISTS dishes (
                id INT AUTO_INCREMENT PRIMARY KEY, 
                user_id INT, 
                category_id INT, 
                name VARCHAR(100), 
                price DECIMAL(10,2), 
                description TEXT, 
                image VARCHAR(255), 
                availability ENUM('Available', 'Not Available') DEFAULT 'Available', 
                veg_type ENUM('veg','non_veg') NOT NULL DEFAULT 'veg',
                available_breakfast TINYINT(1) DEFAULT 1,
                available_lunch TINYINT(1) DEFAULT 1,
                available_dinner TINYINT(1) DEFAULT 1,
                currency VARCHAR(10) DEFAULT 'INR', 
                offer_id INT NULL, 
                is_deleted TINYINT(1) DEFAULT 0,
                deleted_at DATETIME NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )",
            "settings" => "CREATE TABLE IF NOT EXISTS settings (
                id INT AUTO_INCREMENT PRIMARY KEY, 
                user_id INT DEFAULT 0, 
                setting_key VARCHAR(50), 
                setting_value TEXT, 
                UNIQUE KEY u_user_setting (user_id, setting_key)
            )",
            "seasonal_offers" => "CREATE TABLE IF NOT EXISTS seasonal_offers (
                id INT AUTO_INCREMENT PRIMARY KEY, 
                user_id INT, 
                title VARCHAR(100), 
                description TEXT, 
                discount VARCHAR(50), 
                active TINYINT DEFAULT 1, 
                is_deleted TINYINT(1) DEFAULT 0,
                deleted_at DATETIME NULL,
                expires_at DATE NULL, 
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )",
            "qr_scans" => "CREATE TABLE IF NOT EXISTS qr_scans (
                user_id INT PRIMARY KEY, 
                scan_count INT DEFAULT 0
            )",
            "menu_imports" => "CREATE TABLE IF NOT EXISTS menu_imports (
                id INT AUTO_INCREMENT PRIMARY KEY, 
                admin_id INT NOT NULL, 
                file_name VARCHAR(255), 
                file_type VARCHAR(20), 
                file_path VARCHAR(255), 
                uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP, 
                status ENUM('processing','completed','failed') DEFAULT 'processing'
            )",
            "trash_logs" => "CREATE TABLE IF NOT EXISTS trash_logs (
                id INT AUTO_INCREMENT PRIMARY KEY, 
                item_type VARCHAR(50), 
                item_id INT, 
                original_data JSON, 
                deleted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )"
        ];

        try {
            foreach ($tables as $name => $sql) {
                if (!$conn->query($sql)) {
                    throw new Exception("Error creating table '$name': " . $conn->error);
                }
            }
            $message = "Database tables initialized successfully!";
            $step = 2;
        } catch (Exception $e) {
            $message = "Installation Error: " . $e->getMessage();
        }
    } elseif ($step === 2) {
        // Step 2: Super Admin Creation (Or Skip)
        if (isset($_POST['skip_admin'])) {
            $message = "Super Admin creation skipped. Setup complete!";
            $step = 3;
        } else {
            $user = trim($_POST['username'] ?? '');
            $pass = password_hash($_POST['password'] ?? '', PASSWORD_DEFAULT);
            $email = trim($_POST['email'] ?? '');

            if ($user && $_POST['password']) {
                $stmt = $conn->prepare("INSERT INTO users (username, password, email, role) VALUES (?, ?, ?, 'superadmin') ON DUPLICATE KEY UPDATE password=VALUES(password), email=VALUES(email)");
                $stmt->bind_param("sss", $user, $pass, $email);
                if ($stmt->execute()) {
                    $message = "Super Admin account created successfully!";
                    $step = 3;
                } else {
                    $message = "Error: " . $conn->error;
                }
            } else {
                $message = "Please fill in all fields.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Vingo Setup Wizard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="assets/css/menu-style.css?v=<?= time() ?>">
    <style>
        body { background: #f8fafc; height: 100vh; display: flex; align-items: center; justify-content: center; }
        .setup-card { background: #fff; padding: 40px; border-radius: 20px; box-shadow: 0 20px 50px rgba(0,0,0,0.1); width: min(450px, 95vw); text-align: center; }
        .step-indicator { display: flex; justify-content: center; gap: 10px; margin-bottom: 30px; }
        .step-dot { width: 10px; height: 10px; border-radius: 50%; background: #e2e8f0; }
        .step-dot.active { background: #3b82f6; box-shadow: 0 0 10px rgba(59,130,246,0.5); }
    </style>
</head>
<body>

<div class="setup-card">
    <div style="font-size: 3rem; margin-bottom: 20px">⚙️</div>
    <h2 style="margin-bottom: 10px">Vingo Setup</h2>
    <p style="color: #64748b; margin-bottom: 30px; font-size: 0.9rem">
        Step <?= $step ?> of 3
    </p>

    <div class="step-indicator">
        <div class="step-dot <?= $step >= 1 ? 'active' : '' ?>"></div>
        <div class="step-dot <?= $step >= 2 ? 'active' : '' ?>"></div>
        <div class="step-dot <?= $step >= 3 ? 'active' : '' ?>"></div>
    </div>

    <?php if ($message): ?>
        <div class="flash flash-info" style="margin-bottom: 20px; font-size: 0.85rem"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <?php if ($step === 1): ?>
        <form method="POST">
            <input type="hidden" name="step" value="1">
            <p style="margin-bottom: 20px; font-size: 0.9rem">Deploy core database structure for the platform.</p>
            <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center">Initialize Tables</button>
        </form>
    <?php elseif ($step === 2): ?>
        <form method="POST">
            <input type="hidden" name="step" value="2">
            <div class="form-group" style="text-align: left; margin-bottom: 15px">
                <label>Master Username</label>
                <input type="text" name="username" placeholder="e.g. root_admin" required>
            </div>
            <div class="form-group" style="text-align: left; margin-bottom: 15px">
                <label>Master Password</label>
                <input type="password" name="password" required>
            </div>
            <div class="form-group" style="text-align: left; margin-bottom: 15px">
                <label>Recovery Email</label>
                <input type="email" name="email" placeholder="admin@vingo-menu.com">
            </div>
            <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center; margin-bottom: 12px">Create Super Admin</button>
            <button type="submit" name="skip_admin" value="1" class="btn btn-outline" style="width: 100%; justify-content: center" onclick="return confirm('Skip creating admin? Database tables will still be updated.')">Skip & Finalize</button>
        </form>
    <?php elseif ($step === 3): ?>
        <p style="margin-bottom: 30px">Setup complete! Access your consoles below.</p>
        <div style="display: flex; flex-direction: column; gap: 12px">
            <a href="superadmin/index.php" class="btn btn-primary" style="justify-content: center">Master Root Console</a>
            <a href="index.php" class="btn btn-outline" style="justify-content: center">View Public Home</a>
        </div>
        <p style="color: #ef4444; font-size: 0.75rem; margin-top: 25px; font-weight: 700">Recommended: Remove install.php now!</p>
    <?php endif; ?>
</div>

</body>
</html>
