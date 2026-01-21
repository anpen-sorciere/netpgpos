<?php
require_once __DIR__ . '/../common/config.php';
require_once __DIR__ . '/../common/dbconnect.php';

try {
    $pdo = connect();
    $sql = "ALTER TABLE seat_sessions ADD COLUMN is_new_customer TINYINT(1) DEFAULT 0";
    $pdo->exec($sql);
    echo "Column added successfully";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
