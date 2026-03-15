<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

// Use default connection to vingo_db first to find the user's DB
$host = "localhost";
$username = "root";
$password = "";
$db_name = "vingo_db";

try {
    $conn = new PDO("mysql:host=$host;dbname=$db_name", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $data = json_decode(file_get_contents("php://input"));

    if (!empty($data->email) && !empty($data->password)) {
        // 1. Find user's database
        $query = "SELECT db_name FROM restaurants WHERE admin_email = :email";
        $stmt = $conn->prepare($query);
        $stmt->bindParam(":email", $data->email);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $userDb = $row['db_name'];

            // 2. Connect to User's Database
            $userConn = new PDO("mysql:host=$host;dbname=$userDb", $username, $password);

            // 3. Verify Password
            $checkQuery = "SELECT * FROM admin_users WHERE email = :email";
            $checkStmt = $userConn->prepare($checkQuery);
            $checkStmt->bindParam(":email", $data->email);
            $checkStmt->execute();

            if ($checkStmt->rowCount() > 0) {
                $user = $checkStmt->fetch(PDO::FETCH_ASSOC);
                if (password_verify($data->password, $user['password_hash'])) {
                    echo json_encode([
                        "message" => "Login successful",
                        "db" => $userDb,
                        "user" => [
                            "fullname" => $user['fullname'],
                            "email" => $user['email']
                        ]
                    ]);
                } else {
                    http_response_code(401);
                    echo json_encode(["message" => "Invalid password"]);
                }
            } else {
                http_response_code(404);
                echo json_encode(["message" => "User record missing in DB"]); // Should not happen if integrity maintained
            }

        } else {
            http_response_code(404);
            echo json_encode(["message" => "User not found"]);
        }
    } else {
        http_response_code(400);
        echo json_encode(["message" => "Incomplete data"]);
    }

} catch (PDOException $e) {
    http_response_code(503);
    echo json_encode(["message" => "Connection error: " . $e->getMessage()]);
}
?>