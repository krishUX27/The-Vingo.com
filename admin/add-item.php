<?php
// admin/add-item.php
require_once '../includes/functions.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = $_POST['name'] ?? '';
    $category = $_POST['category'] ?? '';
    $price = $_POST['price'] ?? 0;
    $availability = $_POST['availability'] ?? 'Available';
    $seasonal = isset($_POST['seasonal']) ? 1 : 0;
    
    // Validate inputs
    if (empty($name) || empty($category) || empty($price)) {
        die("Please fill in all required fields.");
    }

    // Handle Image Upload
    if (isset($_FILES['food_image']) && $_FILES['food_image']['error'] == 0) {
        $uploadResult = handleImageUpload($_FILES['food_image']);
        
        if (is_array($uploadResult) && isset($uploadResult['error'])) {
            die("Upload Error: " . $uploadResult['error']);
        }
        
        $image_url = $uploadResult;
    } else {
        $image_url = 'assets/placeholder-food.jpg'; // Default if none uploaded
    }

    // Add to Database
    if (addMenuItem($name, $category, $price, $availability, $seasonal, $image_url)) {
        header("Location: menu-items.php?msg=item_added");
        exit();
    } else {
        die("Error adding menu item.");
    }
} else {
    header("Location: menu-items.php");
    exit();
}
?>
