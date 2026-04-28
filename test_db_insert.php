<?php
require_once __DIR__ . '/../includes/db.php';

$admin_id = 1; // Assuming admin ID 1 for test
$cat_id = 0;
$name = 'Test Dish';
$price = 100.00;
$veg_type = 'veg';
$avail_b = 1; $avail_l = 1; $avail_d = 1;
$image = '';
$currency = 'INR';

$stmt = $conn->prepare("INSERT INTO dishes (user_id, category_id, name, price, veg_type, available_breakfast, available_lunch, available_dinner, image, currency) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}

$stmt->bind_param("iisdsiiiss", $admin_id, $cat_id, $name, $price, $veg_type, $avail_b, $avail_l, $avail_d, $image, $currency);
if ($stmt->execute()) {
    echo "Insert Successful! New ID: " . $conn->insert_id;
} else {
    echo "Insert Failed: " . $stmt->error;
}
$stmt->close();
?>
