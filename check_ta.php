<?php
require_once __DIR__ . '/../includes/db.php';

$res = $conn->query("SELECT * FROM dish_translations WHERE language_code = 'ta' LIMIT 5");
if ($res && $res->num_rows > 0) {
    while ($row = $res->fetch_assoc()) {
        echo "Dish ID: {$row['dish_id']}, Name: {$row['name']}, Desc: {$row['description']}\n";
    }
} else {
    echo "No Tamil translations found in database.\n";
}

$res2 = $conn->query("SELECT COUNT(*) FROM dishes WHERE user_id = " . intval($_SESSION['admin_id'] ?? 0));
$count = ($res2) ? $res2->fetch_row()[0] : 0;
echo "Total dishes for current admin: $count\n";
?>
