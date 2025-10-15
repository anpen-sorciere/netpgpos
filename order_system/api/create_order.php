<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

session_start();

require_once(__DIR__ . '/../../dbconnect.php');
require_once(__DIR__ . '/../../functions.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method Not Allowed']);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    $data = $_POST;
}

$shop_utype = isset($data['shop_utype']) ? (int)$data['shop_utype'] : (int)($_SESSION['utype'] ?? 0);
$table_number = isset($data['table_number']) ? (int)$data['table_number'] : 0;
$items = isset($data['items']) && is_array($data['items']) ? $data['items'] : [];
$note = isset($data['note']) ? trim((string)$data['note']) : null;

if ($shop_utype <= 0 || $table_number <= 0 || empty($items)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid parameters']);
    exit;
}

try {
    $pdo = connect();
    $pdo->beginTransaction();

    // Insert order header
    $sqlOrder = 'INSERT INTO orders (shop_utype, table_number, device_session_id, status, note) VALUES (?, ?, ?, ?, ?)';
    $stmtOrder = $pdo->prepare($sqlOrder);
    $deviceSession = session_id();
    $status = 'pending';
    $stmtOrder->execute([$shop_utype, $table_number, $deviceSession, $status, $note]);
    $orderId = (int)$pdo->lastInsertId();

    // Prepare item_mst lookup
    $stmtFindItem = $pdo->prepare('SELECT item_id, item_name, price FROM item_mst WHERE item_id = ? LIMIT 1');

    $sqlItem = 'INSERT INTO order_items (order_id, item_id, item_name, unit_price, quantity, canceled_quantity, status) VALUES (?, ?, ?, ?, ?, 0, \'pending\')';
    $stmtItem = $pdo->prepare($sqlItem);

    foreach ($items as $row) {
        $itemId = isset($row['item_id']) ? (int)$row['item_id'] : null;
        $qty = isset($row['quantity']) ? (int)$row['quantity'] : 0;
        $customName = isset($row['item_name']) ? trim((string)$row['item_name']) : '';
        $customPrice = isset($row['unit_price']) ? (int)$row['unit_price'] : null;

        if ($qty <= 0) {
            continue; // skip invalid lines
        }

        $name = $customName;
        $price = (int)($customPrice ?? 0);
        $refItemId = null;

        if (!empty($itemId)) {
            $stmtFindItem->execute([$itemId]);
            $found = $stmtFindItem->fetch(PDO::FETCH_ASSOC);
            if ($found) {
                $refItemId = (int)$found['item_id'];
                $name = $name !== '' ? $name : (string)$found['item_name'];
                $price = $customPrice !== null ? (int)$customPrice : (int)$found['price'];
            }
        }

        if ($name === '') {
            // 最低限、名称は必要
            $name = 'メニュー';
        }

        $stmtItem->execute([$orderId, $refItemId, $name, $price, $qty]);
    }

    $pdo->commit();
    echo json_encode(['ok' => true, 'order_id' => $orderId]);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}


