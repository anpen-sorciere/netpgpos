<?php
require_once 'c:/xampp/htdocs/common/dbconnect.php';
$pdo = connect();
$stmt = $pdo->query('DESCRIBE base_orders');
print_r($stmt->fetchAll(PDO::FETCH_COLUMN));
