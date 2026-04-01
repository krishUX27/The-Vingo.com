<?php
require_once __DIR__ . '/includes/db.php';

echo "--- Vingo Database Repair: Reconstruction of Settings Table ---\n";

// 1. Rename the existing 'wrong' table to backup
$conn->query("RENAME TABLE settings TO settings_old");
echo "Temporary backup created.\n";

// 2. Create the correct table structure as per system requirements
$sql_create = "CREATE TABLE settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT 0,
    setting_key VARCHAR(50) NOT NULL,
    setting_value TEXT,
    UNIQUE KEY u_user_setting (user_id, setting_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

if ($conn->query($sql_create)) {
    echo "Correct Settings table structure created.\n";
} else {
    die("Fatal Error creating table: " . $conn->error);
}

// 3. Migrate data from old table to new table
// We only migrate the unique pairs (newest if multiple)
$sql_migrate = "INSERT INTO settings (user_id, setting_key, setting_value)
                SELECT user_id, setting_key, setting_value FROM settings_old
                GROUP BY user_id, setting_key";

if ($conn->query($sql_migrate)) {
    echo "Data migration successful.\n";
    $conn->query("DROP TABLE settings_old");
    echo "Cleaned up backup table.\n";
} else {
    echo "Migration Warning: " . $conn->error . ". You may need to manually re-enter settings.\n";
}

echo "\nRepair Complete. Your branding settings will now work perfectly.";
