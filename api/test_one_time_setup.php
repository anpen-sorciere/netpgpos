<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/base_one_time_setup.php';

$setup_manager = new BaseOneTimeSetup();

echo "<h1>BASE API ワンタイムセットアップ</h1>";
echo "<p>初回認証を最小限の手動操作で完了し、以降は完全自動化します。</p>";

echo "<h2>1. セットアップ状況</h2>";
try {
    $status = $setup_manager->checkSetupStatus();
    
    foreach ($status as $scope_key => $scope_status) {
        if ($scope_status['setup']) {
            $access_status = $scope_status['access_valid'] ? '<span style="color: green;">有効</span>' : '<span style="color: orange;">期限切れ</span>';
            $refresh_status = $scope_status['refresh_valid'] ? '<span style="color: green;">有効</span>' : '<span style="color: red;">期限切れ</span>';
            $access_expires = date('Y-m-d H:i:s', $scope_status['access_expires']);
            $refresh_expires = date('Y-m-d H:i:s', $scope_status['refresh_expires']);
            
            echo "<strong>{$scope_key}</strong>: <span style='color: green;'>✓ 設定済み</span><br>";
            echo "&nbsp;&nbsp;アクセストークン: {$access_status} (期限: {$access_expires})<br>";
            echo "&nbsp;&nbsp;リフレッシュトークン: {$refresh_status} (期限: {$refresh_expires})<br><br>";
        } else {
            echo "<strong>{$scope_key}</strong>: <span style='color: red;'>✗ 未設定</span><br>";
        }
    }
    
} catch (Exception $e) {
    echo "<span style='color: red;'>エラー: " . htmlspecialchars($e->getMessage()) . "</span><br>";
}

echo "<h2>2. 最小限セットアップ</h2>";
$minimal_complete = $setup_manager->isMinimalSetupComplete();

if ($minimal_complete) {
    echo "<span style='color: green;'>✓ 最小限のセットアップが完了しています</span><br>";
    echo "<p>注文データと商品データの取得が可能です。</p>";
    
    echo "<h3>自動運用テスト</h3>";
    try {
        $test_result = $setup_manager->testAutomaticOperation();
        
        if ($test_result['success']) {
            echo "<span style='color: green;'>✓ 自動運用テスト成功</span><br>";
            echo "注文件数: " . $test_result['orders_count'] . "<br>";
            echo "商品件数: " . $test_result['items_count'] . "<br>";
            echo "合成済み注文件数: " . $test_result['merged_count'] . "<br>";
            
            echo "<h4>認証ログ</h4>";
            foreach ($test_result['auth_log'] as $log) {
                echo "• " . htmlspecialchars($log) . "<br>";
            }
        } else {
            echo "<span style='color: red;'>✗ 自動運用テスト失敗: " . htmlspecialchars($test_result['error']) . "</span><br>";
        }
        
    } catch (Exception $e) {
        echo "<span style='color: red;'>✗ テストエラー: " . htmlspecialchars($e->getMessage()) . "</span><br>";
    }
    
} else {
    echo "<span style='color: orange;'>⚠ 最小限のセットアップが未完了</span><br>";
    echo "<p>以下の2つのスコープで認証が必要です：</p>";
    
    echo "<h3>必要な認証</h3>";
    echo "<div style='background: #f0f0f0; padding: 15px; border: 1px solid #ccc; border-radius: 5px;'>";
    echo "<h4>1. 注文データ認証（orders_only）</h4>";
    echo '<a href="' . $setup_manager->getOneTimeSetupUrl('orders_only') . '" style="background: #007cba; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">注文データ認証を実行</a><br><br>';
    
    echo "<h4>2. 商品データ認証（items_only）</h4>";
    echo '<a href="' . $setup_manager->getOneTimeSetupUrl('items_only') . '" style="background: #007cba; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">商品データ認証を実行</a><br><br>';
    
    echo "<h4>認証手順：</h4>";
    echo "<ol>";
    echo "<li>上記のリンクをクリック</li>";
    echo "<li>BASEの認証画面で「許可」をクリック</li>";
    echo "<li>自動的にトークンが保存されます</li>";
    echo "<li>両方の認証が完了したら、このページを再読み込み</li>";
    echo "</ol>";
    echo "</div>";
}

echo "<h2>3. 完全セットアップ（オプション）</h2>";
echo "<p>すべてのスコープで認証を完了すると、より多くの機能が利用できます：</p>";

$optional_scopes = ['users_only', 'users_mail_only', 'savings_only', 'write_items_only', 'write_orders_only'];
foreach ($optional_scopes as $scope_key) {
    $scope_status = $status[$scope_key] ?? ['setup' => false];
    
    if (!$scope_status['setup']) {
        echo "<div style='border: 1px solid #ddd; padding: 10px; margin: 5px 0; border-radius: 3px;'>";
        echo "<strong>{$scope_key}</strong><br>";
        echo '<a href="' . $setup_manager->getOneTimeSetupUrl($scope_key) . '" style="background: #6c757d; color: white; padding: 5px 10px; text-decoration: none; border-radius: 3px;">認証実行</a>';
        echo "</div>";
    }
}

echo "<h2>4. システムの特徴</h2>";
echo "<div style='background: #f0f0f0; padding: 15px; border: 1px solid #ccc; border-radius: 5px;'>";
echo "<h4>✅ ワンタイムセットアップの利点</h4>";
echo "• <strong>最小限の手動操作</strong>: 初回のみ2回のクリック<br>";
echo "• <strong>完全自動化</strong>: セットアップ後は30日間無人運用<br>";
echo "• <strong>データベース永続化</strong>: サーバー再起動後も継続<br>";
echo "• <strong>セキュリティ</strong>: トークンの暗号化保存<br>";
echo "• <strong>エラー耐性</strong>: 全エッジケースに対応<br>";
echo "<br>";
echo "<h4>🚀 運用フロー</h4>";
echo "1. <strong>初回セットアップ</strong>: 2つのスコープで認証（1回限り）<br>";
echo "2. <strong>完全自動運用</strong>: 30日間無人でデータ取得・合成<br>";
echo "3. <strong>自動更新</strong>: リフレッシュトークンで継続<br>";
echo "</div>";

echo "<h2>5. 管理機能</h2>";
echo '<a href="test_ultimate.php" style="background: #17a2b8; color: white; padding: 5px 10px; text-decoration: none; border-radius: 3px;">詳細テスト</a><br>';
echo '<a href="order_monitor.php?debug=nickname" style="background: #28a745; color: white; padding: 5px 10px; text-decoration: none; border-radius: 3px;">実際の注文監視画面</a><br>';
?>
