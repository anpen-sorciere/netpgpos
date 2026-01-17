<?php
/**
 * DBスキーマ変更スクリプト
 * base_order_items テーブルにキャスト対応状況管理用のカラムを追加
 */
require_once __DIR__ . '/../../../common/config.php';

try {
    $pdo = new PDO(
        "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
        $user,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    echo "Adding columns to base_order_items...\n";

    // cast_handled (0=未対応, 1=対応済み)
    // cast_handled_at (対応日時)
    $sql = "
        ALTER TABLE base_order_items 
        ADD COLUMN cast_handled TINYINT(1) DEFAULT 0 COMMENT 'キャスト対応済みフラグ',
        ADD COLUMN cast_handled_at DATETIME DEFAULT NULL COMMENT 'キャスト対応日時',
        ADD COLUMN cast_handled_template_id INT DEFAULT NULL COMMENT '使用テンプレートID';
    ";

    $pdo->exec($sql);
    echo "✅ Columns added successfully.\n";

} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column') !== false) {
        echo "ℹ️  Columns already exist. Skipped.\n";
    } else {
        echo "❌ Error: " . $e->getMessage() . "\n";
    }
}
?>
