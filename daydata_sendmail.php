<?php
require("../common/dbconnect.php");
session_start();


?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0,minimum-scale=1.0">
    <title>売上データ確認画面</title>
    <link href="https://use.fontawesome.com/releases/v5.6.1/css/all.css" rel="stylesheet">
    <link href="https://unpkg.com/sanitize.css" rel="stylesheet"/>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
	    <div class="content">
	    </div>
	    <?php
			$firstDate = date("Y-m-d");
			$prevDateObj = new DateTime($firstDate .'-1 day');
	    	$nowHour = intval(date('H'));	//現在の「時」のみ取得
	    	if($nowHour <= 10) {
		    	//0時～10:59:59に稼働した場合は1日前のデータ取得
			    $ymd = explode('-',$prevDateObj->format('Y-m-d'));
			} else {
			    //それ以外の時間は当日データ取得
			    $ymd = explode('-',$firstDate);
		    }
		    $utype = $_SESSION['utype'];
		?>
	    集計年月<?=htmlspecialchars($ymd[0])?>年<?=htmlspecialchars($ymd[1])?>月<?=htmlspecialchars($ymd[2])?>日
	    <br>
       	支払い方法：<?=htmlspecialchars($_SESSION['join']['p_type'])?>
	    <?php
	    	if($_SESSION['join']['p_type'] == 0) {
	    ?>
	    		全部
	    <?php
	    	}else {
			    $pdo=connect();
			    $stmt = $pdo->prepare("SELECT * FROM payment_mst WHERE payment_type = ?");
			    $stmt->execute(array($_SESSION['join']['p_type']));
			    $row = $stmt->fetch(PDO::FETCH_ASSOC);
		?>
				<?=htmlspecialchars($row['payment_name'])?>
		<?php
			}
		?>
		
	    <table><tbody>
	    <tr><th>売上日付</th><th>商品ID</th><th>商品名</th><th>数量</th><th>金額</th><th>キャストID</th><th>キャスト名</th></tr>
		<?php
			$goukei = 0;	//合計金額
		    $db=connect();
		    $stmh = $db->prepare("SELECT * FROM receipt_detail_tbl where shop_mst = ? AND receipt_day = ? ORDER BY receipt_day");
		    $getymd = $ymd[0].$ymd[1].$ymd[2];
		    $stmh->execute(array($utype,$getymd));
		    $receipt_id_old = 0;
		    while($row = $stmh->fetch(PDO::FETCH_ASSOC)){
		    	if($row['item_id'] == 0) {continue;}
			    $stmh2 = $db->prepare("SELECT payment_type FROM receipt_tbl where shop_mst = ? and receipt_id = ?");
			    $stmh2->execute(array($utype,$row['receipt_id']));
			    $row2 = $stmh2->fetch(PDO::FETCH_ASSOC);
			    if($_SESSION['join']['p_type'] > 0) {
				    if($row2['payment_type'] != $_SESSION['join']['p_type']) {continue;}
				}
		    	//レシートIDを前行と比べ違っていれば別レシートになるので一行空白
		    	if($receipt_id_old == 0) {
		    		//始めてなので改行無し
		    		$receipt_id_old = $row['receipt_id'];
		    	}
	    		if($receipt_id_old != $row['receipt_id']) {
	    			//違ったので改行
	    			$receipt_id_old = $row['receipt_id'];
	    ?>
	    			<tr>
				        <td><?=htmlspecialchars("　")?></td>
	    			</tr>
	    <?php
		    	}
		?>
		    <tr>
		        <td><?=htmlspecialchars($row['receipt_day'])?></td>
		        <td align="center"><?=htmlspecialchars($row['item_id'])?></td>
		        <?php
		        	//ここで商品IDから商品データ取得
		        	if($row['item_id'] > 0) {
			        	$row2 = item_get($row['item_id']); 
				?>
						<td><?=htmlspecialchars($row2['item_name'])?></td>
		        <?php }else{ ?>
		        		<td></td>
		        <?php } ?>
				<?php
					//数量と単価から売上金額計算
					$syoukei = $row['quantity'] * $row2['price'];
					$goukei = $goukei + $syoukei;
				?>
		        <td align="center"><?=htmlspecialchars($row['quantity'])?></td>
		        <td align="center"><?=htmlspecialchars($syoukei)?></td>
		        <?php
		        	if($row['cast_id'] > 0) {
			        	$cast_data = cast_get($row['cast_id']); 
		        ?>
				        <td align="center"><?=htmlspecialchars($row['cast_id'])?></td>
				        <td><?=htmlspecialchars($cast_data['cast_name'])?></td>
		        <?php }else{ ?>
		        		<td></td>
		        		<td></td>
		        <?php } ?>
		    </tr>
		<?php
		    }
		    $db = null;
		?>
		</tbody></table>
		カフェ売上金額 <?=htmlspecialchars($goukei)?>
		<br>
		人件費等日付 <?=htmlspecialchars($getymd)?>
		<br>
		<?php
		    $db=connect();
		    $stmh3 = $db->prepare("SELECT * FROM card_sales_temp_tbl WHERE data_day = ? ORDER BY data_day");
		    $stmh3->execute(array($getymd));
		    while($row = $stmh3->fetch(PDO::FETCH_ASSOC)){
		?>
		
	    <table border="1"><tbody>
		    <tr><th>日付</th><th>カード売上</th><th>カード仕入買取</th><th>人件費総計</th></tr>
			<tr>
		        <td align="center"><?=htmlspecialchars($row['data_day'])?></td>
		        <td align="right"><?=htmlspecialchars($row['sales_amount'])?></td>
		        <td align="right"><?=htmlspecialchars($row['purchase_cost'])?></td>
		        <td align="right"><?=htmlspecialchars($row['personnel_cost'])?></td>
		    </tr>
		</tbody></table>
		<?php
				$cardsales = $row['sales_amount'];
				$cardsiire = $row['purchase_cost'];
				$jinkenhi = $row['personnel_cost'];
		    }
		    $db = null;
		    
		    
		   //メール送信
			$to = 'up4s.myt@gmail.com';
//			$to = 'aqua.7505@gmail.com';
			$subject = strval($getymd).'コレクト日計';
			$message = strval($getymd)."\n".'カフェ売上：'.$goukei."\n";
			$message = $message.'カード売上:'.$cardsales."\n";
			$message = $message.'カード仕入買取:'.$cardsiire."\n";
			$message = $message.'人件費:'.$jinkenhi;
			
			$headers = 'From: aqua.7505@gmail.com';
			$headers .= "\r\n";
			$headers .= "Bcc: hirooka@lush-inc.net";
			mail($to, $subject, $message, $headers);
		?>
		
		

        <div class="control">
			<a href="summary.php">戻る</a>
			<a href="index.php">メニューへ</a>
        </div>
</body>
</html>
