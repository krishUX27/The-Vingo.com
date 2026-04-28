<?php
require_once __DIR__ . '/../includes/db.php';

$res = $conn->query("SELECT * FROM menu_imports ORDER BY uploaded_at DESC LIMIT 5");
if ($res && $res->num_rows > 0) {
    while ($row = $res->fetch_assoc()) {
        echo "ID: {$row['id']}, File: {$row['file_name']}, Status: {$row['status']}, Date: {$row['uploaded_at']}\n";
    }
} else {
    echo "No import history found in menu_imports table.\n";
}
?>
