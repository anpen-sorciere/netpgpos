<?php
/**
 * BASE API 認証エンドポイント修正テストスクリプト
 * 正しい認証エンドポイントをテスト
 */
session_start();
require_once __DIR__ . '/config.php';

echo "<h1>BASE API 認証エンドポイント修正テスト</h1>";
echo "<p>正しい認証エンドポイントをテストします</p>";

echo "<h2>1. 設定値の確認</h2>";
echo "<div style='background: #f0f0f0; padding: 15px; border: 1px solid #ccc; border-radius: 5px;'>";
echo "<p><strong>CLIENT_ID:</strong> " . (isset($base_client_id) ? $base_client_id : '未設定') . "</p>";
echo "<p><strong>REDIRECT_URI:</strong> " . (isset($base_redirect_uri) ? $base_redirect_uri : '未設定') . "</p>";
echo "</div>";

echo "<h2>2. 認証エンドポイントのテスト</h2>";
echo "<div style='background: #e8f4fd; padding: 15px; border: 1px solid #b3d9ff; border-radius: 5px;'>";

if (isset($base_client_id) && isset($base_redirect_uri)) {
    
    // 複数の認証エンドポイントをテスト
    $endpoints = [
        'https://api.thebase.in/oauth/authorize',
        'https://api.thebase.in/1/oauth/authorize',
        'https://thebase.in/oauth/authorize',
        'https://thebase.in/api/oauth/authorize'
    ];
    
    $scopes = [
        'orders_only' => 'read_orders',
        'items_only' => 'read_items'
    ];
    
    foreach ($scopes as $scope_key => $api_scope) {
        echo "<h3>{$scope_key} スコープの認証URL</h3>";
        
        foreach ($endpoints as $index => $endpoint) {
            $params = [
                'response_type' => 'code',
                'client_id' => $base_client_id,
                'redirect_uri' => $base_redirect_uri,
                'scope' => $api_scope,
                'state' => $scope_key
            ];
            
            $auth_url = $endpoint . '?' . http_build_query($params);
            $endpoint_name = "エンドポイント " . ($index + 1);
            
            echo "<div style='margin-bottom: 15px; padding: 10px; border: 1px solid #ddd; border-radius: 3px;'>";
            echo "<p><strong>{$endpoint_name}:</strong> {$endpoint}</p>";
            echo "<p><a href='{$auth_url}' style='background: #007bff; color: white; padding: 8px 16px; text-decoration: none; border-radius: 3px; margin-right: 10px;' target='_blank'>{$scope_key} 認証テスト</a></p>";
            echo "<p style='font-size: 12px; color: #666; word-break: break-all;'>{$auth_url}</p>";
            echo "</div>";
        }
    }
    
} else {
    echo "<p style='color: red;'>設定値が不足しています</p>";
}
echo "</div>";

echo "<h2>3. BASE API ドキュメント確認</h2>";
echo "<div style='background: #fff3cd; padding: 15px; border: 1px solid #ffeaa7; border-radius: 5px;'>";
echo "<p>BASE APIの公式ドキュメントを確認してください：</p>";
echo "<ul>";
echo "<li><a href='https://docs.thebase.in/api/' target='_blank'>BASE API 公式ドキュメント</a></li>";
echo "<li><a href='https://docs.thebase.in/api/oauth/authorize' target='_blank'>OAuth認証エンドポイント</a></li>";
echo "<li><a href='https://help.thebase.in/hc/ja/sections/8507567845017-BASE-API%E3%81%AB%E3%81%A4%E3%81%84%E3%81%A6' target='_blank'>BASE API ヘルプ</a></li>";
echo "</ul>";
echo "</div>";

echo "<h2>4. トラブルシューティング</h2>";
echo "<div style='background: #f8d7da; padding: 15px; border: 1px solid #f5c6cb; border-radius: 5px;'>";
echo "<h3>404エラーの原因</h3>";
echo "<ul>";
echo "<li><strong>認証エンドポイントのURLが間違っている</strong><br>→ 上記の複数のエンドポイントをテスト</li>";
echo "<li><strong>CLIENT_IDが無効</strong><br>→ BASE管理画面でアプリケーションが正しく登録されているか確認</li>";
echo "<li><strong>REDIRECT_URIが登録されていない</strong><br>→ BASE管理画面でリダイレクトURIが正しく設定されているか確認</li>";
echo "<li><strong>スコープが無効</strong><br>→ BASE管理画面でスコープが有効になっているか確認</li>";
echo "</ul>";
echo "</div>";
?>
