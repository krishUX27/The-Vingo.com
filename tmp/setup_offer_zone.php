<?php
require_once __DIR__ . '/../includes/db.php';

// 1. Create 'offers' table
$sql1 = "CREATE TABLE IF NOT EXISTS offers (
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
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_deleted TINYINT(1) DEFAULT 0
)";

// 2. Create 'offer_combo_dishes' table
$sql2 = "CREATE TABLE IF NOT EXISTS offer_combo_dishes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    offer_id INT NOT NULL,
    dish_id INT NOT NULL,
    FOREIGN KEY (offer_id) REFERENCES offers(id) ON DELETE CASCADE
)";

if ($conn->query($sql1) && $conn->query($sql2)) {
    echo "Tables 'offers' and 'offer_combo_dishes' created successfully.\n";
} else {
    echo "Error creating tables: " . $conn->error . "\n";
}
?>
