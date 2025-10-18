<?php
// 認証URL生成デバッグ
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>BASE API 認証URL生成デバッグ</h1>";

echo "<h2>1. 設定値確認</h2>";
require_once 'config.php';
echo "client_id: " . (isset($base_client_id) ? $base_client_id : '未設定') . "<br>";
echo "client_secret: " . (isset($base_client_secret) ? substr($base_client_secret, 0, 10) . '...' : '未設定') . "<br>";
echo "redirect_uri: " . (isset($base_redirect_uri) ? $base_redirect_uri : '未設定') . "<br>";

echo "<h2>2. 認証URL生成</h2>";
$params = [
    'response_type' => 'code',
    'client_id' => $base_client_id,
    'redirect_uri' => $base_redirect_uri,
    'scope' => 'read_orders'
];

echo "パラメーター:<br>";
foreach ($params as $key => $value) {
    echo "- $key: " . htmlspecialchars($value) . "<br>";
}

$authUrl = 'https://api.thebase.in/1/oauth/authorize?' . http_build_query($params);
echo "<br>生成された認証URL:<br>";
echo '<a href="' . htmlspecialchars($authUrl) . '" target="_blank" style="background: #007bff; color: white; padding: 10px; text-decoration: none; display: inline-block; margin: 10px 0;">BASE API認証を開始（本番用）</a><br>';
echo '<br><textarea style="width: 100%; height: 100px;">' . htmlspecialchars($authUrl) . '</textarea>';

echo "<h2>3. 簡易認証URL（本番用）</h2>";
$simpleUrl = 'https://api.thebase.in/1/oauth/authorize?response_type=code&client_id=' . $base_client_id . '&redirect_uri=' . urlencode($base_redirect_uri) . '&scope=read_orders';
echo '<a href="' . htmlspecialchars($simpleUrl) . '" target="_blank" style="background: #28a745; color: white; padding: 10px; text-decoration: none; display: inline-block; margin: 10px 0;">簡易認証URL（本番用）</a><br>';
echo '<br><textarea style="width: 100%; height: 100px;">' . htmlspecialchars($simpleUrl) . '</textarea>';

echo "<h2>4. 代替スコープテスト</h2>";
$alternative_scopes = [
    'read_orders',
    'read_items', 
    'read_users',
    'read_users_mail',
    'read_savings',
    'write_users',
    'write_items',
    'write_orders',
    'read_orders,read_items',
    'read_orders,read_users',
    'read_items,read_users',
    'read_orders,read_items,read_users'
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
    echo '<a href="' . htmlspecialchars($test_url) . '" target="_blank" style="background: #28a745; color: white; padding: 5px; text-decoration: none;">テストURL</a></p>';
}

echo "<h2>4. 注意事項</h2>";
echo "<ul>";
echo "<li>BASE API登録画面でコールバックURLを <code>" . htmlspecialchars($base_redirect_uri) . "</code> に設定してください</li>";
echo "<li>認証後、コールバックページで認証コードが表示されるはずです</li>";
echo "<li>BASE APIでは単一スコープのみ使用可能です（組み合わせ不可）</li>";
echo "<li>利用可能なスコープ: read_orders, read_items, read_users, read_users_mail</li>";
echo "</ul>";
?>
