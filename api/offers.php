<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, GET, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

include_once 'db_connection.php';

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        $query = "SELECT * FROM seasonal_offers";
        try {
            $stmt = $conn->prepare($query);
            $stmt->execute();
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode($items);
        } catch (PDOException $e) {
            echo json_encode(["message" => "Error fetching offers: " . $e->getMessage()]);
        }
        break;

    case 'POST':
        $data = json_decode(file_get_contents("php://input"));

        if (!empty($data->name) && !empty($data->discount_details)) {
            $query = "INSERT INTO seasonal_offers (name, discount_details, is_active, valid_until, image_color) VALUES (:name, :discount, :active, :valid, :color)";
            $stmt = $conn->prepare($query);

            $isActive = isset($data->is_active) ? $data->is_active : 1;
            $validUntil = isset($data->valid_until) ? $data->valid_until : null;
            $imageColor = isset($data->image_color) ? $data->image_color : getRandomColor();

            $stmt->bindParam(":name", $data->name);
            $stmt->bindParam(":discount", $data->discount_details);
            $stmt->bindParam(":active", $isActive);
            $stmt->bindParam(":valid", $validUntil);
            $stmt->bindParam(":color", $imageColor);

            if ($stmt->execute()) {
                $id = $conn->lastInsertId();
                echo json_encode(["message" => "Offer created", "id" => $id, "image_color" => $imageColor]);
            } else {
                echo json_encode(["message" => "Unable to create offer"]);
            }
        }
        break;

    case 'PUT':
        $data = json_decode(file_get_contents("php://input"));

        if (!empty($data->id)) {
            $fields = [];
            $params = [':id' => $data->id];

            if (isset($data->name)) {
                $fields[] = "name = :name";
                $params[':name'] = $data->name;
            }
            if (isset($data->discount_details)) {
                $fields[] = "discount_details = :discount";
                $params[':discount'] = $data->discount_details;
            }
            if (isset($data->is_active)) {
                $fields[] = "is_active = :active";
                $params[':active'] = $data->is_active;
            }
            if (isset($data->valid_until)) {
                $fields[] = "valid_until = :valid";
                $params[':valid'] = $data->valid_until;
            }

            if (!empty($fields)) {
                $query = "UPDATE seasonal_offers SET " . implode(", ", $fields) . " WHERE id = :id";
                $stmt = $conn->prepare($query);

                if ($stmt->execute($params)) {
                    echo json_encode(["message" => "Offer updated"]);
                } else {
                    echo json_encode(["message" => "Unable to update offer"]);
                }
            }
        }
        break;

    case 'DELETE':
        $data = json_decode(file_get_contents("php://input"));

        if (!empty($data->id)) {
            $query = "DELETE FROM seasonal_offers WHERE id = :id";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(":id", $data->id);

            if ($stmt->execute()) {
                echo json_encode(["message" => "Offer deleted"]);
            } else {
                echo json_encode(["message" => "Unable to delete offer"]);
            }
        }
        break;
}

function getRandomColor()
{
    $colors = [
        "linear-gradient(45deg, #1dd1a1, #10ac84)",
        "linear-gradient(45deg, #5f27cd, #341f97)",
        "linear-gradient(45deg, #ff9f43, #ee5253)",
        "linear-gradient(45deg, #00d2d3, #2e86de)"
    ];
    return $colors[array_rand($colors)];
}
?>