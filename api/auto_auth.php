<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/base_practical_auto_manager.php';

// HTMLデバッグモード
if (isset($_GET['debug']) && $_GET['debug'] === 'html') {
    header('Content-Type: text/html; charset=UTF-8');
    echo '<h2>auto_auth.php デバッグ</h2>';
    echo '<h3>受信パラメータ:</h3>';
    echo '<pre>' . htmlspecialchars(print_r($_GET, true)) . '</pre>';
    echo '<h3>return_url パラメータ:</h3>';
    echo '<p>' . htmlspecialchars($_GET['return_url'] ?? '未設定') . '</p>';
    echo '<h3>scopes パラメータ:</h3>';
    echo '<p>' . htmlspecialchars($_GET['scopes'] ?? '未設定') . '</p>';
    
    // 実際の認証URL生成をテスト
    try {
        $manager = new BasePracticalAutoManager();
        $required_scopes = $_GET['scopes'] ?? 'read_orders,read_items';
        $scopes_array = explode(',', $required_scopes);
        
        $needs_auth = [];
        foreach ($scopes_array as $scope) {
            $scope = trim($scope);
            if (!$manager->isTokenValid($scope)) {
                $needs_auth[] = $scope;
            }
        }
        
        if (!empty($needs_auth)) {
            $return_url = $_GET['return_url'] ?? '';
            $auth_url = $manager->getAuthUrl($needs_auth[0], $return_url);
            
            echo '<h3>生成された認証URL:</h3>';
            echo '<p><a href="' . htmlspecialchars($auth_url) . '" target="_blank">' . htmlspecialchars($auth_url) . '</a></p>';
        } else {
            echo '<h3>認証不要:</h3>';
            echo '<p>全てのスコープが有効です</p>';
        }
    } catch (Exception $e) {
        echo '<h3>エラー:</h3>';
        echo '<p style="color: red;">' . htmlspecialchars($e->getMessage()) . '</p>';
    }
    
    exit;
}

header('Content-Type: application/json');

try {
    $manager = new BasePracticalAutoManager();
    
    // 必要なスコープを取得
    $required_scopes = $_GET['scopes'] ?? 'read_orders,read_items';
    $scopes_array = explode(',', $required_scopes);
    
    // 認証が必要なスコープを特定
    $needs_auth = [];
    foreach ($scopes_array as $scope) {
        $scope = trim($scope);
        if (!$manager->isTokenValid($scope)) {
            $needs_auth[] = $scope;
        }
    }
    
    if (empty($needs_auth)) {
        echo json_encode([
            'success' => true,
            'message' => '全てのスコープが有効です',
            'needs_auth' => false
        ]);
        exit;
    }
    
    // return_urlパラメータを取得
    $return_url = $_GET['return_url'] ?? '';
    
    // 認証URLを生成（return_urlをstateに含める）
    $auth_url = $manager->getAuthUrl($needs_auth[0], $return_url);
    
    // デバッグ情報を追加
    $debug_info = [
        'received_return_url' => $return_url,
        'auth_url_before_return_url' => $manager->getAuthUrl($needs_auth[0]),
        'auth_url_with_return_url' => $auth_url,
        'get_params' => $_GET,
        'needs_auth_scopes' => $needs_auth
    ];
    
    echo json_encode([
        'success' => true,
        'needs_auth' => true,
        'auth_url' => $auth_url,
        'required_scopes' => $needs_auth,
        'message' => '認証が必要です: ' . implode(', ', $needs_auth),
        'debug_info' => $debug_info
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'needs_auth' => true
    ]);
}
?>
