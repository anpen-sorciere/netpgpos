<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(EALL);

header('Content-Type: application/json; charset=utf-8');

require_once(__DIR__ . '/../../dbconnect.php');
require_once(__DIR__ . '/../../functions.php');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method Not Allowed']);
    exit;
}

$order_item_id = isset($_POST['order_item_id']) ? (int)$_POST['order_item_id'] : 0;
$status = isset($_POST['status']) ? (string)$_POST['status'] : '';

$allowed = ['pending','in_progress','served','canceled'];
if ($order_item_id <= 0 || !in_array($status, $allowed, true)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid parameters']);
    exit;
}

try {
    $pdo = connect();
    $pdo->beginTransaction();

    $stmtSel = $pdo->prepare('SELECT id, quantity, canceled_quantity FROM order_items WHERE id = ? FOR UPDATE');
    $stmtSel->execute([$order_item_id]);
    $item = $stmtSel->fetch(PDO::FETCH_ASSOC);
    if (!$item) {
        throw new RuntimeException('order_item not found');
    }

    // served の場合、残数量が 0 なら served にしない（運用に応じて変更可）
    $remain = (int)$item['quantity'] - (int)$item['canceled_quantity'];
    if ($status === 'served' && $remain <= 0) {
        throw new RuntimeException('no remaining quantity to serve');
    }

    $stmtUpd = $pdo->prepare('UPDATE order_items SET status = ?, updated_at = NOW() WHERE id = ?');
    $stmtUpd->execute([$status, $order_item_id]);

    // Optional event
    $stmtEvt = $pdo->prepare('INSERT INTO order_item_events (order_item_id, event_type, event_qty, meta_json) VALUES (?, ?, NULL, NULL)');
    $stmtEvt->execute([$order_item_id, $status]);

    $pdo->commit();
    echo json_encode(['ok' => true]);
} catch (Throwable $e) {
    if (isset($pdo)) { $pdo->rollBack(); }
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}




