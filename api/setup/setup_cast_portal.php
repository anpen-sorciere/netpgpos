<?php
/**
 * BASE注文テーブル統合セットアップスクリプト
 * 既存のbase_orders/base_order_itemsにキャスト・サプライズ機能を追加
 */
session_start();
require_once __DIR__ . '/../../../common/config.php';
require_once __DIR__ . '/../../../common/dbconnect.php';

echo "<h1>BASE注文テーブル統合セットアップ</h1>";
echo "<p>既存の base_orders/base_order_items にキャスト・サプライズ機能を追加します。</p>";

try {
    $pdo = new PDO(
        "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
        $user,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );

    $sqlFile = __DIR__ . '/consolidate_base_orders.sql';
    if (!file_exists($sqlFile)) {
        die("SQLファイルが見つかりません: " . $sqlFile);
    }

    $sqlContent = file_get_contents($sqlFile);
    
    // セミコロンで分割して実行
    $queries = explode(';', $sqlContent);

    foreach ($queries as $query) {
        $query = trim($query);
        if (empty($query)) continue;

        try {
            $pdo->exec($query);
            echo "<div style='color:green'>✓ SQL実行成功: " . htmlspecialchars(substr($query, 0, 60)) . "...</div>";
        } catch (PDOException $e) {
            // カラムが既に存在する場合はスキップ
            if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
                echo "<div style='color:orange'>⚠ スキップ（カラムは既に存在）: " . htmlspecialchars(substr($query, 0, 60)) . "...</div>";
            } else {
                echo "<div style='color:red'>✗ SQL実行失敗: " . htmlspecialchars($e->getMessage()) . "</div>";
                echo "<pre>" . htmlspecialchars($query) . "</pre>";
            }
        }
    }

    echo "<h2>セットアップ完了</h2>";
    
    // カラム確認
    echo "<h3>base_orders テーブル構造確認</h3>";
    $stmt = $pdo->query("DESCRIBE base_orders");
    $columns = $stmt->fetchAll();
    echo "<ul>";
    foreach ($columns as $col) {
        $highlight = in_array($col['Field'], ['is_surprise', 'surprise_date', 'payment_method', 'dispatch_status_detail']) ? ' style="color:blue;font-weight:bold"' : '';
        echo "<li{$highlight}>{$col['Field']} ({$col['Type']})</li>";
    }
    echo "</ul>";

    echo "<h3>base_order_items テーブル構造確認</h3>";
    $stmt = $pdo->query("DESCRIBE base_order_items");
    $columns = $stmt->fetchAll();
    echo "<ul>";
    foreach ($columns as $col) {
        $highlight = in_array($col['Field'], ['cast_name', 'customer_name_from_option', 'item_surprise_date']) ? ' style="color:blue;font-weight:bold"' : '';
        echo "<li{$highlight}>{$col['Field']} ({$col['Type']})</li>";
    }
    echo "</ul>";

    echo "<div style='background:#d4edda;padding:15px;margin:20px 0;border:1px solid #c3e6cb;border-radius:5px'>";
    echo "<strong>✓ 統合完了</strong><br>";
    echo "既存の base_orders/base_order_items テーブルにキャスト・サプライズ機能が追加されました。<br>";
    echo "キャストポータルはこれらのテーブルを使用します。";
    echo "</div>";

} catch (PDOException $e) {
    echo "<div style='color:red'>DB接続エラー: " . htmlspecialchars($e->getMessage()) . "</div>";
}
?>
