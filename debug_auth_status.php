<?php
/**
 * BASE API 認証状態詳細確認スクリプト
 * 認証状態とエラーの詳細を表示
 */
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/api/base_practical_auto_manager.php';

echo "<h1>BASE API 認証状態詳細確認</h1>";
echo "<p>認証状態とエラーの詳細を表示します</p>";

try {
    $practical_manager = new BasePracticalAutoManager();
    
    echo "<h2>1. 認証状態の確認</h2>";
    $auth_status = $practical_manager->getAuthStatus();
    
    echo "<div style='background: #f0f0f0; padding: 15px; border: 1px solid #ccc; border-radius: 5px;'>";
    echo "<h3>スコープ別認証状態</h3>";
    foreach ($auth_status as $scope => $status) {
        $authenticated = $status['authenticated'] ? '✓ 認証済み' : '✗ 未認証';
        $expires = isset($status['expires']) ? $status['expires'] : '不明';
        $refresh_token = isset($status['refresh_token']) && !empty($status['refresh_token']) ? 'あり' : 'なし';
        
        echo "<p><strong>{$scope}:</strong> {$authenticated} (期限: {$expires}, リフレッシュ: {$refresh_token})</p>";
    }
    echo "</div>";
    
    echo "<h2>2. データベース接続確認</h2>";
    try {
        require_once __DIR__ . '/dbconnect.php';
        echo "<div style='color: green;'>✓ データベース接続成功</div>";
        
        // テーブル存在確認
        $tables = ['system_config', 'base_api_tokens', 'system_logs'];
        foreach ($tables as $table) {
            $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
            $stmt->execute([$table]);
            $exists = $stmt->fetch() ? '✓' : '✗';
            echo "<p>{$exists} {$table} テーブル</p>";
        }
        
    } catch (Exception $e) {
        echo "<div style='color: red;'>✗ データベース接続エラー: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
    
    echo "<h2>3. 設定確認</h2>";
    echo "<div style='background: #f9f9f9; padding: 10px; border: 1px solid #ddd; border-radius: 3px;'>";
    echo "<p><strong>BASE_CLIENT_ID:</strong> " . (defined('BASE_CLIENT_ID') ? '設定済み' : '未設定') . "</p>";
    echo "<p><strong>BASE_CLIENT_SECRET:</strong> " . (defined('BASE_CLIENT_SECRET') ? '設定済み' : '未設定') . "</p>";
    echo "<p><strong>BASE_REDIRECT_URI:</strong> " . (defined('BASE_REDIRECT_URI') ? BASE_REDIRECT_URI : '未設定') . "</p>";
    echo "</div>";
    
    echo "<h2>4. 簡単なAPIテスト</h2>";
    try {
        // orders_only スコープでテスト
        if ($auth_status['orders_only']['authenticated']) {
            echo "<p>orders_only スコープでテスト実行中...</p>";
            $test_data = $practical_manager->getDataWithAutoAuth('orders_only', '/orders', ['limit' => 1]);
            echo "<div style='color: green;'>✓ orders_only APIテスト成功</div>";
            echo "<pre style='background: white; padding: 10px; border: 1px solid #ccc; border-radius: 3px; overflow-x: auto;'>";
            echo htmlspecialchars(json_encode($test_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            echo "</pre>";
        } else {
            echo "<div style='color: red;'>✗ orders_only スコープが認証されていません</div>";
        }
    } catch (Exception $e) {
        echo "<div style='color: red;'>✗ APIテストエラー: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
    
} catch (Exception $e) {
    echo "<div style='color: red; background: #f8d7da; padding: 15px; border: 1px solid #f5c6cb; border-radius: 5px;'>";
    echo "<h3>エラーが発生しました</h3>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><strong>ファイル:</strong> " . $e->getFile() . "</p>";
    echo "<p><strong>行:</strong> " . $e->getLine() . "</p>";
    echo "</div>";
}
?>
