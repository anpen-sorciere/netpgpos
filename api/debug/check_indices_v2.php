<?php
require_once 'c:/xampp/htdocs/common/config.php';

try {
    $pdo = new PDO(
        "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
        $user,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    echo "--- base_order_items Indices ---\n";
    $stmt = $pdo->query("SHOW INDEX FROM base_order_items");
    $indices = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($indices as $idx) {
        echo "Key_name: " . $idx['Key_name'] . ", Column_name: " . $idx['Column_name'] . ", Non_unique: " . $idx['Non_unique'] . "\n";
    }
    echo "--------------------------------\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
