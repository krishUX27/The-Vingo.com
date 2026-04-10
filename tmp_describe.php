<?php
require_once __DIR__ . '/includes/db.php';
$res = $conn->query("DESCRIBE qr_scans");
$data = $res->fetch_all(MYSQLI_ASSOC);
var_export($data);
?>
