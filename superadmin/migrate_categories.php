<?php
require_once __DIR__ . '/../includes/db.php';

echo "Updating category table schema...<br>";

// 1. Add user_id if not exists
$conn->query("ALTER TABLE categories ADD COLUMN IF NOT EXISTS user_id INT NOT NULL DEFAULT 0 AFTER id");

// 2. Add soft-delete columns
$conn->query("ALTER TABLE categories ADD COLUMN IF NOT EXISTS is_deleted TINYINT(1) DEFAULT 0 AFTER name");
$conn->query("ALTER TABLE categories ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL AFTER is_deleted");

// 3. Add index
$conn->query("ALTER TABLE categories ADD INDEX IF NOT EXISTS idx_user_cat (user_id)");

echo "Migration finished!";
?>
