<?php
/**
 * 注文の詳細（お客様情報）をBASE APIから取得するAJAX
 * get_customer_info.php
 */
require_once __DIR__ . '/../../../common/config.php';
require_once __DIR__ . '/../../../common/dbconnect.php';
require_once __DIR__ . '/../classes/base_practical_auto_manager.php'; // パスは適宜調整

header('Content-Type: application/json; charset=utf-8');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception('Invalid Request Method');
    }

    $order_id = $_GET['order_id'] ?? '';
    $shop_id = $_GET['shop_id'] ?? '';

    if (!$order_id || !$shop_id) {
        throw new Exception('Order ID and Shop ID are required.');
    }

    // BASE APIマネージャー初期化
    $manager = new BasePracticalAutoManager($shop_id);

    // 注文詳細API呼び出し
    $response = $manager->makeApiRequest('read_orders', '/orders/detail/' . $order_id);

    if (!isset($response['order'])) {
        throw new Exception('Failed to fetch order details from BASE.');
    }

    $order = $response['order'];
    
    // 必要な情報だけ抽出して返す
    $customer_info = [
        'first_name' => $order['first_name'] ?? '',
        'last_name' => $order['last_name'] ?? '',
        'zip_code' => $order['zip_code'] ?? '',
        'prefecture' => $order['prefecture'] ?? '',
        'address' => $order['address'] ?? '',
        'address2' => $order['address2'] ?? '',
        'tel' => $order['tel'] ?? '',
        'mail_address' => $order['mail_address'] ?? '',
        'payment_method' => $order['payment_method'] ?? '',
        'remark' => $order['remark'] ?? '',  // 備考欄もあれば便利
    ];

    echo json_encode(['success' => true, 'data' => $customer_info]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
