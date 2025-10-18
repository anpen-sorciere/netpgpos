<?php
/**
 * BASE API getAuthUrl メソッド詳細デバッグスクリプト
 * getAuthUrlメソッドの実行過程を詳細に確認
 */
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/api/base_practical_auto_manager.php';

echo "<h1>BASE API getAuthUrl メソッド詳細デバッグ</h1>";
echo "<p>getAuthUrlメソッドの実行過程を詳細に確認します</p>";

echo "<h2>1. 設定値の確認</h2>";
echo "<div style='background: #f0f0f0; padding: 15px; border: 1px solid #ccc; border-radius: 5px;'>";
echo "<p><strong>\$base_client_id:</strong> " . (isset($base_client_id) ? $base_client_id : '未設定') . "</p>";
echo "<p><strong>\$base_client_secret:</strong> " . (isset($base_client_secret) ? '設定済み' : '未設定') . "</p>";
echo "<p><strong>\$base_redirect_uri:</strong> " . (isset($base_redirect_uri) ? $base_redirect_uri : '未設定') . "</p>";
echo "<p><strong>\$base_api_url:</strong> " . (isset($base_api_url) ? $base_api_url : '未設定') . "</p>";
echo "</div>";

echo "<h2>2. BasePracticalAutoManager インスタンス作成</h2>";
try {
    echo "<p>インスタンス作成中...</p>";
    $practical_manager = new BasePracticalAutoManager();
    echo "<div style='color: green;'>✓ BasePracticalAutoManager インスタンス作成成功</div>";
    
    // リフレクションを使用してプロパティを確認
    $reflection = new ReflectionClass($practical_manager);
    $properties = $reflection->getProperties(ReflectionProperty::IS_PRIVATE);
    
    echo "<h3>インスタンスのプロパティ値</h3>";
    echo "<div style='background: #f9f9f9; padding: 10px; border: 1px solid #ddd; border-radius: 3px;'>";
    foreach ($properties as $property) {
        $property->setAccessible(true);
        $value = $property->getValue($practical_manager);
        $property_name = $property->getName();
        
        if ($property_name === 'client_secret' || $property_name === 'encryption_key') {
            $display_value = empty($value) ? '空' : '設定済み';
        } else {
            $display_value = empty($value) ? '空' : $value;
        }
        
        echo "<p><strong>\${$property_name}:</strong> {$display_value}</p>";
    }
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div style='color: red; background: #f8d7da; padding: 15px; border: 1px solid #f5c6cb; border-radius: 5px;'>";
    echo "<h3>インスタンス作成エラー</h3>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><strong>ファイル:</strong> " . $e->getFile() . "</p>";
    echo "<p><strong>行:</strong> " . $e->getLine() . "</p>";
    echo "</div>";
    exit;
}

echo "<h2>3. getAuthUrl メソッドの詳細テスト</h2>";
$scopes = ['orders_only', 'items_only'];

foreach ($scopes as $scope) {
    echo "<h3>{$scope} の認証URL生成</h3>";
    echo "<div style='background: #e8f4fd; padding: 15px; border: 1px solid #b3d9ff; border-radius: 5px;'>";
    
    try {
        echo "<p>getAuthUrl('{$scope}') を呼び出し中...</p>";
        
        // メソッドの実行時間を測定
        $start_time = microtime(true);
        $auth_url = $practical_manager->getAuthUrl($scope);
        $end_time = microtime(true);
        $execution_time = ($end_time - $start_time) * 1000; // ミリ秒
        
        echo "<div style='color: green;'>✓ 認証URL生成成功 (実行時間: " . number_format($execution_time, 2) . "ms)</div>";
        echo "<p><strong>生成されたURL:</strong></p>";
        echo "<p><a href='{$auth_url}' target='_blank' style='word-break: break-all;'>{$auth_url}</a></p>";
        echo "<p><a href='{$auth_url}' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; margin-right: 10px;' target='_blank'>{$scope} 認証を実行</a></p>";
        
    } catch (Exception $e) {
        echo "<div style='color: red; background: #f8d7da; padding: 10px; border: 1px solid #f5c6cb; border-radius: 3px;'>";
        echo "<p><strong>エラー:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
        echo "<p><strong>ファイル:</strong> " . $e->getFile() . "</p>";
        echo "<p><strong>行:</strong> " . $e->getLine() . "</p>";
        echo "<p><strong>スタックトレース:</strong></p>";
        echo "<pre style='background: white; padding: 10px; border: 1px solid #ccc; border-radius: 3px; overflow-x: auto; font-size: 12px;'>";
        echo htmlspecialchars($e->getTraceAsString());
        echo "</pre>";
        echo "</div>";
    }
    
    echo "</div>";
}

echo "<h2>4. 手動認証URL生成（比較用）</h2>";
echo "<div style='background: #fff3cd; padding: 15px; border: 1px solid #ffeaa7; border-radius: 5px;'>";
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
