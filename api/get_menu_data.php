<?php
// api/get_menu_data.php — Unified live menu endpoint for dishes and all offer types
header('Content-Type: application/json');
header('Cache-Control: no-cache');
require_once __DIR__ . '/../includes/db.php';

$user_id = intval($_GET['user_id'] ?? 0);
if (!$user_id) {
    echo json_encode(['success' => false, 'message' => 'User ID required']);
    exit;
}

try {
    // 1. Fetch Dishes Grouped by Category with Seasonal and Combo info
    $sql_dishes = "SELECT d.*, c.name AS category_name, o.title AS offer_title, 
                          CONCAT(o.discount_percentage, '%') AS offer_discount,
                          (SELECT GROUP_CONCAT(o2.title SEPARATOR ', ') 
                           FROM offer_combo_dishes ocd 
                           JOIN offers o2 ON o2.id = ocd.offer_id 
                           WHERE ocd.dish_id = d.id AND o2.offer_type = 'combo' 
                           AND o2.status = 'active' AND o2.is_deleted = 0
                           AND CURRENT_DATE BETWEEN o2.start_date AND o2.end_date) AS combo_names
                   FROM dishes d
                   JOIN categories c ON c.id = d.category_id
                   LEFT JOIN offers o ON o.id = d.offer_id 
                                     AND o.offer_type = 'seasonal' 
                                     AND o.status = 'active'
                                     AND CURRENT_DATE BETWEEN o.start_date AND o.end_date
                   WHERE d.user_id = ? AND d.is_deleted = 0 AND c.is_deleted = 0
                   ORDER BY c.name, d.name";
    
    $stmt_d = $conn->prepare($sql_dishes);
    $stmt_d->bind_param('i', $user_id);
    $stmt_d->execute();
    $res_d = $stmt_d->get_result();
    
    $grouped_dishes = [];
    while ($row = $res_d->fetch_assoc()) {
        $cat = $row['category_name'];
        if (!isset($grouped_dishes[$cat])) $grouped_dishes[$cat] = [];
        $grouped_dishes[$cat][] = [
            'id'             => (int)$row['id'],
            'name'           => $row['name'],
            'price'          => (float)$row['price'],
            'currency'       => $row['currency'],
            'image'          => $row['image'],
            'veg_type'       => $row['veg_type'],
            'offer_title'    => $row['offer_title'],
            'offer_discount' => $row['offer_discount'],
            'combo_names'    => $row['combo_names'],
            'available_breakfast' => (int)$row['available_breakfast'],
            'available_lunch'     => (int)$row['available_lunch'],
            'available_dinner'    => (int)$row['available_dinner'],
            'category'       => $cat
        ];
    }

    // 2. Fetch All Active Offers (Seasonal & Combo)
    $sql_offers = "SELECT * FROM offers 
                   WHERE user_id = ? 
                   AND status = 'active' 
                   AND is_deleted = 0 
                   AND CURRENT_DATE BETWEEN start_date AND end_date
                   ORDER BY created_at DESC";
                   
    $stmt_o = $conn->prepare($sql_offers);
    $stmt_o->bind_param('i', $user_id);
    $stmt_o->execute();
    $res_o = $stmt_o->get_result();
    
    $all_offers = [];
    while ($o = $res_o->fetch_assoc()) {
        if ($o['offer_type'] === 'combo') {
            $oid = $o['id'];
            $items_res = $conn->query("SELECT d.name FROM offer_combo_dishes ocd 
                                       JOIN dishes d ON d.id = ocd.dish_id 
                                       WHERE ocd.offer_id = $oid AND d.is_deleted = 0");
            $items = [];
            while($ir = $items_res->fetch_assoc()) $items[] = $ir['name'];
            $o['combo_items'] = $items;
        }
        $all_offers[] = $o;
    }

    echo json_encode([
        'success' => true,
        'data' => [
            'dishes' => $grouped_dishes,
            'offers' => $all_offers
        ],
        'ts' => time()
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
