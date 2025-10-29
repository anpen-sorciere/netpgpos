<?php
require_once(__DIR__ . '/../common/config.php');
require_once(__DIR__ . '/../common/dbconnect.php');
require_once(__DIR__ . '/../common/functions.php');
header('Content-Type: application/json; charset=UTF-8');

// connect()関数はdbconnect.phpで定義されていると仮定
$pdo = connect();

// カテゴリIDを取得
$category_id = isset($_GET['category_id']) ? $_GET['category_id'] : null;

$items = [];
if ($category_id !== null) {
    // 既存のitem_get_category()関数を呼び出す
    $items = item_get_category($category_id);
}

// 取得したデータをJSON形式で出力
echo json_encode($items);
?>
