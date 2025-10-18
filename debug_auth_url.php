<?php
/**
 * BASE API 認証URL生成テストスクリプト
 * 認証URL生成の詳細を確認
 */
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/api/base_practical_auto_manager.php';

echo "<h1>BASE API 認証URL生成テスト</h1>";
echo "<p>認証URL生成の詳細を確認します</p>";

echo "<h2>1. 設定値の確認</h2>";
echo "<div style='background: #f0f0f0; padding: 15px; border: 1px solid #ccc; border-radius: 5px;'>";
echo "<p><strong>BASE_CLIENT_ID:</strong> " . (defined('BASE_CLIENT_ID') ? BASE_CLIENT_ID : '未定義') . "</p>";
echo "<p><strong>BASE_CLIENT_SECRET:</strong> " . (defined('BASE_CLIENT_SECRET') ? '設定済み' : '未設定') . "</p>";
echo "<p><strong>BASE_REDIRECT_URI:</strong> " . (defined('BASE_REDIRECT_URI') ? BASE_REDIRECT_URI : '未設定') . "</p>";
echo "<p><strong>BASE_API_URL:</strong> " . (defined('BASE_API_URL') ? BASE_API_URL : '未設定') . "</p>";
echo "</div>";

echo "<h2>2. グローバル変数の確認</h2>";
echo "<div style='background: #f9f9f9; padding: 10px; border: 1px solid #ddd; border-radius: 3px;'>";
echo "<p><strong>\$base_client_id:</strong> " . (isset($base_client_id) ? $base_client_id : '未設定') . "</p>";
echo "<p><strong>\$base_client_secret:</strong> " . (isset($base_client_secret) ? '設定済み' : '未設定') . "</p>";
echo "<p><strong>\$base_redirect_uri:</strong> " . (isset($base_redirect_uri) ? $base_redirect_uri : '未設定') . "</p>";
echo "<p><strong>\$base_api_url:</strong> " . (isset($base_api_url) ? $base_api_url : '未設定') . "</p>";
echo "</div>";

echo "<h2>3. BasePracticalAutoManager インスタンス作成テスト</h2>";
try {
    $practical_manager = new BasePracticalAutoManager();
    echo "<div style='color: green;'>✓ BasePracticalAutoManager インスタンス作成成功</div>";
    
    echo "<h2>4. getAuthUrl メソッドテスト</h2>";
    $scopes = ['orders_only', 'items_only'];
    
    foreach ($scopes as $scope) {
        echo "<h3>{$scope} の認証URL生成</h3>";
        try {
            $auth_url = $practical_manager->getAuthUrl($scope);
            echo "<div style='background: #d4edda; padding: 10px; border: 1px solid #c3e6cb; border-radius: 3px;'>";
            echo "<p><strong>認証URL:</strong> <a href='{$auth_url}' target='_blank'>{$auth_url}</a></p>";
            echo "<p><a href='{$auth_url}' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; margin-right: 10px;' target='_blank'>{$scope} 認証を実行</a></p>";
            echo "</div>";
        } catch (Exception $e) {
            echo "<div style='color: red; background: #f8d7da; padding: 10px; border: 1px solid #f5c6cb; border-radius: 3px;'>";
            echo "<p><strong>エラー:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
            echo "<p><strong>ファイル:</strong> " . $e->getFile() . "</p>";
            echo "<p><strong>行:</strong> " . $e->getLine() . "</p>";
            echo "</div>";
        }
    }
    
} catch (Exception $e) {
    echo "<div style='color: red; background: #f8d7da; padding: 15px; border: 1px solid #f5c6cb; border-radius: 5px;'>";
    echo "<h3>BasePracticalAutoManager インスタンス作成エラー</h3>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><strong>ファイル:</strong> " . $e->getFile() . "</p>";
    echo "<p><strong>行:</strong> " . $e->getLine() . "</p>";
    echo "</div>";
}

echo "<h2>5. 手動認証URL（直接生成）</h2>";
echo "<div style='background: #e8f4fd; padding: 15px; border: 1px solid #b3d9ff; border-radius: 5px;'>";
echo "<p>設定値から直接認証URLを生成します：</p>";

if (isset($base_client_id) && isset($base_redirect_uri)) {
    $scopes = [
        'orders_only' => 'read_orders',
        'items_only' => 'read_items'
    ];
    
    foreach ($scopes as $scope_key => $api_scope) {
        $params = [
            'response_type' => 'code',
            'client_id' => $base_client_id,
            'redirect_uri' => $base_redirect_uri,
            'scope' => $api_scope,
            'state' => $scope_key
        ];
        
        $auth_url = 'https://api.thebase.in/oauth/authorize?' . http_build_query($params);
        echo "<p><a href='{$auth_url}' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; margin-right: 10px;' target='_blank'>{$scope_key} 認証を実行（手動生成）</a></p>";
    }
} else {
    echo "<p style='color: red;'>設定値が不足しています</p>";
}
echo "</div>";
?>
