<?php
// シンプルなデバッグファイル
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>BASE API デバッグ</h1>";

echo "<h2>1. 基本情報</h2>";
echo "utype: " . ($_GET['utype'] ?? '未設定') . "<br>";

echo "<h2>2. ファイル存在確認</h2>";
echo "api/base_api_client.php: " . (file_exists('api/base_api_client.php') ? '存在' : '不存在') . "<br>";
echo "../common/config.php: " . (file_exists('../common/config.php') ? '存在' : '不存在') . "<br>";

echo "<h2>3. config.php読み込みテスト</h2>";
try {
    require_once '../common/config.php';
    echo "config.php読み込み: 成功<br>";
    echo "base_client_id: " . (isset($base_client_id) ? $base_client_id : '未設定') . "<br>";
    echo "base_client_secret: " . (isset($base_client_secret) ? substr($base_client_secret, 0, 10) . '...' : '未設定') . "<br>";
    echo "base_redirect_uri: " . (isset($base_redirect_uri) ? $base_redirect_uri : '未設定') . "<br>";
} catch (Exception $e) {
    echo "config.php読み込みエラー: " . $e->getMessage() . "<br>";
}

echo "<h2>4. BaseApiClient読み込みテスト</h2>";
try {
    require_once 'api/base_api_client.php';
    echo "BaseApiClient読み込み: 成功<br>";
    
    $baseApi = new BaseApiClient();
    echo "BaseApiClientインスタンス作成: 成功<br>";
    
    $needsAuth = $baseApi->needsAuth();
    echo "needsAuth(): " . ($needsAuth ? 'true' : 'false') . "<br>";
    
    if ($needsAuth) {
        echo "<h3>5. 認証URL生成テスト</h3>";
        try {
            $authUrl = $baseApi->getAuthUrl();
            echo "認証URL生成: 成功<br>";
            echo "認証URL: " . htmlspecialchars($authUrl) . "<br>";
            echo '<a href="' . htmlspecialchars($authUrl) . '" style="background: #007bff; color: white; padding: 10px; text-decoration: none;">BASE API認証を開始</a>';
        } catch (Exception $e) {
            echo "認証URL生成エラー: " . $e->getMessage() . "<br>";
            echo "ファイル: " . $e->getFile() . "<br>";
            echo "行: " . $e->getLine() . "<br>";
        }
    } else {
        echo "認証済みです<br>";
    }
    
} catch (Exception $e) {
    echo "BaseApiClientエラー: " . $e->getMessage() . "<br>";
    echo "ファイル: " . $e->getFile() . "<br>";
    echo "行: " . $e->getLine() . "<br>";
}

echo "<h2>6. セッション情報</h2>";
session_start();
echo "セッション開始: 成功<br>";
echo "base_access_token: " . (isset($_SESSION['base_access_token']) ? '設定済み' : '未設定') . "<br>";
?>
