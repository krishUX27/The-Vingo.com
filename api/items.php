<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, GET, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

include_once 'db_connection.php';

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        $query = "SELECT * FROM menu_items";
        $stmt = $conn->prepare($query);
        $stmt->execute();
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($items);
        break;

    case 'POST':
        // Handle Multipart (FormData) or JSON
        $isMultipart = strpos($_SERVER["CONTENT_TYPE"], "multipart/form-data") !== false;
        
        if ($isMultipart) {
            try {
                $name = $_POST['name'] ?? '';
                $category = $_POST['category'] ?? '';
                $price = $_POST['price'] ?? 0;
                $availability = $_POST['availability'] ?? 'Available';
                $seasonal = $_POST['seasonal'] ?? 0;
                $id = $_POST['id'] ?? null; 
                
                $image_url = null;
                if (isset($_FILES['food_image']) && $_FILES['food_image']['error'] == 0) {
                    $image_url = handleUpload($_FILES['food_image']);
                }
                
                if ($id) {
                    $query = "UPDATE menu_items SET name=:name, category=:category, price=:price, availability=:availability" . ($image_url ? ", image_url=:img" : "") . " WHERE id=:id";
                    $stmt = $conn->prepare($query);
                    $stmt->bindParam(":id", $id);
                    $stmt->bindParam(":name", $name);
                    $stmt->bindParam(":category", $category);
                    $stmt->bindParam(":price", $price);
                    $stmt->bindParam(":availability", $availability);
                    if ($image_url) $stmt->bindParam(":img", $image_url);
                    if (!$stmt->execute()) throw new Exception("Update failed");
                    echo json_encode(["success" => true, "message" => "Item updated"]);
                } else {
                    $query = "INSERT INTO menu_items (name, category, price, availability, seasonal, image_url, image_color) VALUES (:name, :category, :price, :availability, :seasonal, :image_url, :image_color)";
                    $stmt = $conn->prepare($query);
                    $img_color = getRandomColor();
                    $stmt->bindParam(":name", $name);
                    $stmt->bindParam(":category", $category);
                    $stmt->bindParam(":price", $price);
                    $stmt->bindParam(":availability", $availability);
                    $stmt->bindParam(":seasonal", $seasonal);
                    $stmt->bindParam(":image_url", $image_url);
                    $stmt->bindParam(":image_color", $img_color);
                    if (!$stmt->execute()) throw new Exception("Insert failed");
                    echo json_encode(["success" => true, "message" => "Item created", "id" => $conn->lastInsertId()]);
                }
            } catch (Exception $e) {
                http_response_code(500);
                echo json_encode(["success" => false, "message" => $e->getMessage()]);
            }
        } else {
            // Original JSON logic
            $data = json_decode(file_get_contents("php://input"));
            if (!empty($data->name) && !empty($data->price)) {
                $query = "INSERT INTO menu_items (name, category, price, availability, seasonal, image_color) VALUES (:name, :category, :price, :availability, :seasonal, :image_color)";
                $stmt = $conn->prepare($query);
                $seasonal = isset($data->seasonal) ? $data->seasonal : 0;
                $image_color = isset($data->image_color) ? $data->image_color : getRandomColor();
                $stmt->bindParam(":name", $data->name);
                $stmt->bindParam(":category", $data->category);
                $stmt->bindParam(":price", $data->price);
                $stmt->bindParam(":availability", $data->availability);
                $stmt->bindParam(":seasonal", $seasonal);
                $stmt->bindParam(":image_color", $image_color);
                $stmt->execute();
                echo json_encode(["message" => "Item created", "id" => $conn->lastInsertId()]);
            }
        }
        break;

    case 'PUT':
        $data = json_decode(file_get_contents("php://input"));

        if (!empty($data->id)) {
            // Build dynamic update query
            $fields = [];
            $params = [':id' => $data->id];

            if (isset($data->name)) {
                $fields[] = "name = :name";
                $params[':name'] = $data->name;
            }
            if (isset($data->category)) {
                $fields[] = "category = :category";
                $params[':category'] = $data->category;
            }
            if (isset($data->price)) {
                $fields[] = "price = :price";
                $params[':price'] = $data->price;
            }
            if (isset($data->availability)) {
                $fields[] = "availability = :availability";
                $params[':availability'] = $data->availability;
            }
            if (isset($data->seasonal)) {
                $fields[] = "seasonal = :seasonal";
                $params[':seasonal'] = $data->seasonal;
            }

            if (!empty($fields)) {
                $query = "UPDATE menu_items SET " . implode(", ", $fields) . " WHERE id = :id";
                $stmt = $conn->prepare($query);

                if ($stmt->execute($params)) {
                    echo json_encode(["message" => "Item updated"]);
                } else {
                    echo json_encode(["message" => "Unable to update item"]);
                }
            }
        }
        break;

    case 'DELETE':
        $data = json_decode(file_get_contents("php://input"));

        if (!empty($data->id)) {
            $query = "DELETE FROM menu_items WHERE id = :id";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(":id", $data->id);

            if ($stmt->execute()) {
                echo json_encode(["message" => "Item deleted"]);
            } else {
                echo json_encode(["message" => "Unable to delete item"]);
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

function handleUpload($file) {
    $itemDir = "../uploads/";
    if (!is_dir($itemDir)) mkdir($itemDir, 0777, true);
    
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $newName = time() . "_" . uniqid() . "." . $ext;
    $targetPath = $itemDir . $newName;
    
    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        return "uploads/" . $newName;
    }
    return null;
}
?>