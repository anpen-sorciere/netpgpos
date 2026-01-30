<?php
require_once(__DIR__ . '/../common/config.php');
require_once(__DIR__ . '/../common/dbconnect.php');

try {
    $pdo = connect();
    // costカラムをNULL許容に変更
    $pdo->exec("ALTER TABLE item_mst MODIFY COLUMN cost INT NULL DEFAULT NULL");
    echo "item_mst table has been successfully updated to allow NULL in 'cost' column.";
} catch (PDOException $e) {
    echo "Error updating database: " . $e->getMessage();
}
?>
