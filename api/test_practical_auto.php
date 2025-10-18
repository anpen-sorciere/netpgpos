<?php
/**
 * BASE API 実用的完全自動化システム - テストスクリプト
 * 不明な仕様に対応した堅牢なシステムの動作確認
 */
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/base_practical_auto_manager.php';

$practical_manager = new BasePracticalAutoManager();

echo "<h1>BASE API 実用的完全自動化システム</h1>";
echo "<p>不明な仕様に対応した堅牢なトークン管理とスコープ切り替えシステム</p>";

echo "<h2>1. 認証状態の確認</h2>";
try {
    $auth_status = $practical_manager->getAuthStatus();
    
    foreach ($auth_status as $scope => $status) {
        if ($status['authenticated']) {
            $access_status = $status['access_valid'] ? '<span style="color: green;">有効</span>' : '<span style="color: orange;">期限切れ</span>';
            $refresh_status = $status['refresh_valid'] ? '<span style="color: green;">有効</span>' : '<span style="color: red;">期限切れ</span>';
            
            echo "<strong>{$scope}</strong>: <span style='color: green;'>✓ 認証済み</span><br>";
            echo "&nbsp;&nbsp;アクセストークン: {$access_status} (期限: {$status['access_expires']})<br>";
            echo "&nbsp;&nbsp;リフレッシュトークン: {$refresh_status} (期限: {$status['refresh_expires']})<br><br>";
        } else {
            echo "<strong>{$scope}</strong>: <span style='color: red;'>✗ 未認証</span><br>";
        }
    }
    
} catch (Exception $e) {
    echo "<span style='color: red;'>エラー: " . htmlspecialchars($e->getMessage()) . "</span><br>";
}

echo "<h2>2. 最小限セットアップの確認</h2>";
$orders_ok = isset($auth_status['orders_only']['authenticated']) && $auth_status['orders_only']['authenticated'];
$items_ok = isset($auth_status['items_only']['authenticated']) && $auth_status['items_only']['authenticated'];

if ($orders_ok && $items_ok) {
    echo "<span style='color: green;'>✓ 最小限のセットアップが完了しています</span><br>";
    echo "<p>注文データと商品データの取得が可能です。</p>";
    
    echo "<h3>自動データ取得・合成テスト</h3>";
    try {
        $result = $practical_manager->getCombinedOrderData(5);
        
        echo "<span style='color: green;'>✓ 自動データ取得・合成テスト成功</span><br>";
        echo "注文件数: " . count($result['orders']) . "<br>";
        echo "商品件数: " . count($result['items']) . "<br>";
        echo "合成済み注文件数: " . count($result['merged_orders']) . "<br>";
        
        echo "<h4>認証ログ</h4>";
        foreach ($result['auth_log'] as $log) {
            echo "• " . htmlspecialchars($log) . "<br>";
        }
        
    } catch (Exception $e) {
        echo "<span style='color: red;'>✗ 自動データ取得・合成テスト失敗: " . htmlspecialchars($e->getMessage()) . "</span><br>";
    }
    
} else {
    echo "<span style='color: orange;'>⚠ 最小限のセットアップが未完了</span><br>";
    echo "<p>以下の2つのスコープで認証が必要です：</p>";
    
    echo "<h3>必要な認証</h3>";
    echo "<div style='background: #f0f0f0; padding: 15px; border: 1px solid #ccc; border-radius: 5px;'>";
    
    if (!$orders_ok) {
        echo "<h4>1. 注文データ認証（orders_only）</h4>";
        $orders_url = "https://api.thebase.in/1/oauth/authorize?" . http_build_query([
            'response_type' => 'code',
            'client_id' => $base_client_id,
            'redirect_uri' => $base_redirect_uri,
            'scope' => 'read_orders',
            'state' => 'orders_only'
        ]);
        echo '<a href="' . $orders_url . '" style="background: #007cba; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">注文データ認証を実行</a><br><br>';
    }
    
    if (!$items_ok) {
        echo "<h4>2. 商品データ認証（items_only）</h4>";
        $items_url = "https://api.thebase.in/1/oauth/authorize?" . http_build_query([
            'response_type' => 'code',
            'client_id' => $base_client_id,
            'redirect_uri' => $base_redirect_uri,
            'scope' => 'read_items',
            'state' => 'items_only'
        ]);
        echo '<a href="' . $items_url . '" style="background: #007cba; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">商品データ認証を実行</a><br><br>';
    }
    
    echo "<h4>認証手順：</h4>";
    echo "<ol>";
    echo "<li>上記のリンクをクリック</li>";
    echo "<li>BASEの認証画面で「許可」をクリック</li>";
    echo "<li>自動的にトークンが保存されます</li>";
    echo "<li>両方の認証が完了したら、このページを再読み込み</li>";
    echo "</ol>";
    echo "</div>";
}

echo "<h2>3. システムの特徴</h2>";
echo "<div style='background: #f0f0f0; padding: 15px; border: 1px solid #ccc; border-radius: 5px;'>";
echo "<h4>✅ 実用的完全自動化の利点</h4>";
echo "• <strong>スコープ単体認証対応</strong>: 複数スコープ同時認証不可の制限に対応<br>";
echo "• <strong>不明な仕様への対応</strong>: リフレッシュトークン動作などの不明な仕様に堅牢対応<br>";
echo "• <strong>データベース永続化</strong>: サーバー再起動後も継続<br>";
echo "• <strong>セキュリティ</strong>: トークンのAES-256-CBC暗号化保存<br>";
echo "• <strong>同時実行制御</strong>: 複数リフレッシュ処理の競合を防止<br>";
echo "• <strong>包括的エラーハンドリング</strong>: 全エラーケースに対応<br>";
echo "<br>";
echo "<h4>🚀 運用フロー</h4>";
echo "1. <strong>初回セットアップ</strong>: 2つのスコープで個別認証（1回限り）<br>";
echo "2. <strong>完全自動運用</strong>: 30日間無人でデータ取得・合成<br>";
echo "3. <strong>自動更新</strong>: リフレッシュトークンで継続（不明な仕様に対応）<br>";
echo "4. <strong>フォールバック</strong>: 認証失敗時の適切なエラーハンドリング<br>";
echo "</div>";

echo "<h2>4. 管理機能</h2>";
echo '<a href="order_monitor.php?debug=nickname" style="background: #28a745; color: white; padding: 5px 10px; text-decoration: none; border-radius: 3px;">実際の注文監視画面</a><br>';
echo '<a href="base_callback_debug.php" style="background: #17a2b8; color: white; padding: 5px 10px; text-decoration: none; border-radius: 3px;">認証コールバック処理</a><br>';

echo "<h2>5. 技術仕様の対応状況</h2>";
echo "<div style='background: #fff3cd; padding: 15px; border: 1px solid #ffeaa7; border-radius: 5px;'>";
echo "<h4>📋 確認済み仕様</h4>";
echo "• <strong>スコープは単体のみ</strong>: 複数スコープ同時認証不可 → 個別認証システムで対応<br>";
echo "<br>";
echo "<h4>❓ 不明な仕様への対応</h4>";
echo "• <strong>リフレッシュトークンの動作</strong>: 既存トークン保持 + エラーハンドリング<br>";
echo "• <strong>認可コードの再利用</strong>: 1回使用前提で実装<br>";
echo "• <strong>スコープ別制限</strong>: 個別制限を想定した実装<br>";
echo "• <strong>リフレッシュトークン期限切れ</strong>: 再認証要求の適切なエラーハンドリング<br>";
echo "</div>";
?>
