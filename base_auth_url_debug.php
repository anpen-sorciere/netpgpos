<?php
// 認証URLデバッグファイル
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>BASE API 認証URL デバッグ</h1>";

echo "<h2>1. 設定値確認</h2>";
require_once '../common/config.php';
echo "client_id: " . (isset($base_client_id) ? $base_client_id : '未設定') . "<br>";
echo "client_secret: " . (isset($base_client_secret) ? substr($base_client_secret, 0, 10) . '...' : '未設定') . "<br>";
echo "redirect_uri: " . (isset($base_redirect_uri) ? $base_redirect_uri : '未設定') . "<br>";

echo "<h2>2. 認証URL生成</h2>";
$params = [
    'response_type' => 'code',
    'client_id' => $base_client_id,
    'redirect_uri' => $base_redirect_uri,
    'scope' => 'read_orders read_products read_shop'
];

echo "パラメーター:<br>";
foreach ($params as $key => $value) {
    echo "- $key: " . htmlspecialchars($value) . "<br>";
}

$authUrl = 'https://api.thebase.in/1/oauth/authorize?' . http_build_query($params);
echo "<br>生成された認証URL:<br>";
echo '<a href="' . htmlspecialchars($authUrl) . '" target="_blank">' . htmlspecialchars($authUrl) . '</a>';

echo "<h2>3. 代替スコープテスト</h2>";
$alternative_scopes = [
    'read_orders,read_products,read_shop',
    'read_orders read_products read_shop',
    'read_orders',
    'read_products',
    'read_shop'
];

foreach ($alternative_scopes as $scope) {
    $test_params = [
        'response_type' => 'code',
        'client_id' => $base_client_id,
        'redirect_uri' => $base_redirect_uri,
        'scope' => $scope
    ];
    $test_url = 'https://api.thebase.in/1/oauth/authorize?' . http_build_query($test_params);
    echo "<p><strong>スコープ:</strong> $scope<br>";
    echo '<a href="' . htmlspecialchars($test_url) . '" target="_blank">テストURL</a></p>';
}
?>
