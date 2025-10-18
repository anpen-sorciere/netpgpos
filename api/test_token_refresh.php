<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/base_auto_scope_manager.php';

$auto_manager = new BaseAutoScopeManager();

echo "<h1>BASE API 期限切れ自動リフレッシュテスト</h1>";
echo "<p>トークンの期限切れ時に自動リフレッシュが動作するかテストします。</p>";

echo "<h2>現在の認証状態</h2>";
$auth_status = $auto_manager->getScopeAuthStatus();
foreach ($auth_status as $scope_key => $status) {
    $auth_text = $status['authenticated'] ? '<span style="color: green;">✓ 認証済み</span>' : '<span style="color: red;">✗ 未認証</span>';
    $expires_text = $status['expires'] ? date('Y-m-d H:i:s', $status['expires']) : '不明';
    $refresh_text = $status['has_refresh_token'] ? 'あり' : 'なし';
    $remaining_time = $status['expires'] ? ($status['expires'] - time()) : 0;
    
    echo "<strong>{$scope_key}</strong>: {$auth_text}<br>";
    echo "&nbsp;&nbsp;期限: {$expires_text} (残り{$remaining_time}秒)<br>";
    echo "&nbsp;&nbsp;リフレッシュトークン: {$refresh_text}<br><br>";
}

echo "<h2>期限切れシミュレーションテスト</h2>";
echo "<p>注文データのトークンを強制的に期限切れにして、自動リフレッシュをテストします。</p>";

// 期限切れシミュレーション
$original_expires = $_SESSION['base_token_expires_orders_only'] ?? null;
$_SESSION['base_token_expires_orders_only'] = time() - 1; // 1秒前に期限切れ

echo "<h3>期限切れ設定後のテスト</h3>";
try {
    $result = $auto_manager->getCombinedOrderData(5);
    
    echo "<h4>認証ログ</h4>";
    foreach ($result['auth_log'] as $log) {
        echo "• " . htmlspecialchars($log) . "<br>";
    }
    
    if ($result['error']) {
        echo "<h4 style='color: red;'>エラー</h4>";
        echo htmlspecialchars($result['error']) . "<br>";
    } else {
        echo "<h4 style='color: green;'>成功！</h4>";
        echo "注文件数: " . count($result['orders']) . "<br>";
        echo "商品件数: " . count($result['items']) . "<br>";
        echo "合成済み注文件数: " . count($result['merged_orders']) . "<br>";
        
        echo "<h4>更新後の認証状態</h4>";
        $updated_status = $auto_manager->getScopeAuthStatus();
        foreach ($updated_status as $scope_key => $status) {
            if ($scope_key === 'orders_only') {
                $expires_text = $status['expires'] ? date('Y-m-d H:i:s', $status['expires']) : '不明';
                $remaining_time = $status['expires'] ? ($status['expires'] - time()) : 0;
                echo "<strong>{$scope_key}</strong>: 期限 {$expires_text} (残り{$remaining_time}秒)<br>";
            }
        }
    }
    
} catch (Exception $e) {
    echo "<h4 style='color: red;'>例外エラー</h4>";
    echo htmlspecialchars($e->getMessage()) . "<br>";
} finally {
    // 元の期限に戻す
    if ($original_expires) {
        $_SESSION['base_token_expires_orders_only'] = $original_expires;
    }
}

echo "<h2>管理機能</h2>";
echo '<a href="clear_session.php" style="background: #6c757d; color: white; padding: 5px 10px; text-decoration: none; border-radius: 3px;">セッションクリア</a><br>';
echo '<a href="test_auto_scope.php" style="background: #17a2b8; color: white; padding: 5px 10px; text-decoration: none; border-radius: 3px;">通常テストに戻る</a><br>';
?>
