<?php
echo "<h1>スコープテスト</h1>";
echo "<p>現在時刻: " . date('Y-m-d H:i:s') . "</p>";

// 権限の定義
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

echo "<h2>権限グループ一覧:</h2>";
foreach ($scope_groups as $group_key => $group) {
    echo "<div style='border: 1px solid #ccc; padding: 10px; margin: 10px 0;'>";
    echo "<h3>" . htmlspecialchars($group['name']) . "</h3>";
    echo "<p>" . htmlspecialchars($group['description']) . "</p>";
    echo "<p>スコープ: " . implode(', ', $group['scopes']) . "</p>";
    echo "<p>プライマリスコープ: " . htmlspecialchars($group['primary_scope']) . "</p>";
    echo "</div>";
}

echo "<h2>設定情報:</h2>";
echo "<p>config.phpのパス: " . __DIR__ . '/../config.php' . "</p>";
echo "<p>ファイル存在確認: " . (file_exists(__DIR__ . '/../config.php') ? '存在' : '不存在') . "</p>";

if (file_exists(__DIR__ . '/../config.php')) {
    require_once __DIR__ . '/../config.php';
    echo "<p>Client ID: " . htmlspecialchars($base_client_id ?? 'N/A') . "</p>";
    echo "<p>Redirect URI: " . htmlspecialchars($base_redirect_uri ?? 'N/A') . "</p>";
    echo "<p>Host: " . htmlspecialchars($host ?? 'N/A') . "</p>";
    echo "<p>User: " . htmlspecialchars($user ?? 'N/A') . "</p>";
    echo "<p>DB Name: " . htmlspecialchars($dbname ?? 'N/A') . "</p>";
} else {
    echo "<p style='color: red;'>config.phpが見つかりません</p>";
}
?>
