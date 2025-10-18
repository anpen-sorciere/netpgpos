<?php
require("../common/dbconnect.php");
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


if (!empty($_POST['check'])) {
    // 入力情報をデータベースに登録
	$data_day = $_SESSION['join']['data_day'];
	$sales_amount = $_SESSION['join']['sales_amount'];
	$purchase_cost = $_SESSION['join']['purchase_cost'];
	$personnel_cost = $_SESSION['join']['personnel_cost'];
	$old_sales_amount = $_SESSION['join']['old_sales_amount'];
	$old_purchase_cost = $_SESSION['join']['old_purchase_cost'];
	$old_personnel_cost = $_SESSION['join']['old_personnel_cost'];
	$update_flg = $_SESSION['join']['update_flg'];
	$uri_flg = $_SESSION['join']['uri_flg'];
	$siire_flg = $_SESSION['join']['siire_flg'];
	$jinkenhi_flg = $_SESSION['join']['jinkenhi_flg'];

//データ登録

    $db2=connect();
	if($update_flg == 0) {
	    $statement = $db2->prepare("INSERT INTO card_sales_temp_tbl SET data_day=?, sales_amount=?, purchase_cost=?, personnel_cost=?");
		if($sales_amount=="") {
			$sales_amount = 0;
		}
		if($purchase_cost=="") {
			$purchase_cost = 0;
		}
		if($personnel_cost=="") {
			$personnel_cost = 0;
		}		
	    $statement->execute(array(
	        $data_day,
	        $sales_amount,
	        $purchase_cost,
	        $personnel_cost
	    ));
	} else {
		if($uri_flg == 1) $sales_amount = $old_sales_amount;
		if($siire_flg == 1) $purchase_cost = $old_purchase_cost;
		if($jinkenhi_flg == 1) $personnel_cost = $old_personnel_cost;
	    $statement = $db2->prepare("UPDATE card_sales_temp_tbl SET  sales_amount=?, purchase_cost=?, personnel_cost=? WHERE data_day=?");
	    $statement->execute(array(
	        $sales_amount,
	        $purchase_cost,
	        $personnel_cost,
	        $data_day
	    ));
	}
	
    unset($_SESSION['join']);   // セッションを破棄
    header('Location: card_sales_finish.php');   //
    exit();
}

?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0,minimum-scale=1.0">
    <title>コスト登録確認画面</title>
    <link href="https://use.fontawesome.com/releases/v5.6.1/css/all.css" rel="stylesheet">
    <link href="https://unpkg.com/sanitize.css" rel="stylesheet"/>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="content">
        <form action="" method="POST">
            <input type="hidden" name="check" value="checked">
            <h1>入力情報の確認</h1>
            <p>ご入力情報に変更が必要な場合、下のボタンを押し、変更を行ってください。</p>
            <p>登録情報はあとから変更することもできます。</p>
            <hr>

<h2>伝票基本情報</h2>
            <div class="control">
                <p>店舗コード</p>
				<?php
					//指定日のデータがあれば取得
					$data_day = $_SESSION['join']['data_day'];
					$work_data_day = str_replace('-', '', $data_day);
					$data_day = (int)$work_data_day;
				    $db=connect();
				    $statement = $db->prepare("SELECT * FROM card_sales_temp_tbl WHERE data_day=?");
				    $statement->execute(array(
				        $data_day
				    ));
				    $result = $statement->fetch(PDO::FETCH_ASSOC);
				    
					$old_sales_amount = "既存無し";
					$old_purchase_cost = "既存無し";
					$old_personnel_cost = "既存無し";
					$update_flg = 0;	//0:新規 1:更新
					if(!empty($result)) {
						$old_sales_amount = $result['sales_amount'];
						$old_purchase_cost = $result['purchase_cost'];
						$old_personnel_cost = $result['personnel_cost'];
						$update_flg = 1;
					}
				    echo htmlspecialchars($data_day, ENT_QUOTES);
				    echo htmlspecialchars($old_sales_amount, ENT_QUOTES);
				    echo htmlspecialchars($old_purchase_cost, ENT_QUOTES);
				    echo htmlspecialchars($old_personnel_cost, ENT_QUOTES);
					$_SESSION['join']['data_day'] = $data_day;
					$_SESSION['join']['update_flg'] = $update_flg;
				?>           
                <?php
					if($_SESSION['utype'] == 3) {	?>
						<p><span class="check-info">コレクト</span></p>
				<?php
					}else{
				?>
					<p><span class="check-info"><?php echo htmlspecialchars($_SESSION['join']['shop_mst'], ENT_QUOTES); ?></span></p>
				<?php } ?>
            </div>
            <div class="control">
                <p>日付：
                <?php echo htmlspecialchars($data_day, ENT_QUOTES); ?>
	            </p>
            </div>
            <div class="control">
                <p>売上：
                <?php
                	$uri_flg = 0;
                	if(strval($_SESSION['join']['sales_amount'])=="") {
                		$uri_flg = 1;
                ?>
	                データ変更なし
	                <?php echo htmlspecialchars($old_sales_amount, ENT_QUOTES); ?>
		        <?php
		        	} else {
		        ?>
	                <?php echo htmlspecialchars($_SESSION['join']['sales_amount'], ENT_QUOTES); ?>
	            <?php } ?>
	            </p>
            </div>
            <div class="control">
                <p>仕入れ買取：
                <?php
                	$siire_flg = 0;
                	if(strval($_SESSION['join']['purchase_cost'])=="") {
                		$siire_flg = 1;
                ?>
	                データ変更なし
	                <?php echo htmlspecialchars($old_purchase_cost, ENT_QUOTES); ?>
		        <?php
		        	} else {
		        ?>
	                <?php echo htmlspecialchars($_SESSION['join']['purchase_cost'], ENT_QUOTES); ?>
	            <?php } ?>
	            </p>


            </div>
            <div class="control">
                <p>人件費：
                <?php
                	$jinkenhi_flg = 0;
                	if(strval($_SESSION['join']['personnel_cost'])=="") {
                		$jinkenhi_flg = 1;
                ?>
	                データ変更なし
	                <?php echo htmlspecialchars($old_personnel_cost, ENT_QUOTES); ?>
		        <?php
		        	} else {
		        ?>
	                <?php echo htmlspecialchars($_SESSION['join']['personnel_cost'], ENT_QUOTES); ?>
	            <?php } ?>
	            </p>
            </div>
			<?php
				//フラグ系のセッションへの保存
                $_SESSION['join']['uri_flg'] = $uri_flg;
                $_SESSION['join']['siire_flg'] = $siire_flg;
                $_SESSION['join']['jinkenhi_flg'] = $jinkenhi_flg;
                //データのセッションへの保存
				$_SESSION['join']['old_sales_amount'] = $old_sales_amount;
				$_SESSION['join']['old_purchase_cost'] = $old_purchase_cost;
				$_SESSION['join']['old_personnel_cost'] = $old_personnel_cost;
			?>

            <br>
			<?php
			echo '<a href="' . $_SERVER['HTTP_REFERER'] . '">変更する</a>';
			?>
            <button type="submit" class="btn next-btn">登録する</button>
            <div class="clear"></div>
        </form>
    </div>
</body>
</html>
