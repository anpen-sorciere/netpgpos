<?php 
require_once('./dbconnect.php');
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
        header('Location: receipt_check.php');   // check.phpへ移動
        exit();
    }

}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0,minimum-scale=1.0">
    <title>カード系販売仕入れ入力</title>
    <link href="https://unpkg.com/sanitize.css" rel="stylesheet"/>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="content">
        <form action="" method="POST">
            <h2>伝票基本情報登録</h2>
            <br>
    <table><tbody>
            <div class="control">
				<tr>
				<th>
                <label for="shop_id">店舗コード</label>
                <?php
                	if($_SESSION['utype'] == 3) {
                ?>
                		コレクト
                <?php
                	} else{
                ?>
		                <select name="shop_id" id="shop_id">
							<option value="1">ソルシエール</option>
							<option value="2">レーヴェス</option>
						</select>
				<?php
					}
				?>
                </th>
                <th>
                <label for="sheet_no">座席番号</label>
                <input id="sheet_no" type="text" name="sheet_no">
                </th>
                <th>
                <label for="receipt_day">伝票集計日付</label>
                <input id="receipt_day" type="date" name="receipt_day">
                </th>
                </tr>
                <tr>
                <th>
                <label for="check-in_date">入店日付</label>
                <input id="check-in_date" type="date" name="in_date">
                </th>
                <th>
                <label for="check-in_time">入店時間</label>
                <input id="check-in_time" type="time" name="in_time">
                </th>
                </tr>
                <tr>
                <th>
                <label for="check-out_date">退店日付</label>
                <input id="check-out_date" type="date" name="out_date">
                </th>
                <th>
                <label for="check-out_time">退店時間</label>
                <input id="check-out_time" type="time" name="out_time">
                </th>
                </tr>
                <tr>
                <th>
                <label for="customer_name">顧客名</label>
                <input id="customer_name" type="text" name="customer_name">
                </th>
                </tr>
                <tr>
                <th>
                <label for="issuer_id">伝票起票者</label>
                <input id="issuer_id" type="text" name="issuer_id">
                </th>
                </tr>
	</tbody></table>



                <label for="payment_type">支払い方法</label>
                <select name="p_type" id="p_type">
				<?php
				    $pdo=connect();
				    $stmt = $pdo->prepare("SELECT * FROM payment_mst ORDER BY payment_type");
				    $stmt->execute();
				    $all = $stmt->fetchAll();
				    foreach($all as $row){
				?>
				    <tr>
						<option value="<?php echo $row["payment_type"]; ?>">
						<?php echo htmlspecialchars($row["payment_name"],ENT_QUOTES); ?>
						</option>
				    </tr>
				<?php
				    }
				?>
				</select>
				
                <br>
                
            </div>
 <hr>
 <!-- 伝票明細11行(紙に合わせる) -->
<h2>伝票明細登録</h2>
    <table><tbody>
    	<tr><th>商品名</th><th>キャスト</th><th>数量</th></tr>


		<?php
			for($i=1;$i<=11;$i++){
		?>
	    <tr>
			<th>
                <select name="item_name<?=$i; ?>" id="item_name">
					<option value="" selected disabled></option>
				<?php
				    $all = item_get_all();
				    foreach($all as $row){
				?>
					<option value="<?php echo $row["item_id"]; ?>">
						<?php echo htmlspecialchars($row["item_name"],ENT_QUOTES); ?>
					</option>
				<?php
				    }
				?>
				</select>
			</th>
			<th>
                <select name="cast_name<?=$i; ?>" id="cast_name">
					<option value="" selected disabled></option>
				<?php
				    $all = cast_get_all();
				    foreach($all as $row){
				?>
					<option value="<?php echo $row["cast_id"]; ?>">
						<?php echo htmlspecialchars($row["cast_name"],ENT_QUOTES); ?>
					</option>
				<?php
				    }
				?>
				</select>
			</th>
			<input id="price" type="hidden" name="price<?=$i;?>" autocomplete="off">
			<th>
                <select name="suu<?=$i; ?>" id="suu">
					<option value="" selected disabled></option>
				<?php
				    for($v=0;$v<=30;$v++){
				?>
					<option value="<?php echo $v; ?>">
						<?php echo htmlspecialchars($v,ENT_QUOTES); ?>
					</option>
				<?php
				    }
				?>
				</select>
			</th>

			<input id="sumprice" type="hidden" name="sumprice<?=$i;?>" autocomplete="off">
		</tr>
		<?php
			}
		?>
	</tbody></table>
    <table><tbody>
    	<th>割引きなど調整額</th>
    	<th>
			<input id="adjust_price" name="adjust_price" value="0" autocomplete="off">
		</th>
		<th>※割引は-で入力</th>
	</tbody></table>
 <hr>            <div class="control">
                <button type="submit" class="btn">確認する</button>
				<a href="index.php">メニューへ</a>
            </div>
        </form>
    </div>
    
</body>
</html>
