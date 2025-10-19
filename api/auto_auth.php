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
    
    // 認証URLを生成
    $auth_url = $manager->getAuthUrl($needs_auth[0]); // 最初のスコープで認証
    
    // return_urlパラメータを追加
    $return_url = $_GET['return_url'] ?? '';
    if ($return_url) {
        $auth_url .= (strpos($auth_url, '?') !== false ? '&' : '?') . 'return_url=' . urlencode($return_url);
    }
    
    // デバッグ情報を追加
    $debug_info = [
        'received_return_url' => $return_url,
        'auth_url_with_return_url' => $auth_url,
        'get_params' => $_GET
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
