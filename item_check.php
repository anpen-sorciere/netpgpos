<?php
require("./dbconnect.php");
session_start();

/* 会員登録の手続き以外のアクセスを飛ばす */
if (!isset($_SESSION['join'])) {
    header('Location: item_mst.php');
    exit();
}

$pdo = connect();

if (!empty($_POST['check'])) {
    // 入力情報をデータベースに登録
    $statement = $pdo->prepare("INSERT INTO item_mst SET item_name=?, category=?, price=?, back_price=?");
    $statement->execute(array(
        $_SESSION['join']['item_name'],
        $_SESSION['join']['cate_type'],
        $_SESSION['join']['price'],
        $_SESSION['join']['back_price']
    ));

    unset($_SESSION['join']); // セッションを破棄
    header('Location: item_mst_finish.php'); // finish.phpへ移動
    exit();
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0,minimum-scale=1.0">
    <title>商品登録確認画面</title>
    <link href="https://use.fontawesome.com/releases/v5.6.1/css/all.css" rel="stylesheet">
    <link href="https://unpkg.com/sanitize.css" rel="stylesheet"/>
    <link rel="stylesheet" href="css/style.css">
    <style>
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            border: 1px solid #ccc;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
            width: 30%;
        }
        .check-info {
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="content">
        <form action="" method="POST">
            <input type="hidden" name="check" value="checked">
            <h1>入力情報の確認</h1>
            <p>ご入力情報に変更が必要な場合、下のボタンを押し、変更を行ってください。</p>
            <p>登録情報はあとから変更することもできます。</p>
            <?php if (!empty($error) && $error === "error"): ?>
                <p class="error">＊商品登録に失敗しました。</p>
            <?php endif ?>
            <hr>
            
            <table>
                <tbody>
                    <tr>
                        <th>商品名</th>
                        <td><span class="fas fa-angle-double-right"></span> <span class="check-info"><?= htmlspecialchars($_SESSION['join']['item_name'] ?? '', ENT_QUOTES); ?></span></td>
                    </tr>
                    <tr>
                        <th>価格</th>
                        <td><span class="fas fa-angle-double-right"></span> <span class="check-info"><?= htmlspecialchars($_SESSION['join']['price'] ?? '', ENT_QUOTES); ?>円</span></td>
                    </tr>
                    <tr>
                        <th>バック価格</th>
                        <td><span class="fas fa-angle-double-right"></span> <span class="check-info"><?= htmlspecialchars($_SESSION['join']['back_price'] ?? '', ENT_QUOTES); ?>円</span></td>
                    </tr>
                    <tr>
                        <th>カテゴリー</th>
                        <?php
                            $cate_type = $_SESSION['join']['cate_type'] ?? '';
                            $cate_data = item_category_data_get($pdo, $cate_type);
                            $cate_name = htmlspecialchars($cate_data["category_name"] ?? '', ENT_QUOTES);
                        ?>
                        <td><span class="fas fa-angle-double-right"></span> <span class="check-info"><?= $cate_name; ?></span></td>
                    </tr>
                </tbody>
            </table>

            <br>
            <a href="item_mst.php" class="back-btn">変更する</a>
            <button type="submit" class="btn next-btn">登録する</button>
            <div class="clear"></div>
        </form>
    </div>
</body>
</html>
