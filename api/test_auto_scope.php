<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/base_auto_scope_manager.php';

$auto_manager = new BaseAutoScopeManager();

echo "<h1>BASE API 完全自動スコープ切り替えテスト</h1>";
echo "<p>人の手を介さずに、自動でスコープを切り替えてデータを取得・合成します。</p>";

echo "<h2>現在の認証状態</h2>";
echo "アクセストークン: " . (isset($_SESSION['base_access_token']) ? '設定済み' : '未設定') . "<br>";
echo "リフレッシュトークン: " . (isset($_SESSION['base_refresh_token']) ? '設定済み' : '未設定') . "<br>";
echo "トークン有効期限: " . (isset($_SESSION['base_token_expires']) ? date('Y-m-d H:i:s', $_SESSION['base_token_expires']) : '未設定') . "<br>";

echo "<h2>スコープ別認証状態</h2>";
$auth_status = $auto_manager->getScopeAuthStatus();
foreach ($auth_status as $scope_key => $status) {
    $auth_text = $status['authenticated'] ? '<span style="color: green;">✓ 認証済み</span>' : '<span style="color: red;">✗ 未認証</span>';
    $expires_text = $status['expires'] ? date('Y-m-d H:i:s', $status['expires']) : '不明';
    $refresh_expires_text = $status['refresh_expires'] ? date('Y-m-d H:i:s', $status['refresh_expires']) : '不明';
    $refresh_text = $status['has_refresh_token'] ? 'あり' : 'なし';
    $refresh_expired_text = $status['is_refresh_expired'] ? '<span style="color: red;">期限切れ</span>' : '<span style="color: green;">有効</span>';
    
    echo "<strong>{$scope_key}</strong>: {$auth_text}<br>";
    echo "&nbsp;&nbsp;アクセストークン期限: {$expires_text}<br>";
    echo "&nbsp;&nbsp;リフレッシュトークン: {$refresh_text} ({$refresh_expired_text})<br>";
    echo "&nbsp;&nbsp;リフレッシュ期限: {$refresh_expires_text}<br><br>";
}

echo "<h2>自動データ取得・合成テスト</h2>";
echo "<p>以下の処理を自動実行します：</p>";
echo "<ol>";
echo "<li>注文データを取得（orders_only スコープ）</li>";
echo "<li>商品データを取得（items_only スコープに自動切り替え）</li>";
echo "<li>メモリ上でデータを合成</li>";
echo "<li>結果を表示</li>";
echo "</ol>";

echo "<h3>実行結果</h3>";

try {
    $result = $auto_manager->getCombinedOrderData(10);
    
    echo "<h4>認証ログ</h4>";
    foreach ($result['auth_log'] as $log) {
        echo "• " . htmlspecialchars($log) . "<br>";
    }
    
    if ($result['error']) {
        echo "<h4 style='color: red;'>エラー</h4>";
        echo htmlspecialchars($result['error']) . "<br>";
        
        if (strpos($result['error'], '新しい認証が必要') !== false) {
            echo "<h4>解決方法</h4>";
            echo "<p>初回認証が必要です。以下のリンクで認証してください：</p>";
            echo '<a href="https://api.thebase.in/1/oauth/authorize?response_type=code&client_id=ac363aa232032543a05c99666f828f2d&redirect_uri=https://purplelion51.sakura.ne.jp/netpgpos/api/base_callback_debug.php&scope=read_orders&state=orders_only" style="background: #007cba; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">初回認証（注文データ）</a><br><br>';
            echo '<a href="https://api.thebase.in/1/oauth/authorize?response_type=code&client_id=ac363aa232032543a05c99666f828f2d&redirect_uri=https://purplelion51.sakura.ne.jp/netpgpos/api/base_callback_debug.php&scope=read_items&state=items_only" style="background: #007cba; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">初回認証（商品データ）</a><br><br>';
        }
    } else {
        echo "<h4 style='color: green;'>成功！</h4>";
        echo "注文件数: " . count($result['orders']) . "<br>";
        echo "商品件数: " . count($result['items']) . "<br>";
        echo "合成済み注文件数: " . count($result['merged_orders']) . "<br>";
        
        echo "<h4>合成データサンプル</h4>";
        if (isset($result['merged_orders']['orders'][0])) {
            $sample_order = $result['merged_orders']['orders'][0];
            echo "<pre style='background: #f5f5f5; padding: 10px; border: 1px solid #ddd; max-height: 300px; overflow-y: auto;'>";
            echo htmlspecialchars(print_r($sample_order, true));
            echo "</pre>";
        }
        
        echo "<h4>ニックネーム抽出テスト</h4>";
        if (isset($result['merged_orders']['orders'][0]['order_items'])) {
            foreach ($result['merged_orders']['orders'][0]['order_items'] as $item) {
                if (isset($item['options']) && is_array($item['options'])) {
                    foreach ($item['options'] as $option) {
                        $option_name = $option['option_name'] ?? '';
                        $option_value = $option['option_value'] ?? '';
                        
                        if (stripos($option_name, 'お客様名') !== false) {
                            echo "✓ ニックネーム発見: " . htmlspecialchars($option_value) . "<br>";
                        }
                    }
                }
            }
        }
    }
    
} catch (Exception $e) {
    echo "<h4 style='color: red;'>例外エラー</h4>";
    echo htmlspecialchars($e->getMessage()) . "<br>";
}

echo "<h2>管理機能</h2>";
echo '<a href="clear_session.php" style="background: #6c757d; color: white; padding: 5px 10px; text-decoration: none; border-radius: 3px;">セッションクリア</a><br>';
echo '<a href="multi_scope_test.php" style="background: #17a2b8; color: white; padding: 5px 10px; text-decoration: none; border-radius: 3px;">手動認証テスト</a><br>';
?>
