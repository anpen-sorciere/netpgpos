<?php
// API List Endpoint Option Availability Check
header('Content-Type: text/html; charset=utf-8');
require_once __DIR__ . '/../../common/config.php';
require_once __DIR__ . '/../../common/dbconnect.php';
require_once __DIR__ . '/../../common/functions.php';
require_once __DIR__ . '/base_practical_auto_manager.php';

echo '<pre>';
try {
    $manager = new BasePracticalAutoManager();
    echo "BASE API /orders (List) エンドポイントのレスポンス確認中...\n";
    
    // リスト取得 (最新10件)
    $orders = $manager->getDataWithAutoAuth('read_orders', '/orders', ['limit' => 10]);
    
    if (isset($orders['orders'])) {
        foreach ($orders['orders'] as $index => $order) {
            echo "Order ID: " . $order['unique_key'] . "\n";
            if (isset($order['order_items'])) {
                foreach ($order['order_items'] as $item) {
                    echo "  Item: " . $item['title'] . "\n";
                    if (isset($item['options'])) {
                        echo "    Options Count: " . count($item['options']) . "\n";
                        print_r($item['options']);
                    } else {
                        echo "    Options: Not Set (NULL)\n";
                    }
                }
            } else {
                echo "  No Items in Response\n";
            }
            echo "--------------------------\n";
        }
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
echo '</pre>';
