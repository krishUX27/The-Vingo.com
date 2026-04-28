<?php
// api/fetch_dishes.php — Live menu JSON endpoint (grouped by category)
header('Content-Type: application/json');
header('Cache-Control: no-cache');

require_once __DIR__ . '/../includes/db.php';

$user_id = intval($_GET['user_id'] ?? 0);
if (!$user_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Menu ID is required.']);
    exit;
}

$sql = "SELECT d.id,
               d.name        AS dish_name,
               d.price,
               d.image,
               d.veg_type,
               d.currency,
               c.id          AS category_id,
               c.name        AS category_name,
               o.title       AS offer_title,
               CONCAT(o.discount_percentage, '%') AS offer_discount,
               d.available_breakfast,
               d.available_lunch,
               d.available_dinner
        FROM   dishes     d
        JOIN   categories c ON c.id = d.category_id
        LEFT JOIN offers o ON o.id = d.offer_id AND o.offer_type = 'seasonal' AND o.status = 'active' AND CURRENT_DATE BETWEEN o.start_date AND o.end_date
        WHERE  d.user_id = ? AND d.is_deleted = 0
" . (isset($_GET['veg_type']) && in_array($_GET['veg_type'], ['veg','non_veg']) ? " AND d.veg_type = '" . $conn->real_escape_string($_GET['veg_type']) . "'" : "") . "
        ORDER  BY d.display_order ASC, d.name ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();

if (!$result) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $conn->error]);
    exit;
}

// ── Group by category ─────────────────────────────────────────
$grouped = [];

while ($row = $result->fetch_assoc()) {
    $cat = $row['category_name'];
    if (!isset($grouped[$cat])) {
        $grouped[$cat] = [];
    }
    $grouped[$cat][] = [
        'id'             => (int) $row['id'],
        'name'           => $row['dish_name'],
        'price'          => (float) $row['price'],
        'currency'       => $row['currency'],
        'image'          => $row['image'],
        'veg_type'       => $row['veg_type'],
        'category'       => $cat,
        'offer_title'    => $row['offer_title'],
        'offer_discount' => $row['offer_discount'],
        'available_breakfast' => (int)$row['available_breakfast'],
        'available_lunch'     => (int)$row['available_lunch'],
        'available_dinner'    => (int)$row['available_dinner'],
    ];
}

echo json_encode([
    'success' => true,
    'data'    => $grouped,
    'ts'      => time(),
]);

$conn->close();

