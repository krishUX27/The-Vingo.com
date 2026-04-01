<?php
// superadmin/impersonate.php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../includes/db.php';

$id = intval($_GET['id'] ?? 0);
$target = $_GET['target'] ?? 'console';

if ($id > 0) {
    $stmt = $conn->prepare("SELECT id, username FROM users WHERE id = ? AND role = 'admin' LIMIT 1");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result();
    
    if ($user = $res->fetch_assoc()) {
        // Set Admin Session Variables
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_id']       = $user['id'];
        $_SESSION['admin_username'] = $user['username'];
        
        // Determine Redirect URL
        $redirect = '../admin/dashboard.php';
        if ($target === 'menu') {
            $redirect = '../menu.php?id=' . $user['id'];
        } elseif ($target === 'qr') {
            $redirect = '../admin/qr.php';
        }
        
        header('Location: ' . $redirect);
        exit;
    }
}

// Fallback
header('Location: index.php?error=invalid_impersonation');
exit;
