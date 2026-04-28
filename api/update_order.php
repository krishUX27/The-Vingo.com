<?php
// api/update_order.php — Admin-only endpoint to save dish display_order
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store');

// ── Session & Auth ────────────────────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) session_start();

if (
    !isset($_SESSION['admin_logged_in'])  ||
    $_SESSION['admin_logged_in'] !== true ||
    empty($_SESSION['admin_id'])
) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized. Admin session required.']);
    exit;
}

$admin_id = (int) $_SESSION['admin_id'];

// ── Database ──────────────────────────────────────────────────────────────────
require_once __DIR__ . '/../includes/db.php';

// ── Read & Validate Input ─────────────────────────────────────────────────────
$raw = file_get_contents('php://input');
$body = json_decode($raw, true);

if (!isset($body['order']) || !is_array($body['order']) || empty($body['order'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid or empty order payload.']);
    exit;
}

// order is an array of dish IDs in the desired sequence
$order = array_values($body['order']);

// Validate each element is a positive integer
foreach ($order as $item) {
    if (!is_int($item) && !ctype_digit((string)$item)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid dish ID in order array.']);
        exit;
    }
}

// ── Verify Ownership & Apply Order ───────────────────────────────────────────
// We update only dishes that belong to the authenticated admin.
$stmt = $conn->prepare(
    "UPDATE dishes SET display_order = ? WHERE id = ? AND user_id = ? AND is_deleted = 0"
);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'DB prepare error: ' . $conn->error]);
    exit;
}

$conn->begin_transaction();

try {
    foreach ($order as $position => $dish_id) {
        $pos    = (int) $position;
        $d_id   = (int) $dish_id;
        $stmt->bind_param('iii', $pos, $d_id, $admin_id);
        $stmt->execute();
    }
    $conn->commit();
    $stmt->close();
    echo json_encode(['success' => true, 'message' => 'Order saved successfully.', 'count' => count($order)]);
} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
