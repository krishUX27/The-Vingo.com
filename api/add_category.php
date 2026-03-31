<?php
// api/add_category.php — AJAX endpoint: inline category creation
session_start();
require_once __DIR__ . '/../includes/db.php';

$admin_sess_id = $_SESSION['admin_id'] ?? 0;
if (!$admin_sess_id) {
    echo json_encode(['success' => false, 'error' => 'Authentication required.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

$name = trim($_POST['name'] ?? '');

if ($name === '') {
    echo json_encode(['success' => false, 'error' => 'Category name cannot be empty.']);
    exit;
}
if (strlen($name) > 100) {
    echo json_encode(['success' => false, 'error' => 'Category name too long (max 100 chars).']);
    exit;
}

// ── Duplicate check ───────────────────────────────────────────
$chk = $conn->prepare("SELECT id FROM categories WHERE name = ? AND user_id = ?");
$chk->bind_param('si', $name, $admin_sess_id);
$chk->execute();
$chk->store_result();

if ($chk->num_rows > 0) {
    $chk->bind_result($existing_id);
    $chk->fetch();
    $chk->close();
    echo json_encode([
        'success'   => false,
        'duplicate' => true,
        'error'     => "Category '{$name}' already exists.",
        'id'        => $existing_id,
        'name'      => $name,
    ]);
    exit;
}
$chk->close();

// ── Insert ────────────────────────────────────────────────────
$ins = $conn->prepare("INSERT INTO categories (name, user_id) VALUES (?, ?)");
$ins->bind_param('si', $name, $admin_sess_id);

if ($ins->execute()) {
    $new_id = $conn->insert_id;
    $ins->close();
    echo json_encode([
        'success' => true,
        'id'      => $new_id,
        'name'    => $name,
        'message' => "Category '{$name}' added successfully.",
    ]);
} else {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $conn->error]);
}

$conn->close();

