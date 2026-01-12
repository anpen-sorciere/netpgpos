<?php
echo "<h1>PHP動作テスト</h1>";
echo "<p>現在時刻: " . date('Y-m-d H:i:s') . "</p>";

echo "<h2>基本情報:</h2>";
echo "<p>PHP Version: " . phpversion() . "</p>";
echo "<p>現在のディレクトリ: " . getcwd() . "</p>";
echo "<p>スクリプトのパス: " . __FILE__ . "</p>";

echo "<h2>ファイル存在確認:</h2>";
$config_path = __DIR__ . '/../config.php';
echo "<p>config.phpのパス: " . $config_path . "</p>";
echo "<p>ファイル存在確認: " . (file_exists($config_path) ? '存在' : '不存在') . "</p>";

if (file_exists($config_path)) {
    echo "<p style='color: green;'>config.phpが見つかりました</p>";
    try {
        require_once $config_path;
        echo "<p>config.php読み込み: 成功</p>";
        echo "<p>Client ID: " . (isset($base_client_id) ? htmlspecialchars($base_client_id) : '未設定') . "</p>";
        echo "<p>Redirect URI: " . (isset($base_redirect_uri) ? htmlspecialchars($base_redirect_uri) : '未設定') . "</p>";
    } catch (Exception $e) {
        echo "<p style='color: red;'>config.php読み込みエラー: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
} else {
    echo "<p style='color: red;'>config.phpが見つかりません</p>";
}

echo "<h2>権限グループテスト:</h2>";
$scope_groups = [
    'orders_read' => [
        'name' => '注文確認',
        'description' => '注文の確認',
        'scopes' => ['read_orders'],
        'primary_scope' => 'read_orders'
    ]
];

foreach ($scope_groups as $group_key => $group) {
    echo "<div style='border: 1px solid #ccc; padding: 10px; margin: 10px 0;'>";
    echo "<h3>" . htmlspecialchars($group['name']) . "</h3>";
    echo "<p>" . htmlspecialchars($group['description']) . "</p>";
    echo "<p>スコープ: " . implode(', ', $group['scopes']) . "</p>";
    echo "</div>";
}

echo "<p>テスト完了</p>";
?>
