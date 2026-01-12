<?php
/**
 * キャストポータル用データベースセットアップスクリプト
 */
session_start();
require_once __DIR__ . '/../../../common/config.php';
require_once __DIR__ . '/../../../common/dbconnect.php';

echo "<h1>キャストポータル用DBセットアップ</h1>";

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

    $sqlFile = __DIR__ . '/cast_portal_setup.sql';
    if (!file_exists($sqlFile)) {
        die("SQLファイルが見つかりません: " . $sqlFile);
    }

    $sqlContent = file_get_contents($sqlFile);
    
    // コメント削除（簡易的）
    // $sqlContent = preg_replace('/--.*$/m', '', $sqlContent);

    // セミコロンで分割して実行
    $queries = explode(';', $sqlContent);

    foreach ($queries as $query) {
        $query = trim($query);
        if (empty($query)) continue;

        try {
            $pdo->exec($query);
            echo "<div>SQL実行成功: " . htmlspecialchars(substr($query, 0, 50)) . "...</div>";
        } catch (PDOException $e) {
            echo "<div style='color:red'>SQL実行失敗: " . htmlspecialchars($e->getMessage()) . "</div>";
            echo "<pre>" . htmlspecialchars($query) . "</pre>";
        }
    }

    echo "<h2>セットアップ完了</h2>";
    echo "<p>casts, orders, order_items テーブルが作成されました。</p>";
    
    // テーブル確認
    $tables = ['casts', 'orders', 'order_items'];
    foreach ($tables as $table) {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($stmt->fetch()) {
            echo "<div style='color:green'>✓ テーブル存在確認: $table</div>";
        } else {
            echo "<div style='color:red'>✗ テーブル不足: $table</div>";
        }
    }

} catch (PDOException $e) {
    echo "DB接続エラー: " . $e->getMessage();
}
