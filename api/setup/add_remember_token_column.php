<?php
// cast_mstテーブルに自動ログイン用カラムを追加するスクリプト

require_once __DIR__ . '/../../../common/config.php';

echo '<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>DBスキーマ更新</title>
    <style>body { font-family: sans-serif; padding: 20px; }</style>
</head>
<body>
<h2>cast_mstテーブル更新（自動ログイン対応）</h2>
';

try {
    $pdo = new PDO(
        "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
        $user,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // カラムが存在するかチェック
    $stmt = $pdo->query("SHOW COLUMNS FROM cast_mst LIKE 'remember_token'");
    $exists = $stmt->fetch();

    if (!$exists) {
        echo "<p>カラムを追加します...</p>";
        
        // カラム追加
        $sql = "ALTER TABLE cast_mst 
                ADD COLUMN remember_token VARCHAR(64) NULL DEFAULT NULL AFTER last_login_at,
                ADD COLUMN remember_expires DATETIME NULL DEFAULT NULL AFTER remember_token";
        
        $pdo->exec($sql);
        echo "<div style='color:green; font-weight:bold;'>成功: remember_token, remember_expires カラムを追加しました。</div>";
    } else {
        echo "<div style='color:blue;'>既にカラムは存在します。変更はありません。</div>";
    }

} catch (PDOException $e) {
    echo "<div style='color:red;'>エラー: " . htmlspecialchars($e->getMessage()) . "</div>";
}

echo '<br><a href="../cast/admin_cast_manager_v2.php">戻る</a>';
echo '</body></html>';
?>
