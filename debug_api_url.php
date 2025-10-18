<?php
/**
 * APIリクエストURL確認スクリプト
 * 実際のAPIリクエストURLを確認
 */
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/api/base_practical_auto_manager.php';

echo "<h1>APIリクエストURL確認</h1>";
echo "<p>実際のAPIリクエストURLを確認します</p>";

try {
    $practical_manager = new BasePracticalAutoManager();
    
    echo "<h2>1. 設定値の確認</h2>";
    echo "<div style='background: #f0f0f0; padding: 15px; border: 1px solid #ccc; border-radius: 5px;'>";
    echo "<p><strong>BASE_API_URL:</strong> " . (isset($base_api_url) ? $base_api_url : '未設定') . "</p>";
    echo "</div>";
    
    echo "<h2>2. 認証状態の確認</h2>";
    $auth_status = $practical_manager->getAuthStatus();
    
    foreach (['orders_only', 'items_only'] as $scope) {
        $status = $auth_status[$scope];
        $authenticated = $status['authenticated'] ? '✓ 認証済み' : '✗ 未認証';
        echo "<p><strong>{$scope}:</strong> {$authenticated}</p>";
    }
    
    echo "<h2>3. APIリクエストURLの確認</h2>";
    
    // orders_only でテスト
    if ($auth_status['orders_only']['authenticated']) {
        echo "<h3>orders_only スコープでのAPIリクエスト</h3>";
        
        $token_data = $practical_manager->getScopeToken('orders_only');
        $access_token = $token_data['access_token'];
        
        $endpoint = '/orders';
        $params = ['limit' => 1];
        $url = rtrim($base_api_url, '/') . '/' . ltrim($endpoint, '/');
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }
        
        echo "<div style='background: #e8f4fd; padding: 15px; border: 1px solid #b3d9ff; border-radius: 5px;'>";
        echo "<p><strong>エンドポイント:</strong> {$endpoint}</p>";
        echo "<p><strong>パラメータ:</strong> " . json_encode($params) . "</p>";
        echo "<p><strong>完全URL:</strong> <a href='{$url}' target='_blank' style='word-break: break-all;'>{$url}</a></p>";
        echo "<p><strong>アクセストークン:</strong> " . substr($access_token, 0, 20) . "...</p>";
        echo "</div>";
        
        // 実際のAPIリクエストをテスト
        echo "<h3>実際のAPIリクエストテスト</h3>";
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $access_token
            ]
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        echo "<div style='background: #f9f9f9; padding: 15px; border: 1px solid #ddd; border-radius: 5px;'>";
        echo "<p><strong>HTTPステータス:</strong> {$http_code}</p>";
        
        if ($curl_error) {
            echo "<p><strong>cURLエラー:</strong> {$curl_error}</p>";
        }
        
        if ($response) {
            echo "<p><strong>レスポンス:</strong></p>";
            echo "<pre style='background: white; padding: 10px; border: 1px solid #ccc; border-radius: 3px; overflow-x: auto; max-height: 300px;'>";
            echo htmlspecialchars($response);
            echo "</pre>";
        }
        echo "</div>";
        
    } else {
        echo "<p style='color: red;'>orders_only スコープが認証されていません</p>";
    }
    
} catch (Exception $e) {
    echo "<div style='color: red; background: #f8d7da; padding: 15px; border: 1px solid #f5c6cb; border-radius: 5px;'>";
    echo "<h3>エラーが発生しました</h3>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
}
?>
