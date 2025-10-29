<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

require_once(__DIR__ . '/../../../common/config.php');
require_once(__DIR__ . '/../../../common/dbconnect.php');
require_once(__DIR__ . '/../../../common/functions.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method Not Allowed']);
    exit;
}

$order_item_id = isset($_POST['order_item_id']) ? (int)$_POST['order_item_id'] : 0;
$cancel_qty = isset($_POST['cancel_qty']) ? (int)$_POST['cancel_qty'] : 0;
$reason = isset($_POST['reason']) ? trim((string)$_POST['reason']) : null;

if ($order_item_id <= 0 || $cancel_qty <= 0) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid parameters']);
    exit;
}

try {
    $pdo = connect();
    $pdo->beginTransaction();

    $stmtSel = $pdo->prepare('SELECT id, order_id, quantity, canceled_quantity, status FROM order_items WHERE id = ? FOR UPDATE');
    $stmtSel->execute([$order_item_id]);
    $item = $stmtSel->fetch(PDO::FETCH_ASSOC);
    if (!$item) {
        throw new RuntimeException('order_item not found');
    }

    $remain = (int)$item['quantity'] - (int)$item['canceled_quantity'];
    if ($remain <= 0) {
        throw new RuntimeException('already fully canceled');
    }
    $apply = min($remain, $cancel_qty);

    $newCanceled = (int)$item['canceled_quantity'] + $apply;
    $newStatus = $newCanceled >= (int)$item['quantity'] ? 'canceled' : $item['status'];

    $stmtUpd = $pdo->prepare('UPDATE order_items SET canceled_quantity = ?, status = ?, cancel_reason = ?, updated_at = NOW() WHERE id = ?');
    $stmtUpd->execute([$newCanceled, $newStatus, $reason, $order_item_id]);

    // Optional: insert event
    $stmtEvt = $pdo->prepare('INSERT INTO order_item_events (order_item_id, event_type, event_qty, meta_json) VALUES (?, \'canceled\', ?, NULL)');
    $stmtEvt->execute([$order_item_id, $apply]);

    $pdo->commit();
    echo json_encode(['ok' => true, 'applied' => $apply, 'new_canceled' => $newCanceled, 'status' => $newStatus]);
} catch (Throwable $e) {
    if (isset($pdo)) {
        $pdo->rollBack();
    }
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}


