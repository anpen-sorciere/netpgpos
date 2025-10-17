<?php
require("./dbconnect.php");
session_start();


if (!empty($_POST['check'])) {

    unset($_SESSION['join']);   // セッションを破棄
    header('Location: index.php');   //
    exit();
}

?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0,minimum-scale=1.0">
    <title>伝票確認画面</title>
    <link href="https://use.fontawesome.com/releases/v5.6.1/css/all.css" rel="stylesheet">
    <link href="https://unpkg.com/sanitize.css" rel="stylesheet"/>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="content">
        <form action="" method="POST">
            <input type="hidden" name="check" value="checked">

<h2>伝票基本情報</h2>
			<table><tbody>
			<tr>
				<td>
	                <p>店舗コード</p>
	                <p><?php echo htmlspecialchars($_SESSION['join']['shop_mst'], ENT_QUOTES); ?></p>
	            </td>
	            <td>
                <p>座席番号</p>
                <p><?php echo htmlspecialchars($_SESSION['join']['sheet_no'], ENT_QUOTES); ?></p>
	            </td>
    	        <td>
                <p>伝票集計日付</p>
                <p><?php echo htmlspecialchars($_SESSION['join']['receipt_day'], ENT_QUOTES); ?></p>
	            </td>
	         </tr>   
	         <tr>
	         <td>
                <p>入店日付</p>
                <p><?php echo htmlspecialchars($_SESSION['join']['in_date'], ENT_QUOTES); ?></p>
            </td>
            <td>
                <p>入店時間</p>
                <p><?php echo htmlspecialchars($_SESSION['join']['in_time'], ENT_QUOTES); ?></p>
            </td>
            <td>
                <p>退店日付</p>
                <p><?php echo htmlspecialchars($_SESSION['join']['out_date'], ENT_QUOTES); ?></p>
            </td>
            <td>
                <p>退店時間</p>
                <p><?php echo htmlspecialchars($_SESSION['join']['out_time'], ENT_QUOTES); ?></p>
            </td>
            </tr>
            <tr>
            <td>
                <p>顧客名</p>
                <p><?php echo htmlspecialchars($_SESSION['join']['customer_name'], ENT_QUOTES); ?></p>
            </td>
            <td>
                <p>伝票起票者</p>
                <p><?php echo htmlspecialchars($_SESSION['join']['issuer_id'], ENT_QUOTES); ?></p>
			</td>
			<td>
                <p>支払い方法</p>
				<?php
					$p_type = $_SESSION['join']['p_type'];
					$p_data = payment_data_get($p_type);
					$payment_name = htmlspecialchars($p_data["payment_name"],ENT_QUOTES);
				?>
	            <p>
	            	<?=htmlspecialchars($p_type, ENT_QUOTES); ?>
	            	<?=htmlspecialchars($payment_name, ENT_QUOTES); ?>
	            </p>
	            </td>
	            </tr>
	         </tbody></table>
<h2>伝票明細</h2>
<table><tbody>
		<?php
			for($i=1;$i<=11;$i++) {
		?>
		    <tr>
				<?php
					$item_id = $_SESSION['join']["item_name$i"];
					$item_data = item_get($item_id);
					$item_name = htmlspecialchars($item_data["item_name"],ENT_QUOTES);
				?>
	            <th>
	            	<?=htmlspecialchars($item_id, ENT_QUOTES); ?>
	            	<?=htmlspecialchars($item_name, ENT_QUOTES); ?>
	            </th>
				<?php
					$cast_id = $_SESSION['join']["cast_name$i"];
					$cast_data = cast_get($cast_id);
					$cast_name = htmlspecialchars($cast_data["cast_name"],ENT_QUOTES);
				?>
	            <th>
	            	<?=htmlspecialchars($cast_id, ENT_QUOTES); ?>
	            	<?=htmlspecialchars($cast_name, ENT_QUOTES); ?>
	            </th>
	            <th><?php echo htmlspecialchars($_SESSION['join']["price$i"], ENT_QUOTES); ?></th>
	            <th><?php echo htmlspecialchars($_SESSION['join']["suu$i"], ENT_QUOTES); ?></th>
	            <th><?php echo htmlspecialchars($_SESSION['join']["sumprice$i"], ENT_QUOTES); ?></th>
			</tr>
		<?php
			}
		?>

</tbody></table>

            
            <br>
            <a href="receipt_confirm.php" class="back-btn">他の伝票確認</a>
            <button type="submit" class="btn next-btn">メニューへ</button>
            <div class="clear"></div>
        </form>
    </div>
</body>
</html>
