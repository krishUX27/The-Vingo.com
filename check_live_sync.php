<?php
require_once __DIR__ . '/includes/db.php';

$res = $conn->query("SELECT d.id, d.user_id, d.category_id, d.name, c.name as cat_name, c.is_deleted as cat_deleted 
                    FROM dishes d 
                    JOIN categories c ON c.id = d.category_id 
                    ORDER BY d.id DESC LIMIT 5");
if ($res && $res->num_rows > 0) {
    while ($row = $res->fetch_assoc()) {
        echo "Dish ID: {$row['id']}, User ID: {$row['user_id']}, Cat: {$row['cat_name']} (Deleted: {$row['cat_deleted']})\n";
    }
} else {
    echo "No dishes found in database.\n";
}
?>
