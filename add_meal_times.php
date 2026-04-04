<?php
require_once 'includes/db.php';

$conn->query("ALTER TABLE dishes ADD COLUMN IF NOT EXISTS available_breakfast TINYINT(1) DEFAULT 0");
$conn->query("ALTER TABLE dishes ADD COLUMN IF NOT EXISTS available_lunch TINYINT(1) DEFAULT 0");
$conn->query("ALTER TABLE dishes ADD COLUMN IF NOT EXISTS available_dinner TINYINT(1) DEFAULT 0");

echo "Meal time columns added successfully.\n";
