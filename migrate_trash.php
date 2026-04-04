<?php
require_once 'includes/db.php';

$tables = ['users', 'dishes', 'categories'];

foreach ($tables as $t) {
    echo "Updating table: $t\n";
    $conn->query("ALTER TABLE $t ADD COLUMN IF NOT EXISTS is_deleted TINYINT(1) DEFAULT 0");
    $conn->query("ALTER TABLE $t ADD COLUMN IF NOT EXISTS deleted_at DATETIME NULL");
}

// Ensure trash table exists as a secondary safety log if preferred
$conn->query("CREATE TABLE IF NOT EXISTS trash_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    table_name VARCHAR(50),
    record_id INT,
    deleted_by_user_id INT,
    deleted_by_role VARCHAR(20),
    deleted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    data_backup JSON
)");

echo "Migration complete.\n";
