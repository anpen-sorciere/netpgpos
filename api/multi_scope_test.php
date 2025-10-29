<?php
session_start();
require_once __DIR__ . '/../../common/config.php';
require_once __DIR__ . '/../../common/dbconnect.php';
require_once __DIR__ . '/../../common/functions.php';
require_once __DIR__ . '/base_scope_manager.php';

$scope_manager = new BaseScopeManager();

echo "<h1>複数スコープ認証テスト</h1>";

echo "<h2>現在の認証状態</h2>";
echo "現在のスコープ: " . ($scope_manager->getCurrentScope() ?? '未設定') . "<br>";
echo "認証が必要: " . ($scope_manager->needsAuth() ? 'はい' : 'いいえ') . "<br>";

echo "<h2>スコープ別認証状態</h2>";
$combinations = $scope_manager->getScopeCombinations();
foreach ($combinations as $key => $scope_list) {
    $is_authenticated = $scope_manager->isScopeAuthenticated($key);
    $status = $is_authenticated ? '<span style="color: green;">✓ 認証済み</span>' : '<span style="color: red;">✗ 未認証</span>';
    echo "• {$key}: {$status}<br>";
}

echo "<h2>認証手順</h2>";
echo "<p>以下の順序で認証してください：</p>";

echo "<h3>1. 注文データ認証</h3>";
if ($scope_manager->isScopeAuthenticated('orders_only')) {
    echo '<span style="color: green;">✓ 注文データ認証済み</span><br>';
} else {
    echo '<a href="' . $scope_manager->getAuthUrl('orders_only') . '" style="background: #007cba; color: white; padding: 5px 10px; text-decoration: none; border-radius: 3px;">注文データ認証</a><br>';
}

echo "<h3>2. 商品データ認証</h3>";
if ($scope_manager->isScopeAuthenticated('items_only')) {
    echo '<span style="color: green;">✓ 商品データ認証済み</span><br>';
} else {
    echo '<a href="' . $scope_manager->getAuthUrl('items_only') . '" style="background: #007cba; color: white; padding: 5px 10px; text-decoration: none; border-radius: 3px;">商品データ認証</a><br>';
}

echo "<h2>テスト実行</h2>";
$orders_auth = $scope_manager->isScopeAuthenticated('orders_only');
$items_auth = $scope_manager->isScopeAuthenticated('items_only');

if ($orders_auth && $items_auth) {
    echo '<a href="test_combined_data.php" style="background: #28a745; color: white; padding: 5px 10px; text-decoration: none; border-radius: 3px;">組み合わせテスト実行</a><br>';
} else {
    echo '<span style="color: red;">両方のスコープで認証が必要です</span><br>';
}

echo "<h2>個別テスト</h2>";
if ($orders_auth) {
    echo '<a href="test_orders_only.php" style="background: #28a745; color: white; padding: 5px 10px; text-decoration: none; border-radius: 3px;">注文データテスト</a><br>';
} else {
    echo '<span style="color: gray;">注文データテスト（認証が必要）</span><br>';
}

if ($items_auth) {
    echo '<a href="test_items_only.php" style="background: #28a745; color: white; padding: 5px 10px; text-decoration: none; border-radius: 3px;">商品データテスト</a><br>';
} else {
    echo '<span style="color: gray;">商品データテスト（認証が必要）</span><br>';
}

echo "<h2>管理機能</h2>";
echo '<a href="clear_session.php" style="background: #6c757d; color: white; padding: 5px 10px; text-decoration: none; border-radius: 3px;">セッションクリア</a><br>';
echo '<a href="scope_manager.php" style="background: #17a2b8; color: white; padding: 5px 10px; text-decoration: none; border-radius: 3px;">スコープ管理画面</a><br>';
?>
