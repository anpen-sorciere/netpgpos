<?php
require_once(__DIR__ . '/../common/dbconnect.php');
$pdo = connect();
$stmt = $pdo->query("DESCRIBE seat_sessions");
$columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
print_r($columns);
