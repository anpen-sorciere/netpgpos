<?php
require("./dbconnect.php");
require("./functions.php");
session_start();


?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0,minimum-scale=1.0">
    <title>月別タイムカード確認画面</title>
    <link href="https://use.fontawesome.com/releases/v5.6.1/css/all.css" rel="stylesheet">
    <link href="https://unpkg.com/sanitize.css" rel="stylesheet"/>
    <link rel="stylesheet" href="css/style.css">
	<style>
	  td {
	    text-align: center;
	  }

	  /* .right-aligned-table クラスを持つテーブル内の
	     すべてのセル（thとtd）のテキストを右寄せにする */
	  .right-aligned-table th,
	  .right-aligned-table td {
	    text-align: right;
	  }


	</style>
</head>
<body>
	    <div class="content">
	    </div>
	    <?php
		    $ymd = explode('-', $_SESSION['join']['in_ym']);
		    $cast_id = $_SESSION['join']['cast_id'];
		    $cast_data = cast_get($cast_id);
		    $cast_name = $cast_data['cast_name'];
		    $startymd = $ymd[0].$ymd[1]."01";
		    $endymd = get_last_day_of_month($ymd[0],$ymd[1]);
		    $utype = $_SESSION['utype'];
		    switch ($utype){
				case 1024:
					$shop_id = 1;
					$shop_name = "ソルシエール";
					break;
				case 3:
					$shop_id = $utype;
					$shop_name = "コレクト";
					break;
				default:
					$shop_id = $utype;
					$shop_name = "レーヴェス";
			}
		?>
	    集計年月<?=htmlspecialchars($ymd[0])?>年<?=htmlspecialchars($ymd[1])?>月
	    <br>
	    集計開始年月日<?=htmlspecialchars($startymd)?>
	    <br>
	    集計終了年月日<?=htmlspecialchars($endymd)?>
	    <br>
	    キャストID<?=htmlspecialchars($cast_id)?>　キャスト名<?=htmlspecialchars($cast_name)?>
		<br>
	    <table><tbody>
	    <tr><th>営業日付</th><th>キャストID</th><th>入店日</th><th>入店時間</th><th>退店日</th><th>退店時間</th><th>勤務時間</th><th>勤務時間百分率</th><th>バック金額</th></tr>
		<?php
//			$backmoneyArray = array_fill(0, 31, null);	//バック金額の月の配列
//			$worktimeArray = array_fill(0, 31, null);	//出勤時間の月の配列
			$backmoneyArray = array_fill(0, 31, 0);	//バック金額の月の配列
			$worktimeArray = array_fill(0, 31, 0);	//出勤時間の月の配列
			
		    $db=connect();
		    $stmh = $db->prepare("SELECT * FROM timecard_tbl where cast_id = ? AND eigyo_ymd BETWEEN ? and ? ORDER BY eigyo_ymd");
		    $stmh->execute(array($cast_id,$startymd,$endymd));
		    $timediff = 0;
		    $backsougoukei = 0;
		    $timesoudiff25 = 0;
		    $dayindex = 0;
		    $dayflg = 0;
		    while($row = $stmh->fetch(PDO::FETCH_ASSOC)){
		    	$timediff = calcTimeDiff($row['in_ymd'].$row['in_time'],$row['out_ymd'].$row['out_time']);
				$timediff25 = convertTimeFormat($timediff);
				$timesoudiff25 = $timesoudiff25 + $timediff25;
				//個別
			    $stmh2 = $db->prepare("SELECT receipt_day,cast_id,quantity,item_id,SUM(quantity) as suu FROM receipt_detail_tbl where shop_id = ? AND cast_id = ? AND receipt_day = ? GROUP BY item_id ORDER BY item_id,cast_id");
			    $stmh2->execute(array($shop_id,$cast_id,$row['eigyo_ymd']));
				$backgoukei = 0;	//バック合計金額
			    $backmoney = 0;
			    while($row3 = $stmh2->fetch(PDO::FETCH_ASSOC)){
			    	//if($row['item_id'] == 0) {continue;}
			    	if($row3['item_id'] > 0) {
			        	$row2 = item_get($row3['item_id']);
						$backmoney = $row3['suu'] * $row2['back_price'];
						$backgoukei = $backgoukei + $backmoney;
					}
				}
				$backsougoukei = $backsougoukei + $backgoukei;
				//日付のみ取得し-1したものをINDEXとする。但し1ループで2回目の0,1,2は翌月の1,2,3日のため削除(2月考慮)
				$dayindex = $row['eigyo_ymd'] % 100 - 1;
				if($dayflg == 0 && $dayindex == 3) {
					//4日が着たタイミングでフラグを立てて2回目の1～3日に備える
					$dayflg = 1;
				}
				if (!($dayflg == 1 && $dayindex <= 2)) {
					$backmoneyArray[$dayindex] = $backgoukei;
					$worktimeArray[$dayindex] = $timediff25;
				}

		?>
		    <tr>
		        <td><?=htmlspecialchars($row['eigyo_ymd'])?></td>
		        <td><?=htmlspecialchars($row['cast_id'])?></td>
		        <td><?=htmlspecialchars($row['in_ymd'])?></td>
		        <td><?=htmlspecialchars($row['in_time'])?></td>
		        <td><?=htmlspecialchars($row['out_ymd'])?></td>
		        <td><?=htmlspecialchars($row['out_time'])?></td>
		        <td><?=htmlspecialchars($timediff)?></td>
		        <td><?=htmlspecialchars($timediff25)?></td>
		        <td><?=htmlspecialchars($backgoukei)?></td>
		    </tr>
		    
		<?php
		    }
		    $db = null;
		?>
		    <tr>
		        <td></td>
		        <td></td>
		        <td></td>
		        <td></td>
		        <td></td>
		        <td></td>
		        <td></td>
		        <td><?=htmlspecialchars($timesoudiff25)?></td>
		        <td><?=htmlspecialchars($backsougoukei)?></td>
		    </tr>
		</tbody></table>
		スプレッドシートへのコピペ用
	    <table class="right-aligned-table"><tbody>
		<?php
			for ($i = 0; $i < 31; $i++) {
		?>
			<tr>
		        <td><?=htmlspecialchars($backmoneyArray[$i])?></td>
		        <td><?=htmlspecialchars($worktimeArray[$i])?></td>
		    </tr>
		<?php
		    }
		?>
		</tbody></table>
		
        <div class="control">
			<a href="timecard_disp_select.php">戻る</a>
			<a href="index.php">メニューへ</a>
        </div>
</body>
</html>
