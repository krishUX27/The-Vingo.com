<?php
// api/get_offers.php — Fetch all active seasonal and combo offers
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/db.php';

$user_id = intval($_GET['user_id'] ?? 0);
if (!$user_id) {
    echo json_encode(['success' => false, 'message' => 'User ID required']);
    exit;
}

try {
    // Fetch only active, valid, and non-deleted offers
    $sql = "SELECT * FROM offers 
            WHERE user_id = ? 
            AND status = 'active' 
            AND is_deleted = 0 
            AND start_date <= CURRENT_DATE 
            AND end_date >= CURRENT_DATE
            ORDER BY created_at DESC";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $user_id);
    $stmt->execute();
    $offers = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // For combo offers, fetch the included dish names
    foreach ($offers as &$o) {
        if ($o['offer_type'] === 'combo') {
            $oid = $o['id'];
            $res = $conn->query("SELECT d.name FROM offer_combo_dishes ocd 
                                 JOIN dishes d ON d.id = ocd.dish_id 
                                 WHERE ocd.offer_id = $oid");
            $items = [];
            while($r = $res->fetch_assoc()) $items[] = $r['name'];
            $o['combo_items'] = $items;
        }
    }

    echo json_encode(['success' => true, 'data' => $offers]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
