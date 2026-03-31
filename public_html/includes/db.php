<?php
// includes/db.php
// Menu Manager database connection (mysqli)
// Separate from the existing vingo_menu_db connection.

define('MENU_DB_HOST', 'localhost');
define('MENU_DB_USER', 'root');        // Change for production
define('MENU_DB_PASS', '');            // Change for production
define('MENU_DB_NAME', 'menu_project');

$conn = new mysqli(MENU_DB_HOST, MENU_DB_USER, MENU_DB_PASS, MENU_DB_NAME);

if ($conn->connect_error) {
    // For AJAX endpoints, return JSON; for pages, return readable error
    $is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
               || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'));

    if ($is_ajax) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'DB connection failed: ' . $conn->connect_error]);
        exit;
    }
    die('<p style="font-family:sans-serif;color:red;padding:20px">
         Database connection failed: ' . htmlspecialchars($conn->connect_error) . '<br>
         Make sure XAMPP MySQL is running and database <strong>menu_project</strong> exists.
         </p>');
}

$conn->set_charset('utf8mb4');

/** ── Helper: Get setting from DB (Supports Private Mode) ── */
function menu_get_setting($key, $default = '', $user_id = 0) {
    global $conn;
    $key = $conn->real_escape_string($key);
    $where = "WHERE setting_key = '$key'";
    if ($user_id > 0) $where .= " AND user_id = $user_id";
    
    $res = $conn->query("SELECT setting_value FROM settings $where LIMIT 1");
    if ($res && $row = $res->fetch_assoc()) return $row['setting_value'];
    return $default;
}
?>

