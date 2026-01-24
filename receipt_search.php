<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once(__DIR__ . '/../common/config.php');
require_once(__DIR__ . '/../common/dbconnect.php');
require_once(__DIR__ . '/../common/functions.php');
session_start();

$utype_all = 0;
if (isset($_GET['utype'])) {
    $utype_all = $_GET['utype'];
    $_SESSION['utype'] = $utype_all;
} elseif (isset($_SESSION['utype'])) {
    $utype_all = $_SESSION['utype'];
}

$error = [];
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0,minimum-scale=1.0">
    <title>過去伝票確認</title>
    <link href="https://unpkg.com/sanitize.css" rel="stylesheet"/>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <form action="receipt_search_result.php" method="POST">
            <h1>過去伝票確認</h1>
            <br>
            <input type="hidden" name="utype" value="<?= h($utype_all) ?>">

            <div class="control">
                <label for="c_day">開始日付</label>
                <input type="date" name="c_day" value="<?php echo date('Y-m-d'); ?>">
            </div>
            <div class="control">
                <label for="ec_day">終了日付</label>
                <input type="date" name="ec_day" value="<?php echo date('Y-m-d'); ?>">
            </div>
            
            <div class="control">
                <label for="receipt_id">伝票番号 (完全一致)</label>
                <input type="number" name="receipt_id" placeholder="例: 20240101001" style="padding:10px; width:100%; box-sizing:border-box;">
            </div>

            <div class="control">
                <label for="customer_name">顧客名 (部分一致)</label>
                <input type="text" name="customer_name" placeholder="顧客名の一部を入力" style="padding:10px; width:100%; box-sizing:border-box;">
            </div>
            
            <br>

            <div class="control-buttons">
                <button type="submit" class="btn btn-primary">検索する</button>
                <a href="index.php?utype=<?= h($utype_all) ?>" class="btn btn-secondary">メニューへ</a>
            </div>
        </form>
    </div>
</body>
</html>
