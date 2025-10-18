<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/base_scope_manager.php';

$scope_manager = new BaseScopeManager();

echo "<h1>BASE API スコープ動的管理</h1>";

echo "<h2>現在の認証状態</h2>";
echo "現在のスコープ: " . ($scope_manager->getCurrentScope() ?? '未設定') . "<br>";
echo "アクセストークン: " . (isset($_SESSION['base_access_token']) ? '設定済み' : '未設定') . "<br>";
echo "リフレッシュトークン: " . (isset($_SESSION['base_refresh_token']) ? '設定済み' : '未設定') . "<br>";

echo "<h2>利用可能なスコープ</h2>";
$scopes = $scope_manager->getAvailableScopes();
foreach ($scopes as $scope => $description) {
    echo "• {$scope}: {$description}<br>";
}

echo "<h2>スコープ認証</h2>";
echo "<p>各スコープを個別に認証できます。BASE APIでは同時に複数のスコープを使用できません。</p>";

$combinations = $scope_manager->getScopeCombinations();
foreach ($combinations as $key => $scope_list) {
    $scope_string = implode(', ', $scope_list);
    echo "<div style='border: 1px solid #ccc; padding: 10px; margin: 5px 0;'>";
    echo "<strong>{$key}</strong>: {$scope_string}<br>";
    echo "<a href='" . $scope_manager->getAuthUrl($key) . "' style='background: #007cba; color: white; padding: 5px 10px; text-decoration: none; border-radius: 3px;'>認証実行</a>";
    echo "</div>";
}

echo "<h2>テスト機能</h2>";
echo "<h3>注文データのみ取得</h3>";
echo "<a href='test_orders_only.php' style='background: #28a745; color: white; padding: 5px 10px; text-decoration: none; border-radius: 3px;'>注文データテスト</a><br><br>";

echo "<h3>商品データのみ取得</h3>";
echo "<a href='test_items_only.php' style='background: #28a745; color: white; padding: 5px 10px; text-decoration: none; border-radius: 3px;'>商品データテスト</a><br><br>";

echo "<h3>組み合わせデータ取得</h3>";
echo "<a href='test_combined_data.php' style='background: #dc3545; color: white; padding: 5px 10px; text-decoration: none; border-radius: 3px;'>組み合わせテスト（要2回認証）</a><br><br>";

echo "<h2>セッション管理</h2>";
echo "<a href='clear_session.php' style='background: #6c757d; color: white; padding: 5px 10px; text-decoration: none; border-radius: 3px;'>セッションクリア</a><br><br>";

echo "<h2>メイン機能へのリンク</h2>";
echo "<a href='order_monitor.php' style='background: #17a2b8; color: white; padding: 5px 10px; text-decoration: none; border-radius: 3px;'>注文監視画面</a><br><br>";
?>
