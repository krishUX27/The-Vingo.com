<?php
require_once __DIR__ . '/includes/db.php';
header('Content-Type: text/plain');

echo "--- Settings Table Diagnosis ---\n";

$res = $conn->query("SHOW CREATE TABLE settings");
if ($res) {
    $row = $res->fetch_row();
    echo "Table Schema:\n" . $row[1] . "\n\n";
} else {
    echo "Error fetching schema: " . $conn->error . "\n\n";
}

$res = $conn->query("SELECT * FROM settings ORDER BY id DESC LIMIT 20");
echo "Last 20 Settings Entries:\n";
printf("%-5s | %-8s | %-20s | %s\n", "ID", "User", "Key", "Value");
echo str_repeat("-", 60) . "\n";
while ($row = $res->fetch_assoc()) {
    printf("%-5d | %-8d | %-20s | %s\n", $row['id'], $row['user_id'], $row['setting_key'], $row['setting_value']);
}

echo "\n--- End of Report ---";
