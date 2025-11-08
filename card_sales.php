<?php 
require_once(__DIR__ . '/../common/config.php');
require_once(__DIR__ . '/../common/dbconnect.php');
require_once(__DIR__ . '/../common/functions.php');
session_cache_limiter('none');
session_start();

$uid = $_SESSION['user_id'] ?? null;
$utype = 0;
if (isset($_GET['utype'])) {
    $utype = $_GET['utype'];
    $_SESSION['utype'] = $utype;
} elseif (isset($_SESSION['utype'])) {
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

$post = $_SESSION['join'] ?? [];
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0,minimum-scale=1.0">
    <title>コレクト販売仕入れ人件費入力</title>
    <link href="https://unpkg.com/sanitize.css" rel="stylesheet"/>
    <link rel="stylesheet" href="css/style.css">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f0f4f8;
            color: #333;
            margin: 0;
            padding: 20px;
            display: flex;
            justify-content: center;
            align-items: flex-start;
            min-height: 100vh;
        }
        .container {
            width: 100%;
            max-width: 640px;
            background-color: #fff;
            border-radius: 12px;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.1);
            padding: 30px;
        }
        h1 {
            color: #2c3e50;
            text-align: center;
            margin-bottom: 10px;
        }
        h2 {
            margin: 25px 0 10px;
            color: #3498db;
            font-size: 1.2rem;
            border-left: 4px solid #3498db;
            padding-left: 10px;
        }
        .form-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        .form-table th,
        .form-table td {
            padding: 12px;
            border-bottom: 1px solid #eee;
            text-align: left;
        }
        .form-table th {
            width: 40%;
            background-color: #f8f9fb;
            font-weight: 600;
            color: #555;
        }
        .form-table input[type="date"],
        .form-table input[type="number"],
        .form-table input[type="text"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #d0d7de;
            border-radius: 6px;
            font-size: 1rem;
            box-sizing: border-box;
        }
        .form-table input:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.2);
        }
        .control-buttons {
            display: flex;
            justify-content: center;
            gap: 16px;
            margin-top: 25px;
        }
        .btn-primary {
            background-color: #3498db;
            color: #fff;
            border: none;
            padding: 12px 28px;
            border-radius: 25px;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.2s ease;
            box-shadow: 0 6px 18px rgba(52, 152, 219, 0.3);
        }
        .btn-primary:hover {
            background-color: #2c80ba;
            transform: translateY(-2px);
            box-shadow: 0 8px 22px rgba(44, 128, 186, 0.35);
        }
        .btn-secondary {
            display: inline-block;
            padding: 12px 28px;
            border-radius: 25px;
            background-color: #ecf0f1;
            color: #2c3e50;
            text-decoration: none;
            transition: all 0.2s ease;
        }
        .btn-secondary:hover {
            background-color: #d5dadf;
        }
    </style>
</head>
<body>
    <div class="container">
        <form action="" method="POST">
            <input type="hidden" name="utype" value="<?= htmlspecialchars($utype) ?>">
            <h1>コレクト販売仕入れ入力</h1>

            <h2>基本情報</h2>
            <table class="form-table">
                <tbody>
                    <tr>
                        <th>店舗コード</th>
                        <td>
                            <?php if ($utype == 3): ?>
                                コレクト
                            <?php else: ?>
                                -
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="data_day">日付</label></th>
                        <td>
                            <input id="data_day" type="date" name="data_day" value="<?= htmlspecialchars($post['data_day'] ?? '') ?>" required>
                        </td>
                    </tr>
                </tbody>
            </table>

            <h2>データ登録</h2>
            <table class="form-table">
                <tbody>
                    <tr>
                        <th><label for="sales_amount">カード売上</label></th>
                        <td><input id="sales_amount" type="number" name="sales_amount" value="<?= htmlspecialchars($post['sales_amount'] ?? '') ?>" inputmode="numeric" min="0" step="1"></td>
                    </tr>
                    <tr>
                        <th><label for="purchase_cost">仕入買取</label></th>
                        <td><input id="purchase_cost" type="number" name="purchase_cost" value="<?= htmlspecialchars($post['purchase_cost'] ?? '') ?>" inputmode="numeric" min="0" step="1"></td>
                    </tr>
                    <tr>
                        <th><label for="personnel_cost">コレクト人件費(1日合計)</label></th>
                        <td><input id="personnel_cost" type="number" name="personnel_cost" value="<?= htmlspecialchars($post['personnel_cost'] ?? '') ?>" inputmode="numeric" min="0" step="1"></td>
                    </tr>
                </tbody>
            </table>

            <div class="control-buttons">
                <button type="submit" class="btn-primary">確認する</button>
                <a href="index.php?utype=<?= htmlspecialchars($utype) ?>" class="btn-secondary">メニューへ</a>
            </div>
        </form>
    </div>
    
</body>
</html>
