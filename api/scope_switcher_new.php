<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/base_practical_auto_manager.php';

// セッション開始
session_start();

echo '<h2>BASE API 権限切り替えシステム</h2>';

// 現在のセッション状況を表示
echo '<h3>現在のセッション状況:</h3>';
echo '<pre>';
echo 'base_access_token: ' . (isset($_SESSION['base_access_token']) ? '設定済み' : '未設定') . "\n";
echo 'base_refresh_token: ' . (isset($_SESSION['base_refresh_token']) ? '設定済み' : '未設定') . "\n";
echo 'base_token_expires: ' . (isset($_SESSION['base_token_expires']) ? date('Y-m-d H:i:s', $_SESSION['base_token_expires']) : '未設定') . "\n";
echo '現在時刻: ' . date('Y-m-d H:i:s') . "\n";
if (isset($_SESSION['base_token_expires'])) {
    $remaining = $_SESSION['base_token_expires'] - time();
    echo '残り時間: ' . ($remaining > 0 ? $remaining . '秒' : '期限切れ') . "\n";
}
echo '</pre>';

// 権限の定義（用途別にグループ化）
$scope_groups = [
    'orders_read' => [
        'name' => '注文確認',
        'description' => '注文の確認',
        'scopes' => ['read_orders'],
        'primary_scope' => 'read_orders',
        'test_endpoint' => 'orders?limit=1&offset=0'
    ],
    'orders_write' => [
        'name' => '注文更新',
        'description' => '注文の更新',
        'scopes' => ['write_orders'],
        'primary_scope' => 'write_orders',
        'test_endpoint' => 'orders/edit_status'
    ],
    'items_read' => [
        'name' => '商品確認',
        'description' => '商品の確認',
        'scopes' => ['read_items'],
        'primary_scope' => 'read_items',
        'test_endpoint' => 'items?limit=1&offset=0'
    ],
    'items_write' => [
        'name' => '商品更新',
        'description' => '商品の更新',
        'scopes' => ['write_items'],
        'primary_scope' => 'write_items',
        'test_endpoint' => 'items/edit'
    ],
    'shop' => [
        'name' => 'ショップ管理',
        'description' => 'ショップ情報の確認',
        'scopes' => ['read_users'],
        'primary_scope' => 'read_users',
        'test_endpoint' => 'users/me'
    ],
    'mail' => [
        'name' => 'メール管理',
        'description' => 'ショップメールアドレスの確認',
        'scopes' => ['read_users_mail'],
        'primary_scope' => 'read_users_mail',
        'test_endpoint' => 'users/me'
    ],
    'financial' => [
        'name' => '財務管理',
        'description' => '振込申請情報の確認',
        'scopes' => ['read_savings'],
        'primary_scope' => 'read_savings',
        'test_endpoint' => 'savings'
    ]
];

