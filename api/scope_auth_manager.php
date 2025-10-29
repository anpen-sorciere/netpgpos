<?php
require_once __DIR__ . '/../common/config.php';
require_once __DIR__ . '/../common/dbconnect.php';
require_once __DIR__ . '/../common/functions.php';
require_once __DIR__ . '/base_api_client.php';

// セッション開始
session_start();

echo '<h2>BASE API 個別権限認証システム</h2>';

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

// 各権限の定義
$scopes = [
    'read_orders' => [
        'name' => '注文情報を見る',
        'description' => '注文一覧・詳細の取得',
        'test_endpoint' => 'orders?limit=1&offset=0'
    ],
    'read_items' => [
        'name' => '商品情報を見る',
        'description' => '商品一覧・詳細の取得',
        'test_endpoint' => 'items?limit=1&offset=0'
    ],
    'read_users' => [
        'name' => 'ショップ情報を見る',
        'description' => 'ショップ情報の取得',
        'test_endpoint' => 'users/me'
    ],
    'read_users_mail' => [
        'name' => 'ショップのメールアドレスを見る',
        'description' => 'ショップメールアドレスの取得',
        'test_endpoint' => 'users/me'
    ],
    'read_savings' => [
        'name' => '引き出し申請情報を見る',
        'description' => '振込申請情報の取得',
        'test_endpoint' => 'savings'
    ],
    'write_users' => [
        'name' => 'ショップ情報を更新する',
        'description' => 'ショップ情報の更新',
        'test_endpoint' => 'users/me'
    ],
    'write_items' => [
        'name' => '商品情報を更新する',
        'description' => '商品情報の更新',
        'test_endpoint' => 'items?limit=1&offset=0'
    ],
    'write_orders' => [
        'name' => '注文情報を更新する',
        'description' => '注文ステータスの更新',
        'test_endpoint' => 'orders?limit=1&offset=0'
    ]
];

// 権限テスト機能
function testScope($scope, $endpoint) {
    try {
        $api = new BaseApiClient();
        $response = $api->makeRequest($endpoint);
        return ['success' => true, 'message' => 'OK'];
    } catch (Exception $e) {
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

// 権限テストの実行
if (isset($_GET['test']) && isset($scopes[$_GET['test']])) {
    $scope = $_GET['test'];
    $result = testScope($scope, $scopes[$scope]['test_endpoint']);
    
    echo '<div style="background-color: ' . ($result['success'] ? '#d4edda' : '#f8d7da') . '; padding: 15px; border-radius: 8px; margin: 20px 0;">';
    echo '<h4>' . $scopes[$scope]['name'] . ' テスト結果</h4>';
    echo '<p><strong>結果:</strong> ' . ($result['success'] ? '✅ 成功' : '❌ 失敗') . '</p>';
    echo '<p><strong>メッセージ:</strong> ' . htmlspecialchars($result['message']) . '</p>';
    echo '</div>';
}

echo '<h3>権限別認証ボタン:</h3>';
echo '<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 15px; margin: 20px 0;">';

foreach ($scopes as $scope => $info) {
    echo '<div style="border: 1px solid #dee2e6; border-radius: 8px; padding: 15px; background-color: #f8f9fa;">';
    echo '<h4 style="margin-top: 0; color: #2c3e50;">' . htmlspecialchars($info['name']) . '</h4>';
    echo '<p style="color: #6c757d; font-size: 0.9em;">' . htmlspecialchars($info['description']) . '</p>';
    
    // 認証ボタン
    $auth_url = "https://api.thebase.in/1/oauth/authorize?" . http_build_query([
        'response_type' => 'code',
        'client_id' => $base_client_id,
        'redirect_uri' => $base_redirect_uri,
        'scope' => $scope
    ]);
    
    echo '<a href="' . htmlspecialchars($auth_url) . '" style="background-color: #007bff; color: white; padding: 8px 16px; text-decoration: none; border-radius: 4px; display: inline-block; margin-right: 10px;">認証実行</a>';
    
    // テストボタン
    echo '<a href="?test=' . urlencode($scope) . '" style="background-color: #28a745; color: white; padding: 8px 16px; text-decoration: none; border-radius: 4px; display: inline-block;">テスト実行</a>';
    
    echo '</div>';
}

echo '</div>';

// 全権限認証ボタン
echo '<h3>全権限認証:</h3>';
$all_scopes = implode(',', array_keys($scopes));
$all_auth_url = "https://api.thebase.in/1/oauth/authorize?" . http_build_query([
    'response_type' => 'code',
    'client_id' => $base_client_id,
    'redirect_uri' => $base_redirect_uri,
    'scope' => $all_scopes
]);

echo '<div style="background-color: #fff3cd; padding: 15px; border-radius: 8px; margin: 20px 0;">';
echo '<h4>全権限を一度に認証</h4>';
echo '<p>全ての権限を一度に取得します（失敗する可能性があります）</p>';
echo '<a href="' . htmlspecialchars($all_auth_url) . '" style="background-color: #ffc107; color: #212529; padding: 10px 20px; text-decoration: none; border-radius: 4px; font-weight: bold;">全権限認証実行</a>';
echo '</div>';

// 現在の認証状況
echo '<h3>現在の認証状況:</h3>';
try {
    $api = new BaseApiClient();
    
    if ($api->needsAuth()) {
        echo '<p style="color: red;">❌ 認証が必要です</p>';
    } else {
        echo '<p style="color: green;">✅ 認証済み</p>';
        
        // 各権限のテスト
        echo '<h4>権限テスト結果:</h4>';
        echo '<table style="width: 100%; border-collapse: collapse; margin: 10px 0;">';
        echo '<thead><tr style="background-color: #e9ecef;"><th style="padding: 8px; border: 1px solid #dee2e6;">権限</th><th style="padding: 8px; border: 1px solid #dee2e6;">結果</th><th style="padding: 8px; border: 1px solid #dee2e6;">メッセージ</th></tr></thead>';
        echo '<tbody>';
        
        foreach ($scopes as $scope => $info) {
            $result = testScope($scope, $info['test_endpoint']);
            echo '<tr>';
            echo '<td style="padding: 8px; border: 1px solid #dee2e6;">' . htmlspecialchars($info['name']) . '</td>';
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

echo '<hr>';
echo '<h3>設定情報:</h3>';
echo '<pre>';
echo 'Client ID: ' . htmlspecialchars($base_client_id ?? 'N/A') . "\n";
echo 'Redirect URI: ' . htmlspecialchars($base_redirect_uri ?? 'N/A') . "\n";
echo 'API URL: ' . htmlspecialchars($base_api_url ?? 'N/A') . "\n";
echo '</pre>';

echo '<hr>';
echo '<p><a href="order_monitor.php">注文監視画面に戻る</a> | <a href="auth_status_check.php">認証状況確認に戻る</a></p>';
?>
