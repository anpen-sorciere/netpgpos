<?php
session_start();
require_once __DIR__ . '/../../common/config.php';
require_once __DIR__ . '/../../common/dbconnect.php';
require_once __DIR__ . '/../../common/functions.php';

echo "<h1>BASE API 認証状態チェック</h1>";

echo "<h2>セッション情報</h2>";
echo "base_access_token: " . (isset($_SESSION['base_access_token']) ? '設定済み (' . substr($_SESSION['base_access_token'], 0, 20) . '...)' : '未設定') . "<br>";
echo "base_refresh_token: " . (isset($_SESSION['base_refresh_token']) ? '設定済み (' . substr($_SESSION['base_refresh_token'], 0, 20) . '...)' : '未設定') . "<br>";
echo "base_token_expires: " . (isset($_SESSION['base_token_expires']) ? date('Y-m-d H:i:s', $_SESSION['base_token_expires']) . ' (残り' . ($_SESSION['base_token_expires'] - time()) . '秒)' : '未設定') . "<br>";

echo "<h2>設定情報</h2>";
echo "base_client_id: " . $base_client_id . "<br>";
echo "base_client_secret: " . substr($base_client_secret, 0, 10) . "...<br>";
echo "base_redirect_uri: " . $base_redirect_uri . "<br>";

echo "<h2>認証URL生成テスト</h2>";
require_once __DIR__ . '/classes/base_api_client.php';

$api_client = new BaseApiClient();
echo "認証が必要: " . ($api_client->needsAuth() ? 'はい' : 'いいえ') . "<br>";

if ($api_client->needsAuth()) {
    echo '<a href="' . $api_client->getAuthUrl() . '">BASE API認証を実行</a><br>';
} else {
    echo "<h2>APIテスト</h2>";
    try {
        $orders = $api_client->getOrders(5);
        echo "注文データ取得成功: " . count($orders) . "件<br>";
    } catch (Exception $e) {
        echo "APIテストエラー: " . $e->getMessage() . "<br>";
    }
}
?>