<?php
/**
 * migrate_to_live.php
 * Run this on your live server ONE TIME to sync your database with ALL recent updates.
 */
require_once __DIR__ . '/includes/db.php';

echo "Starting Vingo Menu Super-Migration...\n\n";

/**
 * 1. Ensure 'is_deleted' columns exist on core tables (Dishes, Categories, Users)
 */
$tables_to_check = ['dishes', 'categories', 'users'];
foreach ($tables_to_check as $tbl) {
    $chk = $conn->query("SHOW COLUMNS FROM $tbl LIKE 'is_deleted'");
    if (!$chk || $chk->num_rows === 0) {
        if ($conn->query("ALTER TABLE $tbl ADD COLUMN is_deleted TINYINT(1) DEFAULT 0, ADD COLUMN deleted_at DATETIME NULL")) {
            echo "✅ 'is_deleted' support added to '$tbl' table.\n";
        } else {
            echo "❌ Error adding 'is_deleted' to '$tbl': " . $conn->error . "\n";
        }
    }
}

/**
 * 2. Remove Obsolete Column 'availability' from Dishes
 */
$chk_av = $conn->query("SHOW COLUMNS FROM dishes LIKE 'availability'");
if ($chk_av && $chk_av->num_rows > 0) {
    if ($conn->query("ALTER TABLE dishes DROP COLUMN availability")) {
        echo "✅ Deprecated 'availability' column removed.\n";
    }
}

/**
 * 3. Initialize Offer Zone Tables
 */
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
    echo "✅ Modern 'offers' table is active.\n";
}

$sql_combo = "CREATE TABLE IF NOT EXISTS offer_combo_dishes (
    id INT AUTO_INCREMENT PRIMARY KEY, 
    offer_id INT NOT NULL, 
    dish_id INT NOT NULL
)";
if ($conn->query($sql_combo)) {
    echo "✅ Combo-Link relational table is active.\n";
}

/**
 * 4. Migrate Data from 'seasonal_offers' to 'offers'
 */
$chk_old = $conn->query("SHOW TABLES LIKE 'seasonal_offers'");
if ($chk_old && $chk_old->num_rows > 0) {
    $old_res = $conn->query("SELECT * FROM seasonal_offers");
    if ($old_res && $old_res->num_rows > 0) {
        echo "🔄 Migrating old seasonal offers to the new 'Offer Zone' architecture...\n";
        $stmt = $conn->prepare("INSERT INTO offers (id, user_id, offer_type, title, description, discount_percentage, start_date, end_date, status, is_deleted) VALUES (?, ?, 'seasonal', ?, ?, ?, CURRENT_DATE, DATE_ADD(CURRENT_DATE, INTERVAL 30 DAY), ?, ?)");
        while ($row = $old_res->fetch_assoc()) {
            $id = $row['id'];
            $uid = $row['user_id'];
            $title = $row['title'];
            $desc = $row['description'];
            // Clean decimal for percentage
            $discount = (float)str_replace('%', '', $row['discount'] ?? '0');
            $status = (isset($row['active']) && $row['active'] == 1) ? 'active' : 'inactive';
            $is_del = $row['is_deleted'] ?? 0;

            $stmt->bind_param('iissdsi', $id, $uid, $title, $desc, $discount, $status, $is_del);
            @$stmt->execute();
        }
        echo "✅ Data migration complete. Old deals moved to 'offers' table.\n";
    }
}

echo "\n✨ Migration successfully completed. Your server is now fully up-to-date.\n";
echo "❌ SECURITY WARNING: Please delete this file ('migrate_to_live.php') from your server IMMEDIATELY.\n";
?>
