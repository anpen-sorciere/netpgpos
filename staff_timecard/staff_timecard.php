<?php
// エラー表示を有効化
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// スタッフ用タイムカード画面（QR読み取り・出退勤登録）
require_once(__DIR__ . '/../../common/dbconnect.php');
session_start();
// 今後はIDはcast_idとして扱う
$cast_id = $_SESSION['cast_id'] ?? ($_GET['cast_id'] ?? null);
// ID未指定でも画面表示可能に（打刻時にIDを送信する設計に変更予定）
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0,minimum-scale=1.0">
    <title>スタッフタイムカード</title>
    <link href="https://unpkg.com/sanitize.css" rel="stylesheet"/>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .container { max-width: 400px; margin: 40px auto; background: #fff; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); padding: 30px; }
        .qr-area { text-align: center; margin-bottom: 20px; }
        .status { margin: 20px 0; font-weight: bold; color: #3498db; }
    </style>
</head>
<body>
    <div class="container">
        <h1>タイムカード打刻</h1>
        <div class="qr-area">
            <p>店内タブレットのQRコードを読み込んでください。</p>
            <input type="text" id="qrInput" placeholder="QRコード内容を貼り付け" style="width:80%;padding:8px;">
            <button class="btn btn-primary" onclick="submitQR()">打刻</button>
        </div>
        <div id="status" class="status"></div>
        <a href="../index.php" class="btn btn-secondary">メニューへ</a>
    </div>
    <?php
    // utypeをURLパラメータで引き回す
    $utype = $_GET['utype'] ?? ($_SESSION['utype'] ?? '');
    $utype_param = $utype ? '?utype=' . urlencode($utype) : '';
    ?>
    <div style="text-align:center;margin-top:20px;">
        <a href="../index.php<?= $utype_param ?>" class="btn btn-secondary">メニューへ戻る</a>
    </div>
    <script>
    function submitQR() {
        const qr = document.getElementById('qrInput').value;
        if (!qr) { document.getElementById('status').textContent = 'QRコード内容を入力してください。'; return; }
        fetch('qr_generate.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ staff_id: <?=json_encode($staff_id)?>, qr: qr })
        })
        .then(res => res.json())
        .then(data => {
            document.getElementById('status').textContent = data.message;
        })
        .catch(() => { document.getElementById('status').textContent = '通信エラー'; });
    }
    </script>
</body>
</html>
