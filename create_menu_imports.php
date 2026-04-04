<?php
require_once 'includes/db.php';
$sql = "CREATE TABLE IF NOT EXISTS menu_imports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    file_name VARCHAR(255),
    file_type VARCHAR(20),
    file_path VARCHAR(255),
    uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    status ENUM('processing','completed','failed') DEFAULT 'processing'
)";

if($conn->query($sql)) {
    echo "Table menu_imports created successfully!\n";
} else {
    echo "Error: " . $conn->error . "\n";
}
