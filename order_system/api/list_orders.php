<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

require_once(__DIR__ . '/../../dbconnect.php');
require_once(__DIR__ . '/../../functions.php');

$shop_filter = isset($_GET['shop_utype']) ? (int)$_GET['shop_utype'] : 0; // 0=全店
$status_filter = isset($_GET['status']) ? (string)$_GET['status'] : '';    // '', 'pending', 'in_progress', 'served'
$limit = isset($_GET['limit']) ? max(1, min(500, (int)$_GET['limit'])) : 100;

try {
    $pdo = connect();

    $where = [];
    $params = [];
    if ($shop_filter > 0) {
        $where[] = 'o.shop_utype = ?';
        $params[] = $shop_filter;
    }
    if ($status_filter !== '') {
        $where[] = 'oi.status = ?';
        $params[] = $status_filter;
    }

    $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

    $sql = "
        SELECT 
            o.id AS order_id,
            o.shop_utype,
            o.table_number,
            o.status AS order_status,
            o.created_at,
            oi.id AS order_item_id,
            oi.item_id,
            oi.item_name,
            oi.unit_price,
            oi.quantity,
            oi.canceled_quantity,
            oi.status AS item_status,
            oi.updated_at
        FROM orders o
        JOIN order_items oi ON oi.order_id = o.id
        $whereSql
        ORDER BY o.created_at DESC, o.id DESC, oi.id ASC
        LIMIT $limit
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode(['ok' => true, 'rows' => $rows]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}



