<?php 
require_once(__DIR__ . '/../common/config.php');
require_once(__DIR__ . '/../common/dbconnect.php');
require_once(__DIR__ . '/../common/functions.php');
session_start();

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
        header('Location: card_sales_summary_result.php');
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
    <title>集計データ</title>
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
            max-width: 520px;
            background-color: #fff;
            border-radius: 12px;
            box-shadow: 0 6px 18px rgba(0, 0, 0, 0.1);
            padding: 30px;
        }
        h1 {
            color: #2c3e50;
            text-align: center;
            margin-bottom: 10px;
        }
        .intro-text {
            text-align: center;
            font-size: 0.95rem;
            color: #666;
            margin-bottom: 25px;
        }
        .form-table {
            width: 100%;
            border-collapse: collapse;
        }
        .form-table th,
        .form-table td {
            padding: 12px;
            border-bottom: 1px solid #eee;
            text-align: left;
        }
        .form-table th {
            width: 45%;
            background-color: #f8f9fb;
            font-weight: 600;
        }
        .form-table input[type="date"] {
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
            <h1>カード販売仕入れ確認</h1>
            <p class="intro-text">対象期間を指定して、カード販売・仕入れ・人件費の集計結果を確認できます。</p>

            <table class="form-table">
                <tbody>
                    <tr>
                        <th><label for="c_month">確認開始日付</label></th>
                        <td><input type="date" id="c_month" name="c_month" value="<?= htmlspecialchars($post['c_month'] ?? '') ?>"></td>
                    </tr>
                    <tr>
                        <th><label for="ec_month">確認終了日付</label></th>
                        <td><input type="date" id="ec_month" name="ec_month" value="<?= htmlspecialchars($post['ec_month'] ?? '') ?>"></td>
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
