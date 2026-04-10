<?php
require_once __DIR__ . '/includes/db.php';

$sql = "CREATE TABLE IF NOT EXISTS qr_scan_logs (
    scan_id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    qr_id VARCHAR(50) DEFAULT 'default',
    ip_address VARCHAR(45),
    device_info TEXT,
    scan_timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

if ($conn->query($sql)) {
    echo "✅ Table 'qr_scan_logs' created or already exists.\n";
} else {
    echo "❌ Error creating table: " . $conn->error . "\n";
}

// Also ensure the summary table exists as backup or legacy
$conn->query("CREATE TABLE IF NOT EXISTS qr_scans (user_id INT PRIMARY KEY, scan_count INT DEFAULT 0)");

?>
