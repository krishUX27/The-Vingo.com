<?php
// admin/delete-item.php — Delete a dish
require_once __DIR__ . '/../includes/db.php';
session_start();

$id = intval($_GET['id'] ?? 0);
if (!$id) { header('Location: dashboard.php'); exit; }

$admin_sess_id = $_SESSION['admin_id'] ?? 0;

$s = $conn->prepare("SELECT name, image FROM dishes WHERE id = ? AND user_id = ?");
$s->bind_param('ii', $id, $admin_sess_id);
$s->execute();
$dish = $s->get_result()->fetch_assoc();
$s->close();

if (!$dish) {
    $_SESSION['flash'] = ['type' => 'danger', 'msg' => 'Dish not found.'];
    header('Location: dashboard.php');
    exit;
}

// Delete image file
if ($dish['image'] && file_exists(__DIR__ . '/../uploads/' . $dish['image'])) {
    unlink(__DIR__ . '/../uploads/' . $dish['image']);
}

// Delete record
$del = $conn->prepare("DELETE FROM dishes WHERE id = ? AND user_id = ?");
$del->bind_param('ii', $id, $admin_sess_id);
$del->execute();
$del->close();

$_SESSION['flash'] = ['type' => 'success', 'msg' => "Dish '{$dish['name']}' deleted."];
header('Location: dashboard.php');
exit;

