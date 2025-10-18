<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/base_api_client.php';

// セッション開始
session_start();

// BASE API認証チェック
if (!isset($_SESSION['base_access_token'])) {
    echo '<div style="background-color: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px; margin: 20px;">';
    echo '<strong>BASE API認証が必要です。</strong><br>';
    echo '<a href="base_callback_debug.php" style="background-color: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px;">BASE API認証を実行</a>';
    echo '</div>';
    exit;
}

try {
    $api = new BaseApiClient($_SESSION['base_access_token']);
    
    // 最新の注文を1件取得
    $orders = $api->getOrders(1, 0);
    
    if (empty($orders['orders'])) {
        echo '<div style="background-color: #d1ecf1; color: #0c5460; padding: 15px; border-radius: 8px; margin: 20px;">';
        echo '注文データがありません。';
        echo '</div>';
        exit;
    }
    
    $first_order = $orders['orders'][0];
    $unique_key = $first_order['unique_key'];
    
    echo '<h2>注文詳細デバッグ - 注文ID: ' . htmlspecialchars($unique_key) . '</h2>';
    
    // 注文詳細を取得
    $order_detail = $api->getOrderDetail($unique_key);
    
    echo '<h3>注文詳細データ構造:</h3>';
    echo '<pre style="background-color: #f8f9fa; padding: 15px; border-radius: 8px; overflow-x: auto;">';
    echo htmlspecialchars(print_r($order_detail, true));
    echo '</pre>';
    
    // 商品情報があるかチェック
    if (isset($order_detail['items']) && is_array($order_detail['items'])) {
        echo '<h3>商品情報:</h3>';
        echo '<pre style="background-color: #d4edda; padding: 15px; border-radius: 8px;">';
        echo htmlspecialchars(print_r($order_detail['items'], true));
        echo '</pre>';
    }
    
    // お客様情報があるかチェック
    if (isset($order_detail['customer']) && is_array($order_detail['customer'])) {
        echo '<h3>お客様情報:</h3>';
        echo '<pre style="background-color: #fff3cd; padding: 15px; border-radius: 8px;">';
        echo htmlspecialchars(print_r($order_detail['customer'], true));
        echo '</pre>';
    }
    
    // 配送情報があるかチェック
    if (isset($order_detail['shipping']) && is_array($order_detail['shipping'])) {
        echo '<h3>配送情報:</h3>';
        echo '<pre style="background-color: #d1ecf1; padding: 15px; border-radius: 8px;">';
        echo htmlspecialchars(print_r($order_detail['shipping'], true));
        echo '</pre>';
    }
    
} catch (Exception $e) {
    echo '<div style="background-color: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px; margin: 20px;">';
    echo '<strong>エラー:</strong> ' . htmlspecialchars($e->getMessage());
    echo '</div>';
}
?>
