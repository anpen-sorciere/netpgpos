<?php
// Mock server var for CLI
$_SERVER['HTTP_HOST'] = 'localhost';

require_once(__DIR__ . '/../common/dbconnect.php');
$pdo = connect();

if (!$pdo) {
    die("DB Connection failed\n");
}

try {
    echo "Adding is_new_customer column...\n";
    $sql = "ALTER TABLE seat_sessions ADD COLUMN is_new_customer TINYINT(1) DEFAULT 0";
    $pdo->exec($sql);
    echo "Success: Column added.\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column') !== false) {
        echo "Column already exists.\n";
    } else {
        echo "Error: " . $e->getMessage() . "\n";
    }
}
