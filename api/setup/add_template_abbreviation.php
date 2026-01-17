<?php
/**
 * DBスキーマ変更スクリプト
 * reply_message_templates テーブルに略称カラムを追加
 */
require_once __DIR__ . '/../../common/config.php';

try {
    $pdo = new PDO(
        "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
        $user,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    echo "Adding template_abbreviation to reply_message_templates...\n";

    $sql = "
        ALTER TABLE reply_message_templates 
        ADD COLUMN template_abbreviation VARCHAR(50) DEFAULT NULL COMMENT '定型文略称（管理者識別用）' AFTER template_name;
    ";

    $pdo->exec($sql);
    echo "✅ Column added successfully.\n";

} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column') !== false) {
        echo "ℹ️  Column already exists. Skipped.\n";
    } else {
        echo "❌ Error: " . $e->getMessage() . "\n";
    }
}
?>
