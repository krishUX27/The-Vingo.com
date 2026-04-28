<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/mail_helper.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$email = trim($input['email'] ?? '');

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'error' => 'Please enter a valid email address.']);
    exit;
}

// 1. Check if email exists in users table (Admin role)
$stmt = $conn->prepare("SELECT id, username FROM users WHERE email = ? AND role = 'admin' AND is_deleted = 0 LIMIT 1");
$stmt->bind_param('s', $email);
$stmt->execute();
$res = $stmt->get_result();
$user = $res->fetch_assoc();

if (!$user) {
    // For security, don't reveal if email exists or not. 
    // But usually in internal tools, it's okay. I'll follow the objective: "Validate email exists"
    echo json_encode(['success' => false, 'error' => 'Account with this email not found.']);
    exit;
}

// 2. Rate limiting (prevent spam)
$check_spam = $conn->prepare("SELECT id FROM password_resets WHERE email = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE)");
$check_spam->bind_param('s', $email);
$check_spam->execute();
if ($check_spam->get_result()->num_rows > 0) {
    echo json_encode(['success' => false, 'error' => 'Please wait 60 seconds before requesting another OTP.']);
    exit;
}

// 3. Generate 6-digit OTP
$otp = (string)rand(100000, 999999);
$expires_at = date("Y-m-d H:i:s", strtotime("+5 minutes"));

// 4. Store in DB
$ins = $conn->prepare("INSERT INTO password_resets (email, otp, expires_at) VALUES (?, ?, ?)");
$ins->bind_param('sss', $email, $otp, $expires_at);

if ($ins->execute()) {
    // 5. Send Email
    if (sendOTPEmail($email, $user['username'], $otp)) {
        echo json_encode(['success' => true, 'message' => 'OTP sent to your email.']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to send email. Please try again later.']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Database error.']);
}
