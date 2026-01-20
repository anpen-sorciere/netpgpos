<?php
try {
    $pdo = new PDO('mysql:host=localhost;dbname=sorciere_local;charset=utf8mb4', 'root', '');
    $stmt = $pdo->query("SHOW DATABASES");
    print_r($stmt->fetchAll(PDO::FETCH_COLUMN));
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
