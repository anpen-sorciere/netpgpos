<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/base_practical_auto_manager.php';

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
    
    echo json_encode([
        'success' => true,
        'needs_auth' => true,
        'auth_url' => $auth_url,
        'required_scopes' => $needs_auth,
        'message' => '認証が必要です: ' . implode(', ', $needs_auth)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'needs_auth' => true
    ]);
}
?>
