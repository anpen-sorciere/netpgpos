<?php
// Standalone connection trying local config first
$host = "localhost";
$user = "root";
$password = "";
$dbname = "sorciere_local";

try {
    $pdo = new PDO("mysql:host={$host};dbname={$dbname};charset=utf8mb4", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $stmt = $pdo->query("SHOW CREATE TABLE seat_sessions");
    $res = $stmt->fetch(PDO::FETCH_ASSOC);
    file_put_contents('table_schema.txt', print_r($res, true));
    print_r($res);
} catch (Exception $e) {
    echo $e->getMessage();
}
