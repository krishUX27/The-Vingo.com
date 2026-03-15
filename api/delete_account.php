<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

$host = "localhost";
$username = "root";
$password = "";

$data = json_decode(file_get_contents("php://input"));

if (!empty($data->db) && !empty($data->email) && !empty($data->password)) {

    // Sanitize DB name
    if (!preg_match('/^vingo_[a-z0-9_]+$/', $data->db)) {
        http_response_code(400);
        echo json_encode(["message" => "Invalid database name"]);
        exit;
    }

    try {
        // 1. Verify User Credentials first in their own DB
        $userConn = new PDO("mysql:host=$host;dbname=" . $data->db, $username, $password);
        $userConn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $query = "SELECT * FROM admin_users WHERE email = :email";
        $stmt = $userConn->prepare($query);
        $stmt->bindParam(":email", $data->email);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if (password_verify($data->password, $user['password_hash'])) {

                // CREDENTIALS VALID - PROCEED TO DELETE
                // We need a root connection that isn't bound to the DB being deleted to drop it
                $rootConn = new PDO("mysql:host=$host", $username, $password);
                $rootConn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

                // A. Delete from Master DB (vingo_db)
                $rootConn->exec("USE vingo_db");
                $deleteMaster = $rootConn->prepare("DELETE FROM restaurants WHERE db_name = :db AND admin_email = :email");
                $deleteMaster->bindParam(":db", $data->db);
                $deleteMaster->bindParam(":email", $data->email);
                $deleteMaster->execute();

                // B. Drop User Database
                // WARNING: This is destructive
                $dropSql = "DROP DATABASE IF EXISTS `" . $data->db . "`";
                $rootConn->exec($dropSql);

                echo json_encode(["message" => "Account and data deleted successfully"]);

            } else {
                http_response_code(401);
                echo json_encode(["message" => "Incorrect password"]);
            }
        } else {
            http_response_code(404);
            echo json_encode(["message" => "User not found"]);
        }

    } catch (PDOException $e) {
        http_response_code(503);
        echo json_encode(["message" => "Error: " . $e->getMessage()]);
    }
} else {
    http_response_code(400);
    echo json_encode(["message" => "Incomplete data"]);
}
?>