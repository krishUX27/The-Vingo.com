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

// 3. Create Dishes Table
$sql_dishes = "CREATE TABLE IF NOT EXISTS dishes (
  id           INT            NOT NULL AUTO_INCREMENT,
  name         VARCHAR(150)   NOT NULL,
  price        DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
  category_id  INT            NOT NULL,
  image        VARCHAR(255)   DEFAULT NULL,
  availability ENUM('Available','Not Available') NOT NULL DEFAULT 'Available',
  created_at   TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  CONSTRAINT fk_dish_cat
    FOREIGN KEY (category_id) REFERENCES categories(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

if ($conn->query($sql_dishes)) {
    $success[] = "Table 'dishes' ready.";
} else {
    $errors[] = "Failed 'dishes' table: " . $conn->error;
}

// 4. Create Settings Table (Required for site settings)
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

// 5. Seed Initial Data
$conn->query("INSERT IGNORE INTO categories (name) VALUES ('Starters'),('Main Course'),('Desserts'),('Beverages');");
$conn->query("INSERT IGNORE INTO settings (setting_key, setting_value) VALUES 
    ('restaurant_name', 'Vingo Restaurant'),
    ('restaurant_sub', 'The Future of SaaS Digital Menu');");

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Install — Menu Manager</title>
    <style>
        body { font-family: -apple-system, system-ui, sans-serif; line-height: 1.6; background: #f4f7f9; color: #333; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; }
        .card { background: #fff; padding: 2rem; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); width: 100%; max-width: 500px; }
        h1 { margin-top: 0; color: #1a202c; font-size: 1.5rem; }
        .log-item { padding: 8px 12px; border-radius: 6px; margin-bottom: 8px; font-size: 0.9rem; border-left: 4px solid #eee; }
        .success { background: #f0fff4; border-color: #48bb78; color: #276749; }
        .error { background: #fff5f5; border-color: #f56565; color: #9b2c2c; }
        .footer { margin-top: 24px; text-align: center; }
        .btn { display: inline-block; background: #4c51bf; color: #fff; text-decoration: none; padding: 10px 20px; border-radius: 6px; font-weight: 600; margin-top: 10px; }
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
                <p>Setup complete! Next step: configure <code>includes/db.php</code> if needed.</p>
                <a href="admin/dashboard.php" class="btn">Go to Dashboard →</a>
                <p style="font-size: 0.8rem; color: #718096; margin-top: 20px;">For security, delete <code>install.php</code> after setup.</p>
            <?php else: ?>
                <p>Please resolve the database errors and refresh the page.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
