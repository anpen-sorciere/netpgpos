<?php
/**
 * cast_mstにSNSアカウント用カラムを追加するスクリプト (本番適用完了後に削除してください)
 */
require_once __DIR__ . '/../../../common/config.php';
require_once __DIR__ . '/../../../common/dbconnect.php';

try {
    $pdo = connect();
    echo "Connected to database.<br>";

    // カラム追加
    $columns = [
        'social_x' => 'VARCHAR(255) DEFAULT NULL COMMENT "X(旧Twitter)アカウント"',
        'social_instagram' => 'VARCHAR(255) DEFAULT NULL COMMENT "Instagramアカウント"',
        'social_tiktok' => 'VARCHAR(255) DEFAULT NULL COMMENT "TikTokアカウント"'
    ];

    foreach ($columns as $col => $def) {
        // カラム存在チェック
        $stmt = $pdo->query("SHOW COLUMNS FROM cast_mst LIKE '$col'");
        if ($stmt->fetch()) {
            echo "Column '$col' already exists. Skipping.<br>";
        } else {
            echo "Adding column '$col'...<br>";
            $pdo->exec("ALTER TABLE cast_mst ADD COLUMN $col $def"); // 末尾に追加
        }
    }

    echo "<strong>Database update completed successfully.</strong><br>";
    echo "Please delete this file after confirmation.";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "<br>";
}
