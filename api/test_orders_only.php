<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/base_scope_manager.php';

$scope_manager = new BaseScopeManager();

echo "<h1>注文データのみテスト</h1>";

try {
    $api_client = $scope_manager->createApiClient('orders_only');
    $orders = $api_client->getOrders(10);
    
    echo "<h2>取得成功</h2>";
    echo "取得件数: " . count($orders) . "<br>";
    echo "<pre>" . print_r($orders, true) . "</pre>";
    
} catch (Exception $e) {
    echo "<h2>エラー</h2>";
    echo "エラー: " . $e->getMessage() . "<br>";
    echo "<a href='scope_manager.php'>スコープ管理に戻る</a>";
}
?>
