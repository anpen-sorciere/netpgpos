<?php
try {
    $pdo = new PDO('mysql:host=localhost;dbname=sorciere_local;charset=utf8mb4', 'root', '');
    echo "Connected successfully to sorciere_local\n";
} catch (Exception $e) {
    echo "Connection failed: " . $e->getMessage() . "\n";
}
