<?php
require_once 'c:/xampp/htdocs/common/config.php';

try {
    $pdo = new PDO(
        "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
        $user,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    echo "--- base_orders Statuses ---\n";
    $stmt = $pdo->query("SELECT DISTINCT status FROM base_orders");
    $statuses = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    foreach ($statuses as $status) {
        echo $status . "\n";
    }
    echo "----------------------------\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
