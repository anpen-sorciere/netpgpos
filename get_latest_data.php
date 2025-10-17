<?php
require_once('../common/dbconnect.php');
require_once('../common/functions.php');
header('Content-Type: application/json; charset=UTF-8');

$pdo = connect();

$data = [
    'items' => item_get_all($pdo),
    'casts' => cast_get_all($pdo)
];

echo json_encode($data);

disconnect($pdo);
?>
