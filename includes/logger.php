<?php
/**
 * includes/logger.php
 * Unified logging system for the Vingo Platform.
 */

require_once __DIR__ . '/db.php';

function platform_log($event, $source = 'System', $status = 'OK') {
    global $conn;
    
    // Ensure connection is valid
    if (!$conn || $conn->connect_error) return false;

    $stmt = $conn->prepare("INSERT INTO system_logs (event, source, status) VALUES (?, ?, ?)");
    if ($stmt) {
        $stmt->bind_param('sss', $event, $source, $status);
        $res = $stmt->execute();
        $stmt->close();
        return $res;
    }
    return false;
}
