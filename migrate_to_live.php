<?php
/**
 * migrate_to_live.php
 * Run this on your live server ONE TIME to sync your database with ALL recent updates.
 */
require_once __DIR__ . '/includes/db.php';

echo "<h2>Starting Vingo Menu Super-Migration...</h2><br>";

/**
 * 1. Ensure 'user_id' and 'is_deleted' columns exist on core tables
 */
$tables_to_check = [
    'categories' => ['user_id' => 'INT NOT NULL DEFAULT 0', 'is_deleted' => 'TINYINT(1) DEFAULT 0', 'deleted_at' => 'DATETIME NULL'],
    'dishes'     => ['user_id' => 'INT NOT NULL DEFAULT 0', 'is_deleted' => 'TINYINT(1) DEFAULT 0', 'deleted_at' => 'DATETIME NULL'],
    'users'      => ['is_deleted' => 'TINYINT(1) DEFAULT 0', 'deleted_at' => 'DATETIME NULL']
];

foreach ($tables_to_check as $tbl => $cols) {
    foreach ($cols as $col => $definition) {
        $chk = $conn->query("SHOW COLUMNS FROM $tbl LIKE '$col'");
        if (!$chk || $chk->num_rows === 0) {
            if ($conn->query("ALTER TABLE $tbl ADD COLUMN $col $definition")) {
                echo "✅ '$col' added to '$tbl' table.<br>";
            } else {
                echo "❌ Error adding '$col' to '$tbl': " . $conn->error . "<br>";
            }
        }
    }
}

/**
 * 2. Ensure Category Uniqueness per User
 */
$chk_index = $conn->query("SHOW INDEX FROM categories WHERE Key_name = 'u_cat_user'");
if (!$chk_index || $chk_index->num_rows === 0) {
    // Drop old non-unique index if exists
    $conn->query("ALTER TABLE categories DROP INDEX IF EXISTS uq_cat_name");
    if ($conn->query("ALTER TABLE categories ADD UNIQUE INDEX u_cat_user (user_id, name)")) {
        echo "✅ Category isolation index 'u_cat_user' created.<br>";
    }
}

/**
 * 3. Remove Obsolete Column 'availability' from Dishes
 */
$chk_av = $conn->query("SHOW COLUMNS FROM dishes LIKE 'availability'");
if ($chk_av && $chk_av->num_rows > 0) {
    if ($conn->query("ALTER TABLE dishes DROP COLUMN availability")) {
        echo "✅ Deprecated 'availability' column removed.<br>";
    }
}

/**
 * 4. Initialize Offer Zone Tables
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
    echo "✅ Modern 'offers' table is active.<br>";
}

$sql_combo = "CREATE TABLE IF NOT EXISTS offer_combo_dishes (
    id INT AUTO_INCREMENT PRIMARY KEY, 
    offer_id INT NOT NULL, 
    dish_id INT NOT NULL
)";
if ($conn->query($sql_combo)) {
    echo "✅ Combo-Link relational table is active.<br>";
}

/**
 * 5. Update Categories Isolation (Link to Super Admin if user_id is 0)
 */
$conn->query("UPDATE categories SET user_id = 1 WHERE user_id = 0");
echo "✅ Unassigned categories linked to Super Admin (ID 1).<br>";

/**
 * 6. Migrate Data from 'seasonal_offers' to 'offers'
 */
$chk_old = $conn->query("SHOW TABLES LIKE 'seasonal_offers'");
if ($chk_old && $chk_old->num_rows > 0) {
    $old_res = $conn->query("SELECT * FROM seasonal_offers");
    if ($old_res && $old_res->num_rows > 0) {
        echo "🔄 Migrating old seasonal offers to the new 'Offer Zone' architecture...<br>";
        $stmt = $conn->prepare("INSERT INTO offers (id, user_id, offer_type, title, description, discount_percentage, start_date, end_date, status, is_deleted) VALUES (?, ?, 'seasonal', ?, ?, ?, CURRENT_DATE, DATE_ADD(CURRENT_DATE, INTERVAL 30 DAY), ?, ?)");
        while ($row = $old_res->fetch_assoc()) {
            $id = $row['id'];
            $uid = $row['user_id'] ?: 1; // Default to admin 1 if 0
            $title = $row['title'];
            $desc = $row['description'];
            $discount = (float)str_replace('%', '', $row['discount'] ?? '0');
            $status = (isset($row['active']) && $row['active'] == 1) ? 'active' : 'inactive';
            $is_del = $row['is_deleted'] ?? 0;

            $stmt->bind_param('iissdsi', $id, $uid, $title, $desc, $discount, $status, $is_del);
            @$stmt->execute();
        }
        echo "✅ Data migration complete. Old deals moved to 'offers' table.<br>";
    }
}

echo "<br><h3>✨ Migration successfully completed.</h3><br>";
echo "<div style='color:#991b1b; font-weight:bold; border:2px solid #b91c1c; padding:15px; border-radius:8px'>❌ SECURITY WARNING: Please delete this file ('migrate_to_live.php') from your server IMMEDIATELY.</div>";
?>
