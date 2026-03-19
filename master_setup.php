<?php
// master_setup.php - Run this to initialize the database
$host = '127.0.0.1';
$user = 'root';
$pass = '';

try {
    $pdo = new PDO("mysql:host=$host", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $sql = file_get_contents('database_v2.sql');
    $pdo->exec($sql);

    echo "✅ Database 'vingo_menu_db' created successfully!<br>";
    echo "👉 Go to: <a href='admin/menu-items.php'>Admin Panel</a>";

} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>
