<?php
// 新規座席追加API
require_once __DIR__ . '/../../../common/config.php';
require_once __DIR__ . '/../../../common/dbconnect.php';
require_once __DIR__ . '/../../../common/functions.php';

header('Content-Type: application/json; charset=UTF-8');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Method not allowed');
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
         throw new Exception('Invalid JSON input');
    }

    $shop_id = $input['shop_id'] ?? 0;
    $name = $input['name'] ?? 'New Seat';
    $x = $input['x'] ?? 50;
    $y = $input['y'] ?? 50;
    $w = $input['w'] ?? 10;
    $h = $input['h'] ?? 10;
    $type = $input['type'] ?? 'rect';

    if (empty($shop_id)) {
        throw new Exception('Shop ID required');
    }

    $pdo = connect();
    // 最大のdisplay_orderを取得
    $stmt = $pdo->prepare("SELECT MAX(display_order) FROM sheet_mst WHERE shop_id = ?");
    $stmt->execute([$shop_id]);
    $maxOrder = $stmt->fetchColumn();
    $nextOrder = ($maxOrder) ? $maxOrder + 1 : 1;

    $stmt = $pdo->prepare("INSERT INTO sheet_mst (shop_id, sheet_name, x_pos, y_pos, width, height, type, display_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$shop_id, $name, $x, $y, $w, $h, $type, $nextOrder]);
    
    $newId = $pdo->lastInsertId();

    echo json_encode(['status' => 'success', 'sheet_id' => $newId]);

} catch (Exception $e) {
    error_log("Add Sheet Error: " . $e->getMessage());
    http_response_code(500); // Optional: send 500
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
