<?php
// 新規座席追加API
require_once __DIR__ . '/../../common/config.php';
require_once __DIR__ . '/../../common/dbconnect.php';
require_once __DIR__ . '/../../common/functions.php';

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$shop_id = $input['shop_id'] ?? 0;
$name = $input['name'] ?? 'New Seat';
$x = $input['x'] ?? 50;
$y = $input['y'] ?? 50;
$w = $input['w'] ?? 10;
$h = $input['h'] ?? 10;
$type = $input['type'] ?? 'rect';

if (empty($shop_id)) {
    echo json_encode(['status' => 'error', 'message' => 'Shop ID required']);
    exit;
}

try {
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

} catch (PDOException $e) {
    error_log("Add Sheet Error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Database error']);
}
