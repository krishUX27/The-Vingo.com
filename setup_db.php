<?php
$host = '127.0.0.1';
$username = 'root';
$password = '';

try {
    $conn = new PDO("mysql:host=$host", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $sql = file_get_contents('database.sql');

    // Split SQL into individual queries as PDO might not handle multiple statements in one go depending on config
    // But typically exec handles it if we don't need results.
    // Or we can just run it.

    $conn->exec($sql);
    echo "Database setup successfully.";
} catch (PDOException $e) {
    echo "Connection error: " . $e->getMessage();
}
?>