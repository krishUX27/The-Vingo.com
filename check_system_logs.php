<?php
require_once __DIR__ . '/includes/db.php';

$res = $conn->query("SELECT * FROM system_logs WHERE source = 'MenuImport' ORDER BY id DESC LIMIT 10");
if ($res && $res->num_rows > 0) {
    while ($row = $res->fetch_assoc()) {
        echo "[{$row['status']}] {$row['event']} ({$row['source']})\n";
    }
} else {
    echo "No MenuImport logs found in system_logs table.\n";
}
?>
