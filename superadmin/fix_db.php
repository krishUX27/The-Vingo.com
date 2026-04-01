<?php
require_once __DIR__ . '/../includes/db.php';

// 1. Delete all but the latest entries for each setting_key per user_id
echo "Cleaning up duplicates...<br>";
$conn->query("
    DELETE FROM settings 
    WHERE id NOT IN (
        SELECT max_id FROM (
            SELECT MAX(id) as max_id 
            FROM settings 
            GROUP BY user_id, setting_key
        ) as t
    )
");

// 2. Add Unique Index
echo "Adding unique constraint...<br>";
$conn->query("ALTER TABLE settings ADD UNIQUE INDEX IF NOT EXISTS u_user_setting (user_id, setting_key)");

echo "Migration complete! Redirecting to Dashboard...<br>";
header('Refresh: 3; URL=index.php');
exit;
