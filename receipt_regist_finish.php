<?php
require_once(__DIR__ . '/../common/config.php');
require_once(__DIR__ . '/../common/dbconnect.php');
require_once(__DIR__ . '/../common/functions.php');
session_cache_limiter('none');
session_start();
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0,minimum-scale=1.0">
    <title>登録完了</title>
    <link href="https://unpkg.com/sanitize.css" rel="stylesheet"/>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="content">
        <h1>伝票登録が完了しました。</h1>
        <p>下のボタンよりメニューページに移動してください。</p>
        <br><br>
        <a href="index.php"><button class="btn">メニュー</button></a>
		<?php
			// GETパラメータから値を取得
			$initday = $_GET['initday'];
			//SESSIONから店コード取得(1024ソルシ,2レーヴェス,3コレクト)
			$utype = $_SESSION['utype'];
			if($utype > 3 or $utype < 2) {
				$utype = 1024;
			}

			// 遷移先URLを生成
			$destination_url = "receipt_input.php?utype=" . $utype . "&initday=" . $initday;

			// HTML (リンク)
  			echo '<a href="' . $destination_url . '">伝票登録</a>';
		?>
    </div>
</body>
</html>
