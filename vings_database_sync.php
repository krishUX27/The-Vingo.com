<?php
// vings_database_sync.php — Safe one-click sync for Hostinger
require_once __DIR__ . '/includes/db.php';

echo "<h2>Vingo Database Sync Tool</h2>";
echo "<p>Checking and adding missing columns...</p>";

$tasks = [
    "Adding 'is_deleted' to users" => "ALTER TABLE users ADD COLUMN IF NOT EXISTS is_deleted TINYINT(1) DEFAULT 0, ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL",
    "Adding 'is_deleted' to categories" => "ALTER TABLE categories ADD COLUMN IF NOT EXISTS is_deleted TINYINT(1) DEFAULT 0, ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL",
    "Adding 'is_deleted' to dishes" => "ALTER TABLE dishes ADD COLUMN IF NOT EXISTS is_deleted TINYINT(1) DEFAULT 0, ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL",
    "Adding 'veg_type' to dishes" => "ALTER TABLE dishes ADD COLUMN IF NOT EXISTS veg_type ENUM('veg','non_veg') NOT NULL DEFAULT 'veg'",
    "Adding 'meal times' to dishes" => "ALTER TABLE dishes ADD COLUMN IF NOT EXISTS available_breakfast TINYINT(1) DEFAULT 1, ADD COLUMN IF NOT EXISTS available_lunch TINYINT(1) DEFAULT 1, ADD COLUMN IF NOT EXISTS available_dinner TINYINT(1) DEFAULT 1",
    "Adding 'status' to users" => "ALTER TABLE users ADD COLUMN IF NOT EXISTS status ENUM('active','hold') DEFAULT 'active'",
    "Creating 'trash_logs' table" => "CREATE TABLE IF NOT EXISTS trash_logs (id INT AUTO_INCREMENT PRIMARY KEY, item_type VARCHAR(50), item_id INT, original_data JSON, deleted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)",
    "Creating 'qr_scan_logs' table" => "CREATE TABLE IF NOT EXISTS qr_scan_logs (scan_id INT AUTO_INCREMENT PRIMARY KEY, admin_id INT NOT NULL, qr_id VARCHAR(50) DEFAULT 'default', ip_address VARCHAR(45), device_info TEXT, scan_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP)",
    "Creating 'qr_scans' summary table" => "CREATE TABLE IF NOT EXISTS qr_scans (user_id INT PRIMARY KEY, scan_count INT DEFAULT 0)"
];

foreach ($tasks as $label => $sql) {
    if ($conn->query($sql)) {
        echo "<div style='color:green'>✅ Success: $label</div>";
    } else {
        echo "<div style='color:red'>❌ Failed: $label - " . $conn->error . "</div>";
    }
}

echo "<p><b>Sync complete!</b> You can now go to <a href='admin/dashboard.php'>Dashboard</a>. Please delete this file for security.</p>";
