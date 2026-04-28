<?php
require_once __DIR__ . '/includes/db.php';

// Fix any categories that might have been accidentally marked as deleted or have NULL status
$conn->query("UPDATE categories SET is_deleted = 0 WHERE is_deleted IS NULL");

// Debug: Check what's actually in the DB for the current user
$user_id = 10; // We see Le-Arabia, which is likely ID 10 based on logs
$res = $conn->query("SELECT COUNT(*) FROM dishes WHERE user_id = $user_id AND is_deleted = 0");
$dish_count = ($res) ? $res->fetch_row()[0] : 0;

$res2 = $conn->query("SELECT COUNT(*) FROM categories WHERE user_id = $user_id AND is_deleted = 0");
$cat_count = ($res2) ? $res2->fetch_row()[0] : 0;

echo "User ID: $user_id\n";
echo "Active Dishes: $dish_count\n";
echo "Active Categories: $cat_count\n";

// Check if dishes are linked to deleted categories
$res3 = $conn->query("SELECT d.name, c.name as cat_name, c.is_deleted FROM dishes d JOIN categories c ON d.category_id = c.id WHERE d.user_id = $user_id LIMIT 5");
if ($res3) {
    while($row = $res3->fetch_assoc()) {
        echo "Dish: {$row['name']} | Cat: {$row['cat_name']} | Cat Deleted: {$row['is_deleted']}\n";
    }
}
?>
