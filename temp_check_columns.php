<?php
require_once(__DIR__ . '/../common/config.php');
require_once(__DIR__ . '/../common/dbconnect.php');

try {
    $pdo = connect();
    $stmt = $pdo->query("DESC seat_sessions");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
    echo "Columns: " . implode(", ", $columns) . "\n";
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
