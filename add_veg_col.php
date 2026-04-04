<?php
require_once 'includes/db.php';
$conn->query("ALTER TABLE dishes ADD COLUMN IF NOT EXISTS veg_type ENUM('veg','non_veg') NOT NULL DEFAULT 'veg'");
echo "Column 'veg_type' added successfully.\n";
