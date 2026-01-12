<?php
// 既存の動作するファイルをベースにしたテスト
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>動作テスト</h1>";

echo "<h2>1. 基本情報</h2>";
echo "現在時刻: " . date('Y-m-d H:i:s') . "<br>";
echo "PHP Version: " . phpversion() . "<br>";

echo "<h2>2. ファイル存在確認</h2>";
$config_path = __DIR__ . '/../../../common/config.php';
echo "config.phpのパス: " . $config_path . "<br>";
echo "ファイル存在確認: " . (file_exists($config_path) ? '存在' : '不存在') . "<br>";

echo "<h2>3. config.php読み込みテスト</h2>";
if (file_exists($config_path)) {
    echo "config.php発見: " . $config_path . "<br>";
    try {
        require_once $config_path;
        echo "config.php読み込み: 成功<br>";
        echo "base_client_id: " . (isset($base_client_id) ? $base_client_id : '未設定') . "<br>";
        echo "base_redirect_uri: " . (isset($base_redirect_uri) ? $base_redirect_uri : '未設定') . "<br>";
    } catch (Exception $e) {
        echo "config.php読み込みエラー: " . $e->getMessage() . "<br>";
    }
} else {
    echo "config.phpが見つかりません<br>";
}

echo "<h2>4. 権限グループテスト</h2>";
$scope_groups = [
    'orders_read' => [
        'name' => '注文確認',
        'description' => '注文の確認',
        'scopes' => ['read_orders'],
        'primary_scope' => 'read_orders'
    ],
    'items_read' => [
        'name' => '商品確認',
        'description' => '商品の確認', 
        'scopes' => ['read_items'],
        'primary_scope' => 'read_items'
    ]
];

foreach ($scope_groups as $group_key => $group) {
    echo "<div style='border: 1px solid #ccc; padding: 10px; margin: 10px 0;'>";
    echo "<h3>" . htmlspecialchars($group['name']) . "</h3>";
    echo "<p>" . htmlspecialchars($group['description']) . "</p>";
    echo "<p>スコープ: " . implode(', ', $group['scopes']) . "</p>";
    echo "<p>プライマリスコープ: " . htmlspecialchars($group['primary_scope']) . "</p>";
    echo "</div>";
}

echo "<h2>5. 認証URLテスト</h2>";
if (isset($base_client_id) && isset($base_redirect_uri)) {
    $auth_url = "https://api.thebase.in/1/oauth/authorize?" . http_build_query([
        'response_type' => 'code',
        'client_id' => $base_client_id,
        'redirect_uri' => $base_redirect_uri,
        'scope' => 'read_orders',
        'state' => 'read_orders'
    ]);
    
    echo "<p>認証URL生成: 成功</p>";
    echo "<p>URL: " . htmlspecialchars($auth_url) . "</p>";
    echo "<a href='" . htmlspecialchars($auth_url) . "' style='background-color: #007bff; color: white; padding: 8px 16px; text-decoration: none; border-radius: 4px;'>認証実行</a>";
} else {
    echo "<p style='color: red;'>認証URL生成: 失敗（設定情報が不完全）</p>";
}

echo "<h2>6. テスト完了</h2>";
echo "<p>すべてのテストが完了しました。</p>";
?>
