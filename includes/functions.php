<?php
// functions.php
require_once 'db.php';

// Fetch all menu items
function getAllMenuItems($category = null, $availability = null) {
    global $pdo;
    $sql = "SELECT * FROM menu_items WHERE 1=1";
    $params = [];

    if ($category && $category != 'All') {
        $sql .= " AND category = ?";
        $params[] = $category;
    }

    if ($availability && $availability != 'All') {
        $sql .= " AND availability = ?";
        $params[] = $availability;
    }

    $sql .= " ORDER BY created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

// Add a menu item
function addMenuItem($name, $category, $price, $availability, $seasonal, $image_url) {
    global $pdo;
    $sql = "INSERT INTO menu_items (name, category, price, availability, seasonal, image_url) 
            VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    return $stmt->execute([$name, $category, $price, $availability, $seasonal, $image_url]);
}

// Handle image upload and return the path
function handleImageUpload($file) {
    $targetDir = "../uploads/";
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0777, true);
    }

    $fileName = basename($file["name"]);
    $imageFileType = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    
    // Check if image file is an actual image
    $check = getimagesize($file["tmp_name"]);
    if($check === false) return ["error" => "File is not an image."];

    // Check file size (max 5MB)
    if ($file["size"] > 5000000) return ["error" => "File is too large (max 5MB)."];

    // Allow certain file formats
    if(!in_array($imageFileType, ["jpg", "png", "jpeg"])) {
        return ["error" => "Only JPG, PNG & JPEG files are allowed."];
    }

    // Generate unique name
    $newFileName = time() . "_" . uniqid() . "." . $imageFileType;
    $targetFilePath = $targetDir . $newFileName;

    if (move_uploaded_file($file["tmp_name"], $targetFilePath)) {
        return "uploads/" . $newFileName; // Return path to store in DB
    } else {
        return ["error" => "Error uploading file."];
    }
}
?>
