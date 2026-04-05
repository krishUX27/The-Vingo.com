<?php
/**
 * migrate_to_live.php
 * Run this on your live server ONE TIME to sync your database with current updates.
 */
require_once __DIR__ . '/includes/db.php';

echo "Starting Vingo Menu Database Migration...\n";

// 1. Remove Obsolete Column 'availability'
$chk = $conn->query("SHOW COLUMNS FROM dishes LIKE 'availability'");
if ($chk && $chk->num_rows > 0) {
    if ($conn->query("ALTER TABLE dishes DROP COLUMN availability")) {
        echo "✅ Column 'availability' dropped from 'dishes' table.\n";
    } else {
        echo "❌ Error dropping 'availability': " . $conn->error . "\n";
    }
}

// 2. Create 'offers' table
$sql_offers = "CREATE TABLE IF NOT EXISTS offers (
    id INT AUTO_INCREMENT PRIMARY KEY, 
    user_id INT NOT NULL, 
    offer_type ENUM('seasonal', 'combo') NOT NULL, 
    title VARCHAR(255) NOT NULL, 
    description TEXT, 
    discount_percentage DECIMAL(5,2) DEFAULT NULL, 
    combo_price DECIMAL(10,2) DEFAULT NULL, 
    start_date DATE NOT NULL, 
    end_date DATE NOT NULL, 
    status ENUM('active', 'inactive') DEFAULT 'active', 
    is_deleted TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
if ($conn->query($sql_offers)) {
    echo "✅ Table 'offers' is ready.\n";
} else {
    echo "❌ Error creating 'offers' table: " . $conn->error . "\n";
}

// 3. Create 'offer_combo_dishes' table
$sql_combo = "CREATE TABLE IF NOT EXISTS offer_combo_dishes (
    id INT AUTO_INCREMENT PRIMARY KEY, 
    offer_id INT NOT NULL, 
    dish_id INT NOT NULL
)";
if ($conn->query($sql_combo)) {
    echo "✅ Table 'offer_combo_dishes' is ready.\n";
} else {
    echo "❌ Error creating 'offer_combo_dishes' table: " . $conn->error . "\n";
}

// 4. Migrate Old Seasonal Offers (Optional)
$chk_old = $conn->query("SHOW TABLES LIKE 'seasonal_offers'");
if ($chk_old && $chk_old->num_rows > 0) {
    $old_res = $conn->query("SELECT * FROM seasonal_offers");
    if ($old_res && $old_res->num_rows > 0) {
        echo "🔄 Migrating old seasonal offers to the new 'Offer Zone'...\n";
        $stmt = $conn->prepare("INSERT INTO offers (id, user_id, offer_type, title, description, discount_percentage, start_date, end_date, status, is_deleted) VALUES (?, ?, 'seasonal', ?, ?, ?, CURRENT_DATE, DATE_ADD(CURRENT_DATE, INTERVAL 30 DAY), ?, ?)");
        while ($row = $old_res->fetch_assoc()) {
            $id = $row['id'];
            $uid = $row['user_id'];
            $title = $row['title'];
            $desc = $row['description'];
            // Clean decimal for percentage
            $discount = (float)str_replace('%', '', $row['discount']);
            $status = ($row['active'] == 1) ? 'active' : 'inactive';
            $is_del = $row['is_deleted'];

            $stmt->bind_param('iissdsi', $id, $uid, $title, $desc, $discount, $status, $is_del);
            @$stmt->execute();
        }
        echo "✅ Migration complete. Old data is now in 'offers'.\n";
    }
}

echo "\nMigration script finished. You can now delete this file from your server.\n";
?>
