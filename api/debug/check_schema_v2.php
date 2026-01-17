<?php
ini_set('display_errors', 1);
require_once 'c:/xampp/htdocs/common/config.php';
header('Content-Type: text/plain');

try {
    $pdo = new PDO(
        "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
        $user,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    echo "--- base_order_items Columns ---\n";
    $stmt = $pdo->query("DESCRIBE base_order_items");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($columns as $col) {
        echo $col['Field'] . " (" . $col['Type'] . ")\n";
    }
    echo "--------------------------------\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
