<?php
$_SERVER['HTTP_HOST'] = 'localhost';
require_once __DIR__ . '/../common/config.php';
require_once __DIR__ . '/../common/dbconnect.php';

try {
    $pdo = connect();
    echo "Connected.\n";
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    print_r($tables);
} catch(Exception $e) {
    echo $e->getMessage();
}
