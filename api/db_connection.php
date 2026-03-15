<?php
$host = '127.0.0.1';
$username = 'root';
$password = '';

// Dynamic DB Selection
$db_name = 'vingo_db'; // Default

if (isset($_GET['db']) && !empty($_GET['db'])) {
    $requested_db = $_GET['db'];
} elseif (isset($_POST['db']) && !empty($_POST['db'])) {
    $requested_db = $_POST['db'];
} else {
    // Check if JSON input has db
    $json_input = json_decode(file_get_contents("php://input"), true);
    if (isset($json_input['db'])) {
        $requested_db = $json_input['db'];
    }
}

if (isset($requested_db)) {
    // Sanitize: allow only alphanumeric and underscores, must start with vingo_
    if (preg_match('/^vingo_[a-z0-9_]+$/', $requested_db)) {
        $db_name = $requested_db;
    }
}

try {
    $conn = new PDO("mysql:host=$host;dbname=$db_name", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    // Return JSON error if API request
    if (strpos($_SERVER['REQUEST_URI'], 'api/') !== false) {
        header("Content-Type: application/json");
        echo json_encode(["error" => "Connection error: " . $e->getMessage()]);
        exit;
    } else {
        echo "Connection error: " . $e->getMessage();
    }
}
?>