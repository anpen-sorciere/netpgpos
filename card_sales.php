<?php 
require_once('../common/dbconnect.php');
session_cache_limiter('none');
session_start();
    $uid = $_SESSION['user_id'];
    $utype = 0;
	if(isset($_GET['utype'])) { 
		$utype = $_GET['utype'];
		$_SESSION['utype'] = $utype;
	}elseif(isset($_SESSION['utype'])) {
		$utype = $_SESSION['utype'];
	}

if (!empty($_POST)) {
    /* エラーがなければ次のページへ */
    if (!isset($error)) {
        $_SESSION['join'] = $_POST;   // フォームの内容をセッションで保存
        header('Location: card_sales_check.php');   // check.phpへ移動
        exit();
    }

}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0,minimum-scale=1.0">
    <title>コレクト販売仕入れ人件費入力</title>
    <link href="https://unpkg.com/sanitize.css" rel="stylesheet"/>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="content">
        <form action="" method="POST">
            <h2>販売仕入れ入力</h2>
            <br>
    <table><tbody>
            <div class="control">
				<tr>
				<th>
                <label for="shop_mst">店舗コード</label>
                <?php
                	if($_SESSION['utype'] == 3) {
                ?>
                		コレクト
                <?php
                	} else{
                		exit();
					}
				?>
                </th>
                <th>
                <label for="data_day">日付</label>
                <input id="data_day" type="date" name="data_day">
                </th>
                </tr>
	</tbody></table>

                
            </div>
 <hr>
<h2>データ登録</h2>
    <table><tbody>
	    <tr>
			<th>
				カード売上
				<input id="sales_amount" name="sales_amount" autocomplete="off">
			</th>
		</tr>
	    <tr>
			<th>
				仕入買取
				<input id="purchase_cost" name="purchase_cost" autocomplete="off">
			</th>
		</tr>
	    <tr>
			<th>
				コレクト人件費(1日合計)
				<input id="personnel_cost" name="personnel_cost" autocomplete="off">
			</th>
		</tr>
	</tbody></table>
 <hr>            <div class="control">
                <button type="submit" class="btn">確認する</button>
				<a href="index.php">メニューへ</a>
            </div>
        </form>
    </div>
    
</body>
</html>
