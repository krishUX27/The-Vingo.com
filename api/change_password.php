<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// db_connection.php normally connects to vingo_db or dynamic db if generic. 
// But here we want to connect to a specific DB passed in the body or params.
// Let's do a custom connection to ensure we control the logic.

$host = "localhost";
$username = "root";
$password = "";

$data = json_decode(file_get_contents("php://input"));

if (
    !empty($data->db) &&
    !empty($data->email) &&
    !empty($data->current_password) &&
    !empty($data->new_password)
) {
    // Sanitize DB name for safety (basic check)
    if (!preg_match('/^vingo_[a-z0-9_]+$/', $data->db)) {
        http_response_code(400);
        echo json_encode(["message" => "Invalid database name format"]);
        exit;
    }

    try {
        // Connect to the specific user database
        $conn = new PDO("mysql:host=$host;dbname=" . $data->db, $username, $password);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        // 1. Verify Current Password
        $query = "SELECT * FROM admin_users WHERE email = :email";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(":email", $data->email);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (password_verify($data->current_password, $user['password_hash'])) {
                // 2. Update Password
                $newHash = password_hash($data->new_password, PASSWORD_DEFAULT);

                $updateQuery = "UPDATE admin_users SET password_hash = :pw WHERE email = :email";
                $updateStmt = $conn->prepare($updateQuery);
                $updateStmt->bindParam(":pw", $newHash);
                $updateStmt->bindParam(":email", $data->email);

                if ($updateStmt->execute()) {
                    echo json_encode(["message" => "Password updated successfully"]);
                } else {
                    http_response_code(500);
                    echo json_encode(["message" => "Failed to update password"]);
                }

            } else {
                http_response_code(401);
                echo json_encode(["message" => "Incorrect current password"]);
            }
        } else {
            http_response_code(404);
            echo json_encode(["message" => "User not found"]);
        }

    } catch (PDOException $e) {
        http_response_code(503);
        echo json_encode(["message" => "Connection error: " . $e->getMessage()]);
    }
} else {
    http_response_code(400);
    echo json_encode(["message" => "Incomplete data"]);
}
?>