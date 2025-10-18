<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/base_scope_manager.php';

$scope_manager = new BaseScopeManager();

echo "<h1>商品データのみテスト</h1>";

try {
    $api_client = $scope_manager->createApiClient('items_only');
    $items = $api_client->getProducts(10);
    
    echo "<h2>取得成功</h2>";
    echo "取得件数: " . count($items) . "<br>";
    echo "<pre>" . print_r($items, true) . "</pre>";
    
} catch (Exception $e) {
    echo "<h2>エラー</h2>";
    echo "エラー: " . $e->getMessage() . "<br>";
    echo "<a href='scope_manager.php'>スコープ管理に戻る</a>";
}
?>
