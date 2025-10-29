<?php
require_once(__DIR__ . '/../common/config.php');
require_once(__DIR__ . '/../common/dbconnect.php');
require_once(__DIR__ . '/../common/functions.php');
header('Content-Type: application/json; charset=UTF-8');

$pdo = connect();

$data = [
    'items' => item_get_all($pdo),
    'casts' => cast_get_all($pdo)
];

echo json_encode($data);

disconnect($pdo);
?>
