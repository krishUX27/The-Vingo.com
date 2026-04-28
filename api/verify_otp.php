<?php
session_start();
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$email = trim($input['email'] ?? '');
$otp   = trim($input['otp'] ?? '');

if (empty($email) || empty($otp)) {
    echo json_encode(['success' => false, 'error' => 'Email and OTP are required.']);
    exit;
}

// 1. Verify OTP
$stmt = $conn->prepare("SELECT id FROM password_resets WHERE email = ? AND otp = ? AND is_used = 0 AND expires_at > NOW() ORDER BY created_at DESC LIMIT 1");
$stmt->bind_param('ss', $email, $otp);
$stmt->execute();
$res = $stmt->get_result();

if ($res->num_rows > 0) {
    $row = $res->fetch_assoc();
    $reset_id = $row['id'];

    // 2. Mark OTP as used
    $upd = $conn->prepare("UPDATE password_resets SET is_used = 1 WHERE id = ?");
    $upd->bind_param('i', $reset_id);
    $upd->execute();

    // 3. Create verified session
    $_SESSION['otp_verified'] = true;
    $_SESSION['reset_email']  = $email;

    echo json_encode(['success' => true, 'message' => 'OTP verified successfully.']);
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid or expired OTP.']);
}
