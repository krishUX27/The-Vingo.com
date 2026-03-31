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
               d.availability,
               d.currency,
               c.id          AS category_id,
               c.name        AS category_name,
               o.title       AS offer_title,
               o.discount    AS offer_discount
        FROM   dishes     d
        JOIN   categories c ON c.id = d.category_id
        LEFT JOIN seasonal_offers o ON o.id = d.offer_id
        WHERE  d.user_id = ?
        ORDER  BY c.name, d.name";

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
        'availability'   => $row['availability'],
        'category'       => $cat,
        'offer_title'    => $row['offer_title'],
        'offer_discount' => $row['offer_discount'],
    ];
}

echo json_encode([
    'success' => true,
    'data'    => $grouped,
    'ts'      => time(),
]);

$conn->close();

