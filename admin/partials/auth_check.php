<?php
// admin/partials/auth_check.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Initial Session Check
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: index.php');
    exit;
}

// 2. Real-time Database Check (Ensures account hasn't been deleted by Super Admin)
require_once __DIR__ . '/../../includes/db.php';

$check_id = (int)($_SESSION['admin_id'] ?? 0);
$stmt = $conn->prepare("SELECT id, is_active, status FROM users WHERE id = ? AND role = 'admin' LIMIT 1");
$stmt->bind_param('i', $check_id);
$stmt->execute();
$res = $stmt->get_result();
$user = $res->fetch_assoc();

if (!$user || $user['is_active'] != 1 || ($user['status'] ?? 'active') === 'hold') {
    // Account deactivated or on hold
    $msg = ($user && $user['status'] === 'hold') ? 'on_hold' : 'account_disabled';
    session_unset();
    session_destroy();
    header('Location: index.php?msg=' . $msg);
    exit;
}
$stmt->close();
