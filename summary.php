<?php
require_once('../common/dbconnect.php');
require_once('../common/functions.php');
session_start();

$utype_all = 0;
if (isset($_GET['utype'])) {
    $utype_all = $_GET['utype'];
    $_SESSION['utype'] = $utype_all;
} elseif (isset($_SESSION['utype'])) {
    $utype_all = $_SESSION['utype'];
}

$error = [];
$pdo = null;
$payment_list = [];

try {
    $pdo = connect();
    $payment_list = payment_get_all($pdo);
} catch (PDOException $e) {
    $error['db'] = "データベースエラー: " . h($e->getMessage());
    error_log("Database Error: " . $e->getMessage());
} finally {
    disconnect($pdo);
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0,minimum-scale=1.0">
    <title>売上集計データ</title>
    <link href="https://unpkg.com/sanitize.css" rel="stylesheet"/>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <form action="summary_result.php" method="POST">
            <h1>売上集計データ</h1>
            <br>
            <input type="hidden" name="utype" value="<?= h($utype_all) ?>">

            <div class="control">
                <label for="c_day">確認開始日付</label>
                <input type="date" name="c_day" value="<?php echo date('Y-m-d'); ?>">
            </div>
            <div class="control">
                <label for="ec_day">確認終了日付</label>
                <input type="date" name="ec_day" value="<?php echo date('Y-m-d'); ?>">
            </div>
            
            <div class="control">
                <label for="p_type">支払い方法</label>
                <select name="p_type" id="p_type">
                    <option value="0">全部</option>
                    <option value="-1">現金以外</option>
                    <?php foreach ($payment_list as $row): ?>
                        <option value="<?= h($row["payment_type"]) ?>">
                            <?= h($row["payment_name"]) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <br>

            <div class="control-buttons">
                <button type="submit" class="btn btn-primary">確認する</button>
                <a href="index.php?utype=<?= h($utype_all) ?>" class="btn btn-secondary">メニューへ</a>
            </div>
        </form>
    </div>
</body>
</html>
