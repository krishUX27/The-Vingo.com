<?php
// patch_db_all.php - Nuclear fix (FIXED SYNTAX)
$host = '127.0.0.1';
$username = 'root';
$password = '';

try {
    $conn = new PDO("mysql:host=$host", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Get all databases
    $stmt = $conn->query("SHOW DATABASES LIKE 'vingo_%'");
    $dbs = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($dbs)) {
        $dbs[] = 'vingo_db';
    }

    foreach ($dbs as $db) {
        try {
            $conn->exec("USE `$db` ");
            // Check if table exists
            $tableExists = $conn->query("SHOW TABLES LIKE 'menu_items'")->rowCount() > 0;
            if (!$tableExists) {
                echo "⏭️ Skipping $db: menu_items table not found.<br>";
                continue;
            }

            // Check if column exists
            $checkColumn = $conn->query("SHOW COLUMNS FROM menu_items LIKE 'image_url'")->rowCount() > 0;
            if ($checkColumn) {
                echo "ℹ️ $db.menu_items already has image_url.<br>";
            } else {
                $conn->exec("ALTER TABLE menu_items ADD COLUMN image_url TEXT AFTER seasonal");
                echo "✅ Successfully added image_url to <b>$db.menu_items</b><br>";
            }
        } catch (PDOException $e) {
            echo "⚠️ Error in $db: " . $e->getMessage() . "<br>";
        }
    }

    echo "<br>🚀 All detected Vingo databases have been updated!";
    echo "<br>👉 <b>Please refresh your Menu Items page and try adding again!</b>";

} catch (PDOException $e) {
    echo "❌ Server Error: " . $e->getMessage();
}
?>
