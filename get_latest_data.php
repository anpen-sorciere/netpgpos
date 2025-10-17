<?php
require_once('./dbconnect.php');
require_once('./functions.php');
header('Content-Type: application/json; charset=UTF-8');

$pdo = connect();

$data = [
    'items' => item_get_all($pdo),
    'casts' => cast_get_all($pdo)
];

echo json_encode($data);

disconnect($pdo);
?>
