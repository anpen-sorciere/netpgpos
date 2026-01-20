<?php
require_once 'C:/xampp/htdocs/common/dbconnect.php';

try {
    $pdo = connect();
    if (!$pdo) exit;

    echo "Checking if membership_rewards column exists...<br>";
    
    // カラム確認
    $stmt = $pdo->query("SHOW COLUMNS FROM base_orders LIKE 'membership_rewards'");
    $exists = $stmt->fetch();

    if ($exists) {
        echo "Column 'membership_rewards' already exists.<br>";
    } else {
        echo "Adding 'membership_rewards' column...<br>";
        // TEXT型で追加（JSONデータ格納用）
        $sql = "ALTER TABLE base_orders ADD COLUMN membership_rewards TEXT NULL AFTER surprise_date";
        $pdo->exec($sql);
        echo "Column added successfully.<br>";
    }

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
