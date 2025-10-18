<?php
require("../common/dbconnect.php");
session_start();


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
		    	}
		        	//ここで商品IDから商品データ取得
		        	if($row['item_id'] > 0) {
			        	$row2 = item_get($row['item_id']); 
					}
					//数量と単価から売上金額計算
					$syoukei = $row['quantity'] * $row2['price'];
					$goukei = $goukei + $syoukei;

		    }
		    $db = null;

		    $db=connect();
		    $stmh3 = $db->prepare("SELECT * FROM card_sales_temp_tbl WHERE data_day = ? ORDER BY data_day");
		    $stmh3->execute(array($getymd));
		    while($row = $stmh3->fetch(PDO::FETCH_ASSOC)){
				$cardsales = $row['sales_amount'];
				$cardsiire = $row['purchase_cost'];
				$jinkenhi = $row['personnel_cost'];
		    }
		    $db = null;
		    
		   //メール送信
//			$to = 'up4s.myt@gmail.com';
			$to = 'aqua.7505@gmail.com';
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
