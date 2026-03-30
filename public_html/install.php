<?php
/**
 * install.php — Simple Web Setup for Menu Manager
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database connection params
$host = 'localhost';
$user = 'root';
$pass = '';
$dbname = 'menu_project';

$conn = new mysqli($host, $user, $pass);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$success = [];
$errors = [];

// 1. Create Database
if ($conn->query("CREATE DATABASE IF NOT EXISTS $dbname CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci")) {
    $success[] = "Database '$dbname' created or already exists.";
} else {
    $errors[] = "Failed to create database: " . $conn->error;
}

$conn->select_db($dbname);

// 2. Create Categories Table
$sql_cats = "CREATE TABLE IF NOT EXISTS categories (
  id   INT          NOT NULL AUTO_INCREMENT,
  name VARCHAR(100) NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_cat_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

if ($conn->query($sql_cats)) {
    $success[] = "Table 'categories' ready.";
} else {
    $errors[] = "Failed 'categories' table: " . $conn->error;
}

// 3. Create Seasonal Offers Table
$sql_offers = "CREATE TABLE IF NOT EXISTS seasonal_offers (
  id           INT          NOT NULL AUTO_INCREMENT,
  title        VARCHAR(150) NOT NULL,
  description  TEXT,
  discount     VARCHAR(50),
  active       TINYINT(1)   DEFAULT 1,
  expires_at   DATE         DEFAULT NULL,
  created_at   TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

if ($conn->query($sql_offers)) {
    $success[] = "Table 'seasonal_offers' ready.";
} else {
    $errors[] = "Failed 'seasonal_offers' table: " . $conn->error;
}

// 4. Create Dishes Table
$sql_dishes = "CREATE TABLE IF NOT EXISTS dishes (
  id           INT            NOT NULL AUTO_INCREMENT,
  name         VARCHAR(150)   NOT NULL,
  price        DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
  category_id  INT            NOT NULL,
  image        VARCHAR(255)   DEFAULT NULL,
  availability ENUM('Available','Not Available') NOT NULL DEFAULT 'Available',
  currency     VARCHAR(10)    DEFAULT 'INR',
  offer_id     INT            DEFAULT NULL,
  created_at   TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_dish_cat
    FOREIGN KEY (category_id) REFERENCES categories(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_dish_offer
    FOREIGN KEY (offer_id) REFERENCES seasonal_offers(id)
    ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

if ($conn->query($sql_dishes)) {
    $success[] = "Table 'dishes' ready.";
} else {
    // Attempt column updates if table exists
    $conn->query("ALTER TABLE dishes ADD COLUMN IF NOT EXISTS currency VARCHAR(10) DEFAULT 'INR' AFTER availability");
    $conn->query("ALTER TABLE dishes ADD COLUMN IF NOT EXISTS offer_id INT DEFAULT NULL AFTER currency");
    $success[] = "Table 'dishes' updated/ready.";
}

// 5. Create Settings Table
$sql_settings = "CREATE TABLE IF NOT EXISTS settings (
  setting_key   VARCHAR(50)  NOT NULL,
  setting_value TEXT,
  PRIMARY KEY (setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

if ($conn->query($sql_settings)) {
    $success[] = "Table 'settings' ready.";
} else {
    $errors[] = "Failed 'settings' table: " . $conn->error;
}

// 6. Create Users Table
$sql_users = "CREATE TABLE IF NOT EXISTS users (
  id         INT          NOT NULL AUTO_INCREMENT,
  username   VARCHAR(50)  NOT NULL,
  password   VARCHAR(255) NOT NULL,
  role       ENUM('admin', 'superadmin') DEFAULT 'admin',
  email      VARCHAR(100) DEFAULT NULL,
  created_at TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

if ($conn->query($sql_users)) {
    $success[] = "Table 'users' ready.";
} else {
    // Update role column if missing
    $conn->query("ALTER TABLE users ADD COLUMN IF NOT EXISTS role ENUM('admin', 'superadmin') DEFAULT 'admin' AFTER password");
    $success[] = "Table 'users' updated/ready.";
}

// 7. Seed Data
$conn->query("INSERT IGNORE INTO categories (name) VALUES ('Starters'),('Main Course'),('Desserts'),('Beverages');");
$conn->query("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES 
    ('restaurant_name', 'Vingo Restaurant'),
    ('restaurant_sub', 'The Future of SaaS Digital Menu');");

// Seed Admin
$admin_user = 'admin';
$admin_pass = password_hash('admin123', PASSWORD_BCRYPT);
$conn->query("INSERT IGNORE INTO users (username, password, role) VALUES ('$admin_user', '$admin_pass', 'admin')");

// Seed Superadmin
$super_user = 'superadmin';
$super_pass = password_hash('super123', PASSWORD_BCRYPT);
$conn->query("INSERT IGNORE INTO users (username, password, role) VALUES ('$super_user', '$super_pass', 'superadmin')");

$success[] = "Admin and Superadmin seeds checked.";

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Install — Menu Manager</title>
    <style>
        body { font-family: -apple-system, system-ui, sans-serif; line-height: 1.6; background: #f4f7f9; color: #333; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; }
        .card { background: #fff; padding: 2.5rem; border-radius: 16px; box-shadow: 0 10px 40px rgba(0,0,0,0.1); width: 100%; max-width: 550px; }
        h1 { margin-top: 0; color: #1a202c; font-size: 1.75rem; border-bottom: 2px solid #edf2f7; padding-bottom: 1rem; margin-bottom: 1.5rem; }
        .log-item { padding: 10px 14px; border-radius: 8px; margin-bottom: 10px; font-size: 0.95rem; border-left: 5px solid #cbd5e0; }
        .success { background: #f0fff4; border-color: #48bb78; color: #22543d; }
        .error { background: #fff5f5; border-color: #f56565; color: #742a2a; }
        .footer { margin-top: 30px; text-align: center; border-top: 1px solid #edf2f7; padding-top: 1.5rem; }
        .btn { display: inline-block; background: #4c51bf; color: #fff; text-decoration: none; padding: 12px 24px; border-radius: 8px; font-weight: 700; transition: background 0.2s; }
        .btn:hover { background: #434190; }
        code { background: #edf2f7; padding: 2px 5px; border-radius: 4px; font-family: monospace; }
    </style>
</head>
<body>
    <div class="card">
        <h1>🚀 Setup Status</h1>
        
        <?php foreach ($errors as $e): ?>
            <div class="log-item error">❌ <?= htmlspecialchars($e) ?></div>
        <?php endforeach; ?>

        <?php foreach ($success as $s): ?>
            <div class="log-item success">✅ <?= htmlspecialchars($s) ?></div>
        <?php endforeach; ?>

        <div class="footer">
            <?php if (empty($errors)): ?>
                <p><strong>Installation Successful!</strong></p>
                <p style="margin-bottom: 20px; font-size: 0.9rem; color: #4a5568">Default Admin: <code>admin</code> / <code>admin123</code><br>Default Super: <code>superadmin</code> / <code>super123</code></p>
                <a href="admin/dashboard.php" class="btn">Access Dashboard →</a>
                <p style="font-size: 0.8rem; color: #a0aec0; margin-top: 20px;">Please delete <code>install.php</code> after setup.</p>
            <?php else: ?>
                <p>Some errors occurred. Please check your DB configuration and try again.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
