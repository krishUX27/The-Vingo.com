<?php
require_once 'includes/db.php';
$res = $conn->query('DESCRIBE dishes');
while($row = $res->fetch_assoc()) {
    echo $row['Field'] . ' (' . $row['Type'] . ')' . PHP_EOL;
}
