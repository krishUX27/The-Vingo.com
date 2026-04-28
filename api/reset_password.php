<?php
session_start();
require_once __DIR__ . '/../includes/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method.']);
    exit;
}

// Security Check: Must have verified OTP
if (!isset($_SESSION['otp_verified']) || $_SESSION['otp_verified'] !== true || !isset($_SESSION['reset_email'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized. Please verify OTP first.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$new_pass = $input['password'] ?? '';
$confirm_pass = $input['confirm_password'] ?? '';
$email = $_SESSION['reset_email'];

if (strlen($new_pass) < 6) {
    echo json_encode(['success' => false, 'error' => 'Password must be at least 6 characters long.']);
    exit;
}

if ($new_pass !== $confirm_pass) {
    echo json_encode(['success' => false, 'error' => 'Passwords do not match.']);
    exit;
}

// 1. Hash the new password
$hashed = password_hash($new_pass, PASSWORD_DEFAULT);

// 2. Update the user table
$stmt = $conn->prepare("UPDATE users SET password = ? WHERE email = ? AND role = 'admin'");
$stmt->bind_param('ss', $hashed, $email);

if ($stmt->execute()) {
    // 3. Clear session
    unset($_SESSION['otp_verified']);
    unset($_SESSION['reset_email']);
    
    echo json_encode(['success' => true, 'message' => 'Password reset successful. You can now login.']);
} else {
    echo json_encode(['success' => false, 'error' => 'Database error. Failed to update password.']);
}
