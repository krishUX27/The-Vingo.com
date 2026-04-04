<?php
// admin/delete-category.php — Soft Delete a category (Redirect back to add-category.php)
// Since categories don't have is_deleted column (they do but we usually check if dishes exist)
require_once __DIR__ . '/partials/auth_check.php';
require_once __DIR__ . '/../includes/db.php';

$id = intval($_GET['id'] ?? 0);
if (!$id) { header('Location: add-category.php'); exit; }

$admin_sess_id = (int)($_SESSION['admin_id'] ?? 0);

// Check if dishes belong to this category (Isolation check)
$chk = $conn->prepare("SELECT COUNT(*) FROM dishes WHERE category_id = ? AND user_id = ? AND is_deleted = 0");
$chk->bind_param('ii', $id, $admin_sess_id);
$chk->execute();
$cnt = $chk->get_result()->fetch_row()[0];
$chk->close();

if ($cnt > 0) {
    $_SESSION['flash'] = ['type' => 'danger', 'msg' => "Cannot delete — {$cnt} dish(es) belong to this category."];
} else {
    // Soft Delete category
    $del = $conn->prepare("UPDATE categories SET is_deleted = 1, deleted_at = NOW() WHERE id = ? AND user_id = ?");
    $del->bind_param('ii', $id, $admin_sess_id);
    if ($del->execute()) {
        $_SESSION['flash'] = ['type' => 'success', 'msg' => 'Category deleted.'];
    } else {
        $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'System error during deletion.'];
    }
    $del->close();
}

header('Location: add-category.php');
exit;
