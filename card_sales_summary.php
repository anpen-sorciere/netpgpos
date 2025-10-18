<?php 
require_once('../common/dbconnect.php');
session_start();

if (!empty($_POST)) {
    /* エラーがなければ次のページへ */
    if (!isset($error)) {
        $_SESSION['join'] = $_POST;   // フォームの内容をセッションで保存
        header('Location: card_sales_summary_result.php');
        exit();
    }

}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0,minimum-scale=1.0">
    <title>集計データ</title>
    <link href="https://unpkg.com/sanitize.css" rel="stylesheet"/>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="content">
        <form action="" method="POST">
            <h1>カード販売仕入れ確認</h1>
            <br>

            <div class="control">
                <label for="conf_month">確認開始年月</label>
				<input type="date" name="c_month">
            </div>
            <div class="control">
                <label for="conf_emonth">確認終了年月</label>
				<input type="date" name="ec_month">
            </div>

                <br>

            <div class="control">
                <button type="submit" class="btn">確認する</button>
				<a href="index.php">メニューへ</a>
            </div>
        </form>
    </div>
    
</body>
</html>
