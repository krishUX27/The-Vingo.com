<?php
require_once __DIR__ . '/../includes/db.php';

// 1. Check dishes table columns
$res = $conn->query("SHOW COLUMNS FROM dishes");
$cols = [];
while ($row = $res->fetch_assoc()) { $cols[] = $row['Field']; }

echo "Dishes table columns: " . implode(', ', $cols) . "\n";

// 2. Ensure 'name' and 'description' columns exist in 'dishes' (as fallback)
if (!in_array('name', $cols)) {
    echo "Adding 'name' column to dishes...\n";
    $conn->query("ALTER TABLE dishes ADD COLUMN name VARCHAR(255) AFTER category_id");
}
if (!in_array('description', $cols)) {
    echo "Adding 'description' column to dishes...\n";
    $conn->query("ALTER TABLE dishes ADD COLUMN description TEXT AFTER name");
}

// 3. Sync data from translations to base table (English as default)
echo "Syncing English translations to base dishes table...\n";
$sync = $conn->query("
    UPDATE dishes d
    JOIN dish_translations t ON t.dish_id = d.id AND t.language_code = 'en'
    SET d.name = t.name, d.description = t.description
    WHERE d.name IS NULL OR d.name = ''
");

if ($sync) {
    echo "Sync complete.\n";
} else {
    echo "Sync failed: " . $conn->error . "\n";
}

// 4. Ensure password_resets table is ready
$conn->query("CREATE TABLE IF NOT EXISTS password_resets (
  id INT AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(255) NOT NULL,
  otp VARCHAR(6) NOT NULL,
  expires_at DATETIME NOT NULL,
  is_used TINYINT(1) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");
echo "Database maintenance complete.\n";
?>
