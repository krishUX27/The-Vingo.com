<?php
require_once __DIR__ . '/../includes/db.php';
$conn->query("ALTER TABLE dishes DROP COLUMN availability");
if ($conn->error) {
    echo "Error: " . $conn->error . "\n";
} else {
    echo "Column 'availability' dropped successfully.\n";
}
?>
