<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/base_api_client.php';

// セッション開始
session_start();

echo '<h2>本番環境 BASE API認証状況確認</h2>';

// 現在のセッション状況を表示
echo '<h3>現在のセッション状況:</h3>';
echo '<pre>';
echo 'base_access_token: ' . (isset($_SESSION['base_access_token']) ? '設定済み' : '未設定') . "\n";
echo 'base_token_expires: ' . (isset($_SESSION['base_token_expires']) ? date('Y-m-d H:i:s', $_SESSION['base_token_expires']) : '未設定') . "\n";
echo '現在時刻: ' . date('Y-m-d H:i:s') . "\n";
if (isset($_SESSION['base_token_expires'])) {
    $remaining = $_SESSION['base_token_expires'] - time();
    echo '残り時間: ' . ($remaining > 0 ? $remaining . '秒' : '期限切れ') . "\n";
}
echo '</pre>';

// BASE APIクライアントを作成
$api = new BaseApiClient();

echo '<h3>認証状況:</h3>';
if ($api->needsAuth()) {
    echo '<p style="color: red;">❌ 認証が必要です</p>';
    
    // 認証URLを生成
    $auth_url = $api->getAuthUrl();
    echo '<h3>認証URL:</h3>';
    echo '<p><a href="' . htmlspecialchars($auth_url) . '" target="_blank" style="background-color: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px;">BASE API認証を実行</a></p>';
    echo '<p><small>認証URL: ' . htmlspecialchars($auth_url) . '</small></p>';
} else {
    echo '<p style="color: green;">✅ 認証済み</p>';
    
    // テスト用に注文データを取得してみる
    try {
        $orders = $api->getOrders(1, 0);
        echo '<p style="color: green;">✅ API接続テスト成功</p>';
        echo '<p>取得した注文数: ' . count($orders['orders'] ?? []) . '</p>';
    } catch (Exception $e) {
        echo '<p style="color: red;">❌ API接続テスト失敗: ' . htmlspecialchars($e->getMessage()) . '</p>';
    }
}

echo '<hr>';
echo '<h3>設定情報:</h3>';
echo '<pre>';
echo 'Client ID: ' . htmlspecialchars($base_client_id ?? 'N/A') . "\n";
echo 'Redirect URI: ' . htmlspecialchars($base_redirect_uri ?? 'N/A') . "\n";
echo 'API URL: ' . htmlspecialchars($base_api_url ?? 'N/A') . "\n";
echo '</pre>';

echo '<hr>';
echo '<p><a href="order_monitor.php">注文監視画面に戻る</a></p>';
?>
