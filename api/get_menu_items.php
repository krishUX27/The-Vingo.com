<?php
// api/get_menu_items.php
header("Content-Type: application/json");
require_once '../includes/functions.php';

$category = $_GET['category'] ?? 'All';
$availability = $_GET['availability'] ?? 'All';

try {
    $items = getAllMenuItems($category, $availability);
    echo json_encode(["success" => true, "data" => $items]);
} catch (Exception $e) {
    echo json_encode(["success" => false, "message" => $e->getMessage()]);
}
?>
