<?php
// patch_db.php - Run this to add the image_url column to existing tables
include_once 'api/db_connection.php';

try {
    $sql = "ALTER TABLE menu_items ADD COLUMN IF NOT EXISTS image_url TEXT AFTER seasonal";
    // For older MySQL versions that don't support ADD COLUMN IF NOT EXISTS, we use try-catch
    try {
        $conn->exec("ALTER TABLE menu_items ADD COLUMN image_url TEXT AFTER seasonal");
        echo "✅ 'image_url' column added successfully to database: " . $db_name . "<br>";
    } catch (PDOException $e) {
        if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
            echo "ℹ️ 'image_url' column already exists in: " . $db_name . "<br>";
        } else {
            throw $e;
        }
    }

    echo "👉 Now you can add items with images!";
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
