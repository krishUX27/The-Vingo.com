<?php
// register.php

// Database connection parameters (for creating the new DB)
$host = '127.0.0.1';
$username = 'root';
$password = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // 1. Capture Form Data
    $fullname = $_POST['fullname'] ?? '';
    $restaurantName = $_POST['restaurant'] ?? '';
    $email = $_POST['email'] ?? '';
    $pass = $_POST['password'] ?? '';

    // Basic Validation
    if (empty($restaurantName)) {
        die("Restaurant Name is required.");
    }

    // 2. Generate a safe Database Name
    // Convert "John's Kitchen" -> "vingo_johns_kitchen"
    // Remove non-alphanumeric characters, replace spaces with underscores, lowercase
    $cleanName = preg_replace('/[^a-z0-9\s]/', '', strtolower($restaurantName));
    $dbName = 'vingo_' . preg_replace('/\s+/', '_', $cleanName);

    try {
        // Connect to MySQL Server (no specific DB yet)
        $conn = new PDO("mysql:host=$host", $username, $password);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // 3. Create the Database
        // Use backticks to prevent SQL injection issues with identifiers, though we cleaned it
        $sqlCreateDB = "CREATE DATABASE IF NOT EXISTS `$dbName`";
        $conn->exec($sqlCreateDB);

        // 4. Create Tables in the new Database
        $conn->exec("USE `$dbName`");

        $sqlCreateTable = "
        CREATE TABLE IF NOT EXISTS menu_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            category VARCHAR(100) NOT NULL,
             price DECIMAL(10, 2) NOT NULL,
            availability VARCHAR(50) NOT NULL,
            seasonal BOOLEAN DEFAULT 0,
            image_color VARCHAR(100)
        )";
        $conn->exec($sqlCreateTable);

        $sqlCreateOffers = "
        CREATE TABLE IF NOT EXISTS seasonal_offers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            discount_details VARCHAR(255) NOT NULL,
            is_active BOOLEAN DEFAULT 1,
            valid_until DATE,
            image_color VARCHAR(100)
        )";
        $conn->exec($sqlCreateOffers);

        // Optional: Save the user credentials in the new DB or a master DB
        // For this specific request, we'll Create a 'settings' or 'admin' table in this new DB
        $sqlCreateAdmin = "
        CREATE TABLE IF NOT EXISTS admin_users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            fullname VARCHAR(100),
            email VARCHAR(100),
            password_hash VARCHAR(255),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        $conn->exec($sqlCreateAdmin);

        // Insert the Admin User
        $stmt = $conn->prepare("INSERT INTO admin_users (fullname, email, password_hash) VALUES (:fn, :em, :pw)");
        $stmt->execute([
            ':fn' => $fullname,
            ':em' => $email,
            ':pw' => password_hash($pass, PASSWORD_DEFAULT)
        ]);

        // 6. Register in Master DB (vingo_db)
        $conn->exec("USE `vingo_db`");
        $stmtMaster = $conn->prepare("INSERT INTO restaurants (name, db_name, admin_email) VALUES (:name, :db, :email)");
        $stmtMaster->execute([
            ':name' => $restaurantName,
            ':db' => $dbName,
            ':email' => $email
        ]);

        // 5. Success - Redirect to Dashboard
        header("Location: dashboard.html?msg=account_created&db=$dbName");
        exit();

    } catch (PDOException $e) {
        // Handle Errors
        echo "Error Creating Account/Database: " . $e->getMessage();
        // In production, log this and show a friendly message
    }
}
?>