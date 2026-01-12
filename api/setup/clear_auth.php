<?php
session_start();
require_once __DIR__ . '/../../../common/config.php';
require_once __DIR__ . '/../../../common/dbconnect.php';
require_once __DIR__ . '/../../../common/functions.php';

echo "<h1>認証データクリア</h1>";

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // 認証トークンテーブルのデータを削除
    $stmt = $pdo->prepare("DELETE FROM base_api_tokens");
    $stmt->execute();
    $deleted_tokens = $stmt->rowCount();
    
    // システム設定からBASE関連を削除
    $stmt = $pdo->prepare("DELETE FROM system_config WHERE config_key LIKE 'base_%'");
    $stmt->execute();
    $deleted_configs = $stmt->rowCount();
    
    // セッションからも削除
    unset($_SESSION['base_access_token']);
    unset($_SESSION['base_refresh_token']);
    unset($_SESSION['base_token_expires']);
    unset($_SESSION['base_current_scope']);
    
    echo "<div style='background-color: #d4edda; padding: 15px; border: 1px solid #c3e6cb; border-radius: 4px; margin: 10px 0;'>";
    echo "<h3>✅ 認証データクリア完了</h3>";
    echo "<p>削除されたトークン数: " . $deleted_tokens . "</p>";
    echo "<p>削除された設定数: " . $deleted_configs . "</p>";
    echo "<p>セッションデータもクリアしました</p>";
    echo "</div>";
    
    echo "<div style='margin: 20px 0;'>";
    echo "<a href='../order_monitor.php' style='background-color: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; margin-right: 10px;'>注文監視に戻る</a>";
    echo "<a href='../../base_data_sync_top.php?utype=1024' style='background-color: #6c757d; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px;'>BASEデータ同期に戻る</a>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div style='background-color: #f8d7da; padding: 15px; border: 1px solid #f5c6cb; border-radius: 4px; margin: 10px 0;'>";
    echo "<h3>❌ エラー</h3>";
    echo "<p>エラー: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
}
?>
