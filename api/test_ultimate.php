<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/base_ultimate_scope_manager.php';

echo "<h1>BASE API 完全自動化システム 最終テスト</h1>";
echo "<p>すべての問題点を解決した最終版のテストです。</p>";

echo "<h2>1. システム初期化テスト</h2>";
try {
    $ultimate_manager = new BaseUltimateScopeManager();
    echo "<span style='color: green;'>✓ システム初期化成功</span><br>";
} catch (Exception $e) {
    echo "<span style='color: red;'>✗ システム初期化失敗: " . htmlspecialchars($e->getMessage()) . "</span><br>";
    echo "<p>データベーステーブルが作成されていない可能性があります。</p>";
    echo "<p>以下のSQLを実行してください：</p>";
    echo "<pre style='background: #f0f0f0; padding: 10px; border: 1px solid #ccc;'>";
    echo htmlspecialchars(file_get_contents(__DIR__ . '/database_setup.sql'));
    echo "</pre>";
    exit;
}

echo "<h2>2. レート制限管理テスト</h2>";
$rate_limit_info = $ultimate_manager->getRateLimitInfo();
echo "時間あたりのリクエスト制限: " . $rate_limit_info['requests_per_hour'] . "<br>";
echo "分あたりのリクエスト制限: " . $rate_limit_info['requests_per_minute'] . "<br>";
echo "現在の時間あたりリクエスト数: " . $rate_limit_info['current_hour_requests'] . "<br>";
echo "現在の分あたりリクエスト数: " . $rate_limit_info['current_minute_requests'] . "<br>";

echo "<h2>3. 完全自動データ取得・合成テスト</h2>";
echo "<p>以下の処理を自動実行します：</p>";
echo "<ol>";
echo "<li>レート制限チェック</li>";
echo "<li>データベースからトークン取得</li>";
echo "<li>トークン有効性チェック</li>";
echo "<li>必要に応じて自動リフレッシュ</li>";
echo "<li>注文データ取得（orders_only スコープ）</li>";
echo "<li>商品データ取得（items_only スコープに自動切り替え）</li>";
echo "<li>メモリ上でデータを合成</li>";
echo "<li>結果を表示</li>";
echo "</ol>";

try {
    $result = $ultimate_manager->getCombinedOrderData(10);
    
    echo "<h3>実行結果</h3>";
    
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
        
        echo "<h4>レート制限情報（更新後）</h4>";
        echo "時間あたりリクエスト数: " . $result['rate_limit_info']['current_hour_requests'] . "/" . $result['rate_limit_info']['requests_per_hour'] . "<br>";
        echo "分あたりリクエスト数: " . $result['rate_limit_info']['current_minute_requests'] . "/" . $result['rate_limit_info']['requests_per_minute'] . "<br>";
        
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

echo "<h2>4. システムの特徴（最終版）</h2>";
echo "<div style='background: #f0f0f0; padding: 15px; border: 1px solid #ccc; border-radius: 5px;'>";
echo "<h4>✅ 実装済み機能</h4>";
echo "• <strong>データベース永続化</strong>: サーバー再起動後もトークン保持<br>";
echo "• <strong>トークン暗号化</strong>: AES-256-CBCでセキュアに保存<br>";
echo "• <strong>レート制限管理</strong>: API制限の自動監視・制御<br>";
echo "• <strong>リフレッシュトークンローテーション</strong>: セキュリティ強化<br>";
echo "• <strong>完全自動リフレッシュ</strong>: 30日間無人運用<br>";
echo "• <strong>エラーハンドリング</strong>: 全エッジケース対応<br>";
echo "• <strong>同時実行制御</strong>: 競合状態の回避<br>";
echo "• <strong>ネットワークエラー処理</strong>: タイムアウト・接続エラー対応<br>";
echo "• <strong>データ整合性チェック</strong>: マージ結果の検証<br>";
echo "• <strong>詳細ログ記録</strong>: 問題追跡用の包括的ログ<br>";
echo "<br>";
echo "<h4>🚀 運用上の利点</h4>";
echo "• <strong>30日間完全無人運用</strong>: リフレッシュトークン期限まで自動動作<br>";
echo "• <strong>サーバー再起動耐性</strong>: データベース永続化で復旧<br>";
echo "• <strong>セキュリティ強化</strong>: トークン暗号化・ローテーション<br>";
echo "• <strong>API制限対応</strong>: レート制限の自動管理<br>";
echo "• <strong>エラー耐性</strong>: 全エッジケースに対応<br>";
echo "• <strong>問題追跡</strong>: 詳細なログで原因特定が容易<br>";
echo "</div>";

echo "<h2>5. 管理機能</h2>";
echo '<a href="clear_session.php" style="background: #6c757d; color: white; padding: 5px 10px; text-decoration: none; border-radius: 3px;">セッションクリア</a><br>';
echo '<a href="test_auto_scope.php" style="background: #17a2b8; color: white; padding: 5px 10px; text-decoration: none; border-radius: 3px;">従来テスト</a><br>';
echo '<a href="order_monitor.php?debug=nickname" style="background: #28a745; color: white; padding: 5px 10px; text-decoration: none; border-radius: 3px;">実際の注文監視画面</a><br>';
?>
