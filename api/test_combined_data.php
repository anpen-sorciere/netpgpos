<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/base_scope_manager.php';

$scope_manager = new BaseScopeManager();

echo "<h1>組み合わせデータテスト</h1>";
echo "<p>このテストは注文データと商品データを組み合わせて取得します。</p>";

echo "<h2>認証状態チェック</h2>";
$orders_auth = $scope_manager->isScopeAuthenticated('orders_only');
$items_auth = $scope_manager->isScopeAuthenticated('items_only');

echo "注文データ認証: " . ($orders_auth ? '<span style="color: green;">✓ 認証済み</span>' : '<span style="color: red;">✗ 未認証</span>') . "<br>";
echo "商品データ認証: " . ($items_auth ? '<span style="color: green;">✓ 認証済み</span>' : '<span style="color: red;">✗ 未認証</span>') . "<br>";

if (!$orders_auth || !$items_auth) {
    echo "<h2>認証が必要です</h2>";
    echo "<p>以下の認証を完了してください：</p>";
    if (!$orders_auth) {
        echo '<a href="' . $scope_manager->getAuthUrl('orders_only') . '" style="background: #007cba; color: white; padding: 5px 10px; text-decoration: none; border-radius: 3px;">注文データ認証</a><br><br>';
    }
    if (!$items_auth) {
        echo '<a href="' . $scope_manager->getAuthUrl('items_only') . '" style="background: #007cba; color: white; padding: 5px 10px; text-decoration: none; border-radius: 3px;">商品データ認証</a><br><br>';
    }
    echo '<a href="multi_scope_test.php">複数スコープ認証テストに戻る</a>';
    exit;
}

try {
    $combined_data = $scope_manager->getCombinedData(5);
    
    if ($combined_data['error']) {
        echo "<h2>エラー</h2>";
        echo "エラー: " . $combined_data['error'] . "<br>";
    } else {
        echo "<h2>取得成功</h2>";
        echo "注文件数: " . count($combined_data['orders']) . "<br>";
        echo "商品件数: " . count($combined_data['items']) . "<br>";
        
        // マージテスト
        $merged_orders = $scope_manager->mergeOrderWithItems($combined_data['orders'], $combined_data['items']);
        
        echo "<h3>マージ結果サンプル</h3>";
        if (isset($merged_orders['orders'][0])) {
            echo "<pre>" . print_r($merged_orders['orders'][0], true) . "</pre>";
        }
    }
    
} catch (Exception $e) {
    echo "<h2>エラー</h2>";
    echo "エラー: " . $e->getMessage() . "<br>";
}

echo "<br><a href='scope_manager.php'>スコープ管理に戻る</a>";
?>
