<?php
// エラーレポートを有効にし、すべてのエラーを表示
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require("../common/dbconnect.php");
require("../common/functions.php");
session_start();

// セッションから必要な情報を取得
$cast_id = $_SESSION['join']['cast_id'] ?? null;
$in_ymd = $_SESSION['join']['in_ymd'] ?? null;
$pay = $_SESSION['join']['pay'] ?? null;
$utype = $_SESSION['utype'] ?? null;

// キャスト名を取得するための処理
$cast_name = "不明なキャスト";
if ($cast_id) {
    $db = connect();
    $statement = $db->prepare("SELECT cast_name FROM cast_mst WHERE cast_id = ?");
    $statement->execute(array($cast_id));
    $cast_data = $statement->fetch(PDO::FETCH_ASSOC);
    if ($cast_data) {
        $cast_name = $cast_data['cast_name'];
    }
}

// 登録年月のフォーマットを整形
$display_ymd = "";
if ($in_ymd) {
    $date = new DateTime($in_ymd . '-01'); // 日付として扱うために'-01'を追加
    $display_ymd = $date->format('Y年m月');
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0,minimum-scale=1.0">
    <title>時給確認画面</title>
    <link href="https://use.fontawesome.com/releases/v5.6.1/css/all.css" rel="stylesheet">
    <link href="https://unpkg.com/sanitize.css" rel="stylesheet"/>
    <link rel="stylesheet" href="style.css">
    <style>
        .control a {
            padding: 12px 25px;
            text-decoration: none;
            color: #fff;
            background-color: #3498db;
            border-radius: 30px;
            font-size: 1em;
            transition: transform 0.2s, box-shadow 0.2s;
            box-shadow: 0 4px 15px rgba(52, 152, 219, 0.4);
            display: inline-block;
            margin-top: 10px;
        }
        .control a:hover {
            background-color: #2980b9;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(52, 152, 219, 0.6);
        }
        .container {
            width: 100%;
            max-width: 600px;
            padding: 30px;
            background-color: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            text-align: center;
        }
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f0f4f8;
            color: #333;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        h1 {
            color: #3498db;
        }
        .result-box {
            background-color: #f9f9f9;
            padding: 20px;
            border-radius: 8px;
            border: 1px solid #ddd;
            margin-top: 20px;
        }
        .result-item {
            font-size: 1.2em;
            margin-bottom: 10px;
        }
        .result-label {
            font-weight: bold;
            color: #555;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>時給登録完了</h1>
        <div class="result-box">
            <p class="result-item">
                <span class="result-label">キャスト名:</span>
                <?= htmlspecialchars($cast_name) ?>
            </p>
            <p class="result-item">
                <span class="result-label">登録年月:</span>
                <?= htmlspecialchars($display_ymd) ?>
            </p>
            <p class="result-item">
                <span class="result-label">時給:</span>
                <?= htmlspecialchars(number_format($pay)) ?>円
            </p>
        </div>
        <div class="control">
            <a href="paytbl_input.php">戻る</a>
            <a href="index.php">メニューへ</a>
        </div>
    </div>
</body>
</html>
