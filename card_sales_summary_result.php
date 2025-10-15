<?php
require("./dbconnect.php");
session_start();

?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0,minimum-scale=1.0">
    <title>カード販売仕入れデータ確認画面</title>
    <link href="https://use.fontawesome.com/releases/v5.6.1/css/all.css" rel="stylesheet">
    <link href="https://unpkg.com/sanitize.css" rel="stylesheet"/>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
	    <div class="content">
	    </div>
	    <?php
		    $ymd = explode('-', $_SESSION['join']['c_month']);
		    $eymd = explode('-', $_SESSION['join']['ec_month']);
		    $utype = $_SESSION['utype'];
		?>
		
	    集計年月<?=htmlspecialchars($ymd[0])?>年<?=htmlspecialchars($ymd[1])?>月<?=htmlspecialchars($ymd[2])?>日～<?=htmlspecialchars($eymd[0])?>年<?=htmlspecialchars($eymd[1])?>月<?=htmlspecialchars($eymd[2])?>日
	    <br>
		
	    <table border="1"><tbody>
	    <tr><th>日付</th><th>カード売上</th><th>カード仕入買取</th><th>人件費総計</th></tr>
		<?php
			$urigoukei = 0;	//合計金額
			$kaigoukei = 0;	//仕入金額
			$hitogoukei = 0;	//人件費金額
		    $db=connect();
		    $stmh = $db->prepare("SELECT * FROM card_sales_temp_tbl WHERE data_day BETWEEN ? and ? ORDER BY data_day");
		    $startymd = $ymd[0].$ymd[1].$ymd[2];
		    $endymd = $eymd[0].$eymd[1].$eymd[2];
		    $stmh->execute(array($startymd,$endymd));
		    while($row = $stmh->fetch(PDO::FETCH_ASSOC)){
		?>
			<tr>
		        <td align="center"><?=htmlspecialchars($row['data_day'])?></td>
		        <td align="right"><?=htmlspecialchars($row['sales_amount'])?></td>
		        <td align="right"><?=htmlspecialchars($row['purchase_cost'])?></td>
		        <td align="right"><?=htmlspecialchars($row['personnel_cost'])?></td>
		    </tr>

		<?php
				$urigoukei += $row['sales_amount'];
				$kaigoukei += $row['purchase_cost'];
				$hitogoukei += $row['personnel_cost'];
		
		    }
		    $db = null;
		?>
		<tr>
			<td align="center">合計金額</td>
			<td align="right"><?=htmlspecialchars($urigoukei)?></td>
			<td align="right"><?=htmlspecialchars($kaigoukei)?></td>
			<td align="right"><?=htmlspecialchars($hitogoukei)?></td>
		</tr>
		</tbody></table>
        <div class="control">
			<a href="card_sales_summary.php">戻る</a>
			<a href="index.php">メニューへ</a>
        </div>
</body>
</html>
