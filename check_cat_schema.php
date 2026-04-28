<?php
require_once __DIR__ . '/includes/db.php';

$res = $conn->query("SHOW CREATE TABLE categories");
if ($res) {
    $row = $res->fetch_assoc();
    echo $row['Create Table'];
} else {
    echo "Error: " . $conn->error;
}
?>
