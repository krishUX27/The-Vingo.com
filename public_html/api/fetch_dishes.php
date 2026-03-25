<?php
// api/fetch_dishes.php — Live menu JSON endpoint (grouped by category)
header('Content-Type: application/json');
header('Cache-Control: no-cache');

require_once __DIR__ . '/../includes/db.php';

$sql = "SELECT d.id,
               d.name        AS dish_name,
               d.price,
               d.image,
               d.availability,
               c.id          AS category_id,
               c.name        AS category_name
        FROM   dishes     d
        JOIN   categories c ON c.id = d.category_id
        ORDER  BY c.name, d.name";

$result = $conn->query($sql);

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
        'id'           => (int) $row['id'],
        'name'         => $row['dish_name'],
        'price'        => (float) $row['price'],
        'image'        => $row['image'],          // filename only; client prepends uploads path
        'availability' => $row['availability'],
        'category'     => $cat,
    ];
}

echo json_encode([
    'success' => true,
    'data'    => $grouped,
    'ts'      => time(),
]);

$conn->close();