// 権限テスト機能
function testScopeGroup($group_key, $endpoint) {
    try {
        $manager = new BasePracticalAutoManager();
        // group_keyからprimary_scopeを取得して使用
        global $scope_groups;
        $scope = $scope_groups[$group_key]['primary_scope'];
        $response = $manager->getDataWithAutoAuth($scope, $endpoint);
        return ['success' => true, 'message' => 'OK'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

// 権限テストの実行
if (isset($_GET['test']) && isset($scope_groups[$_GET['test']])) {
    $group_key = $_GET['test'];
    $group = $scope_groups[$group_key];
    $result = testScopeGroup($group_key, $group['test_endpoint']);
    
    echo '<div style="background-color: ' . ($result['success'] ? '#d4edda' : '#f8d7da') . '; padding: 15px; border-radius: 8px; margin: 20px 0;">';
    echo '<h4>' . $group['name'] . ' テスト結果</h4>';
    echo '<p><strong>結果:</strong> ' . ($result['success'] ? '✅ 成功' : '❌ 失敗') . '</p>';
    echo '<p><strong>メッセージ:</strong> ' . htmlspecialchars($result['message']) . '</p>';
    echo '</div>';
}

echo '<h3>用途別権限認証:</h3>';
echo '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 15px; margin: 20px 0;">';

foreach ($scope_groups as $group_key => $group) {
    echo '<div style="border: 1px solid #dee2e6; border-radius: 8px; padding: 15px; background-color: #f8f9fa;">';
    echo '<h4 style="margin-top: 0; color: #2c3e50;">' . htmlspecialchars($group['name']) . '</h4>';
    echo '<p style="color: #6c757d; font-size: 0.9em;">' . htmlspecialchars($group['description']) . '</p>';
    
    // 含まれる権限を表示
    echo '<p style="font-size: 0.8em; color: #495057;"><strong>含まれる権限:</strong> ' . implode(', ', $group['scopes']) . '</p>';
    
    // 認証ボタン（主要権限のみ）
    $auth_url = "https://api.thebase.in/1/oauth/authorize?" . http_build_query([
        'response_type' => 'code',
        'client_id' => $base_client_id,
        'redirect_uri' => $base_redirect_uri,
        'scope' => $group['primary_scope'],
        'state' => $group['primary_scope']  // 実際のスコープ名をstateパラメータで渡す
    ]);
    
    // デバッグ: 認証URLを表示
    echo '<div style="background-color: #f8f9fa; padding: 10px; margin: 10px 0; border-radius: 4px; font-size: 0.8em;">';
    echo '<strong>デバッグ - 認証URL:</strong><br>';
    echo '<code>' . htmlspecialchars($auth_url) . '</code>';
    echo '</div>';
    
    echo '<a href="' . htmlspecialchars($auth_url) . '" style="background-color: #007bff; color: white; padding: 8px 16px; text-decoration: none; border-radius: 4px; display: inline-block; margin-right: 10px;">認証実行</a>';
    
    // テストボタン
    echo '<a href="?test=' . urlencode($group_key) . '" style="background-color: #28a745; color: white; padding: 8px 16px; text-decoration: none; border-radius: 4px; display: inline-block;">テスト実行</a>';
    
    echo '</div>';
}

echo '</div>';

// 現在の認証状況
echo '<h3>現在の認証状況:</h3>';
try {
    $manager = new BasePracticalAutoManager();
    $auth_status = $manager->getAuthStatus();
    
    $has_valid_auth = false;
    foreach ($auth_status as $scope => $status) {
        if ($status['authenticated'] && $status['access_valid']) {
            $has_valid_auth = true;
            break;
        }
    }
    
    if (!$has_valid_auth) {
        echo '<p style="color: red;">❌ 認証が必要です</p>';
    } else {
        echo '<p style="color: green;">✅ 認証済み</p>';
        
        // 各権限グループのテスト
        echo '<h4>権限グループテスト結果:</h4>';
        echo '<table style="width: 100%; border-collapse: collapse; margin: 10px 0;">';
        echo '<thead><tr style="background-color: #e9ecef;"><th style="padding: 8px; border: 1px solid #dee2e6;">権限グループ</th><th style="padding: 8px; border: 1px solid #dee2e6;">結果</th><th style="padding: 8px; border: 1px solid #dee2e6;">メッセージ</th></tr></thead>';
        echo '<tbody>';
        
        foreach ($scope_groups as $group_key => $group) {
            $result = testScopeGroup($group_key, $group['test_endpoint']);
            echo '<tr>';
            echo '<td style="padding: 8px; border: 1px solid #dee2e6;">' . htmlspecialchars($group['name']) . '</td>';
            echo '<td style="padding: 8px; border: 1px solid #dee2e6; color: ' . ($result['success'] ? 'green' : 'red') . ';">' . ($result['success'] ? '✅ OK' : '❌ NG') . '</td>';
            echo '<td style="padding: 8px; border: 1px solid #dee2e6; font-size: 0.8em;">' . htmlspecialchars($result['message']) . '</td>';
            echo '</tr>';
        }
        
        echo '</tbody>';
        echo '</table>';
    }
} catch (Exception $e) {
    echo '<p style="color: red;">❌ エラー: ' . htmlspecialchars($e->getMessage()) . '</p>';
}

// 使用上の注意
echo '<div style="background-color: #fff3cd; padding: 15px; border-radius: 8px; margin: 20px 0;">';
echo '<h4>⚠️ 使用上の注意</h4>';
echo '<ul>';
echo '<li><strong>権限の排他性:</strong> 新しい権限で認証すると、前の権限は無効になります</li>';
echo '<li><strong>用途別認証:</strong> 必要な機能に応じて権限を切り替えてください</li>';
echo '<li><strong>注文監視:</strong> 注文監視画面を使用する場合は「注文管理」権限が必要です</li>';
echo '<li><strong>商品管理:</strong> 商品情報を確認する場合は「商品管理」権限が必要です</li>';
echo '</ul>';
echo '</div>';

// クイックアクセス
echo '<h3>クイックアクセス:</h3>';
echo '<div style="display: flex; gap: 10px; margin: 20px 0;">';
echo '<a href="order_monitor.php" style="background-color: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px;">注文監視画面</a>';
echo '<a href="auth_status_check.php" style="background-color: #6c757d; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px;">認証状況確認</a>';
echo '<a href="scope_auth_manager.php" style="background-color: #17a2b8; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px;">個別権限認証</a>';
echo '</div>';

echo '<hr>';
echo '<h3>設定情報:</h3>';
echo '<pre>';
echo 'Client ID: ' . htmlspecialchars($base_client_id ?? 'N/A') . "\n";
echo 'Redirect URI: ' . htmlspecialchars($base_redirect_uri ?? 'N/A') . "\n";
echo 'API URL: ' . htmlspecialchars($base_api_url ?? 'N/A') . "\n";
echo '</pre>';
?>
