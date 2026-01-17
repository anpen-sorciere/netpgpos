<?php
/**
 * video_uploadsテーブル更新スクリプト
 * order_item_idカラムを追加する
 */

require_once __DIR__ . '/../../../common/config.php';
require_once __DIR__ . '/../../../common/dbconnect.php';

echo "<h2>video_uploads テーブル更新</h2>";

try {
    $pdo = connect();
    
    // カラムが存在するかチェック
    $stmt = $pdo->prepare("SHOW COLUMNS FROM video_uploads LIKE 'order_item_id'");
    $stmt->execute();
    if ($stmt->fetch()) {
        echo "ℹ️ `order_item_id` カラムは既に存在します。<br>";
    } else {
        $sql = "ALTER TABLE video_uploads ADD COLUMN order_item_id VARCHAR(50) DEFAULT NULL COMMENT '紐付け用アイテムID' AFTER cast_id";
        $pdo->exec($sql);
        echo "✅ `order_item_id` カラムを追加しました。<br>";
        
        // インデックスも追加
        $pdo->exec("CREATE INDEX idx_order_item_id ON video_uploads (order_item_id)");
        echo "✅ インデックスを追加しました。<br>";
    }

} catch (PDOException $e) {
    echo "❌ DBエラー: " . $e->getMessage() . "<br>";
}
