<?php
require_once __DIR__ . '/includes/db.php';
$conn->query("ALTER TABLE seasonal_offers ADD COLUMN active TINYINT(1) DEFAULT 1 AFTER expires_at");
echo "✅ Active column added.";
unlink(__FILE__);
?>
