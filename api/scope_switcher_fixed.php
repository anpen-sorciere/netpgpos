<?php
// BASE API 権限切り替えシステム（動作するファイルをベースに作成）
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>BASE API 権限切り替えシステム</h1>";

echo "<h2>1. 基本情報</h2>";
echo "現在時刻: " . date('Y-m-d H:i:s') . "<br>";
echo "PHP Version: " . phpversion() . "<br>";

echo "<h2>2. ファイル存在確認</h2>";
$config_path = __DIR__ . '/../config.php';
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

echo "<h2>4. 権限グループ定義</h2>";
$scope_groups = [
    'orders_read' => [
        'name' => '注文確認',
        'description' => '注文の確認',
        'scopes' => ['read_orders'],
        'primary_scope' => 'read_orders'
    ],
    'orders_write' => [
        'name' => '注文更新',
        'description' => '注文の更新',
        'scopes' => ['write_orders'],
        'primary_scope' => 'write_orders'
    ],
    'items_read' => [
        'name' => '商品確認',
        'description' => '商品の確認',
        'scopes' => ['read_items'],
        'primary_scope' => 'read_items'
    ],
    'items_write' => [
        'name' => '商品更新',
        'description' => '商品の更新',
        'scopes' => ['write_items'],
        'primary_scope' => 'write_items'
    ],
    'shop' => [
        'name' => 'ショップ管理',
        'description' => 'ショップ情報の確認',
        'scopes' => ['read_users'],
        'primary_scope' => 'read_users'
    ],
    'mail' => [
        'name' => 'メール管理',
        'description' => 'ショップメールアドレスの確認',
        'scopes' => ['read_users_mail'],
        'primary_scope' => 'read_users_mail'
    ],
    'financial' => [
        'name' => '財務管理',
        'description' => '振込申請情報の確認',
        'scopes' => ['read_savings'],
        'primary_scope' => 'read_savings'
    ]
];

echo "<h2>5. 用途別権限認証</h2>";
echo "<div style='display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 15px; margin: 20px 0;'>";

foreach ($scope_groups as $group_key => $group) {
    echo "<div style='border: 1px solid #dee2e6; border-radius: 8px; padding: 15px; background-color: #f8f9fa;'>";
    echo "<h4 style='margin-top: 0; color: #2c3e50;'>" . htmlspecialchars($group['name']) . "</h4>";
    echo "<p style='color: #6c757d; font-size: 0.9em;'>" . htmlspecialchars($group['description']) . "</p>";
    
    // 含まれる権限を表示
    echo "<p style='font-size: 0.8em; color: #495057;'><strong>含まれる権限:</strong> " . implode(', ', $group['scopes']) . "</p>";
    
    // 認証ボタン（主要権限のみ）
    if (isset($base_client_id) && isset($base_redirect_uri)) {
        $auth_url = "https://api.thebase.in/1/oauth/authorize?" . http_build_query([
            'response_type' => 'code',
            'client_id' => $base_client_id,
            'redirect_uri' => $base_redirect_uri,
            'scope' => $group['primary_scope'],
            'state' => $group['primary_scope']
        ]);
        
        // デバッグ: 認証URLを表示
        echo "<div style='background-color: #f8f9fa; padding: 10px; margin: 10px 0; border-radius: 4px; font-size: 0.8em;'>";
        echo "<strong>デバッグ - 認証URL:</strong><br>";
        echo "<code>" . htmlspecialchars($auth_url) . "</code>";
        echo "</div>";
        
        echo "<a href='" . htmlspecialchars($auth_url) . "' style='background-color: #007bff; color: white; padding: 8px 16px; text-decoration: none; border-radius: 4px; display: inline-block; margin-right: 10px;'>認証実行</a>";
    } else {
        echo "<p style='color: red;'>認証URL生成: 失敗（設定情報が不完全）</p>";
    }
    
    echo "</div>";
}

echo "</div>";

echo "<h2>6. 設定情報</h2>";
echo "<pre>";
echo "Client ID: " . htmlspecialchars($base_client_id ?? 'N/A') . "\n";
echo "Redirect URI: " . htmlspecialchars($base_redirect_uri ?? 'N/A') . "\n";
echo "API URL: " . htmlspecialchars($base_api_url ?? 'N/A') . "\n";
echo "</pre>";

echo "<h2>7. テスト完了</h2>";
echo "<p>すべてのテストが完了しました。</p>";
?>
