<?php
require_once(__DIR__ . '/../common/config.php');
require_once(__DIR__ . '/../common/dbconnect.php');

try {
    $pdo = connect();
    // 既存の0円データをNULLに更新
    $count = $pdo->exec("UPDATE item_mst SET cost = NULL WHERE cost = 0");
    echo "Update completed. {$count} items converted from 0 to NULL.";
} catch (PDOException $e) {
    echo "Error updating database: " . $e->getMessage();
}
?>
