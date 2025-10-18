<?php
/**
 * BASE API 直接認証テストスクリプト
 * 認証プロセスを完全に手動で実行
 */
session_start();
require_once __DIR__ . '/config.php';

echo "<h1>BASE API 直接認証テスト</h1>";
echo "<p>認証プロセスを完全に手動で実行します</p>";

echo "<h2>1. 設定値の確認</h2>";
echo "<div style='background: #f0f0f0; padding: 15px; border: 1px solid #ccc; border-radius: 5px;'>";
echo "<p><strong>CLIENT_ID:</strong> " . (isset($base_client_id) ? $base_client_id : '未設定') . "</p>";
echo "<p><strong>CLIENT_SECRET:</strong> " . (isset($base_client_secret) ? '設定済み' : '未設定') . "</p>";
echo "<p><strong>REDIRECT_URI:</strong> " . (isset($base_redirect_uri) ? $base_redirect_uri : '未設定') . "</p>";
echo "</div>";

echo "<h2>2. 直接認証URL生成</h2>";
echo "<div style='background: #e8f4fd; padding: 15px; border: 1px solid #b3d9ff; border-radius: 5px;'>";

if (isset($base_client_id) && isset($base_redirect_uri)) {
    // orders_only スコープ
    $orders_params = [
        'response_type' => 'code',
        'client_id' => $base_client_id,
        'redirect_uri' => $base_redirect_uri,
        'scope' => 'read_orders',
        'state' => 'orders_only'
    ];
    $orders_url = 'https://api.thebase.in/1/oauth/authorize?' . http_build_query($orders_params);
    
    // items_only スコープ
    $items_params = [
        'response_type' => 'code',
        'client_id' => $base_client_id,
        'redirect_uri' => $base_redirect_uri,
        'scope' => 'read_items',
        'state' => 'items_only'
    ];
    $items_url = 'https://api.thebase.in/1/oauth/authorize?' . http_build_query($items_params);
    
    echo "<h3>注文情報取得用認証</h3>";
    echo "<p><strong>スコープ:</strong> read_orders</p>";
    echo "<p><strong>URL:</strong> <a href='{$orders_url}' target='_blank' style='word-break: break-all;'>{$orders_url}</a></p>";
    echo "<p><a href='{$orders_url}' style='background: #007bff; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; font-size: 16px; font-weight: bold;' target='_blank'>📋 注文情報取得認証を実行</a></p>";
    
    echo "<h3>商品情報取得用認証</h3>";
    echo "<p><strong>スコープ:</strong> read_items</p>";
    echo "<p><strong>URL:</strong> <a href='{$items_url}' target='_blank' style='word-break: break-all;'>{$items_url}</a></p>";
    echo "<p><a href='{$items_url}' style='background: #28a745; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; font-size: 16px; font-weight: bold;' target='_blank'>📦 商品情報取得認証を実行</a></p>";
    
} else {
    echo "<p style='color: red;'>設定値が不足しています</p>";
}
echo "</div>";

echo "<h2>3. 認証完了後の確認</h2>";
echo "<div style='background: #fff3cd; padding: 15px; border: 1px solid #ffeaa7; border-radius: 5px;'>";
echo "<p>認証完了後、以下のURLで確認してください：</p>";
echo "<ul>";
echo "<li><a href='debug_auth_status.php'>認証状態確認</a></li>";
echo "<li><a href='debug_json_structure.php'>JSONデータ構造確認</a></li>";
echo "<li><a href='api/order_monitor.php'>注文監視システム</a></li>";
echo "</ul>";
echo "</div>";

echo "<h2>4. トラブルシューティング</h2>";
echo "<div style='background: #f8d7da; padding: 15px; border: 1px solid #f5c6cb; border-radius: 5px;'>";
echo "<h3>よくある問題と解決方法</h3>";
echo "<ul>";
echo "<li><strong>認証URLをクリックしてもBASEの画面に移動しない</strong><br>→ ブラウザのポップアップブロックを確認</li>";
echo "<li><strong>BASEの認証画面でエラーが表示される</strong><br>→ CLIENT_IDとREDIRECT_URIが正しいか確認</li>";
echo "<li><strong>認証完了後にコールバックでエラー</strong><br>→ base_callback_debug.phpが正しく動作するか確認</li>";
echo "<li><strong>404エラーが発生する</strong><br>→ BASE管理画面でスコープが有効になっているか確認</li>";
echo "</ul>";
echo "</div>";
?>
