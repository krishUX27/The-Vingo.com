<?php
header('Content-Type: application/json');

// 1. Auth Check - Reuse the admin auth logic
// We need to simulate the relative path for auth_check.php
require_once __DIR__ . '/../admin/partials/auth_check.php';

// The auth_check.php ensures $_SESSION['admin_logged_in'] is true 
// and provides the $conn object and $check_id.

$admin_id = $_SESSION['admin_id'] ?? 0;

if ($admin_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized access.']);
    exit;
}

try {
    // 2. Fetch the QR Scan Count for this specific admin
    // This ensures isolation as requested.
    $stmt = $conn->prepare("SELECT COUNT(*) AS total FROM qr_scan_logs WHERE admin_id = ?");
    $stmt->bind_param('i', $admin_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $data = $result->fetch_assoc();
    $stmt->close();

    $qr_scan_count = (int)($data['total'] ?? 0);

    echo json_encode([
        'success' => true,
        'qr_scan_count' => $qr_scan_count,
        'timestamp' => time()
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Server error occurred.'
    ]);
}
?>
