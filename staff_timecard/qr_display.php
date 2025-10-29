<?php
// 店内タブレット用：1秒ごとに更新されるQRコード表示画面
require_once(__DIR__ . '/../../common/config.php');
require_once(__DIR__ . '/../../common/dbconnect.php');
require_once(__DIR__ . '/../../common/functions.php');
// 店舗ID（utype）をURLパラメータから取得
$utype = $_GET['utype'] ?? '';
// actionは出勤/退勤どちらか（例：in/out）
$action = $_GET['action'] ?? 'in';
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0,minimum-scale=1.0">
    <title>スタッフ用QRタイムカード</title>
    <link href="https://unpkg.com/sanitize.css" rel="stylesheet"/>
    <link rel="stylesheet" href="../css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.1/build/qrcode.min.js"></script>
    <style>
        body { background:#fff; }
        .container { max-width: 400px; margin: 40px auto; background: #fff; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); padding: 30px; text-align:center; }
        .qr-area { margin: 30px 0; }
        .info { font-size:1.1em; margin-bottom:10px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>スタッフ用QRタイムカード</h1>
        <div class="info">店舗ID: <?= htmlspecialchars($utype) ?> / 打刻種別: <?= htmlspecialchars($action) ?></div>
        <div id="qr" class="qr-area"></div>
        <div id="qrText" style="word-break:break-all;font-size:0.9em;color:#555;"></div>
        <div style="margin-top:20px;color:#888;">QRコードをスマホで読み込んでください</div>
    </div>
    <script>
    function updateQR() {
        const now = new Date();
        const y = now.getFullYear();
        const m = ('0'+(now.getMonth()+1)).slice(-2);
        const d = ('0'+now.getDate()).slice(-2);
        const h = ('0'+now.getHours()).slice(-2);
        const i = ('0'+now.getMinutes()).slice(-2);
        const s = ('0'+now.getSeconds()).slice(-2);
        // QR内容例: action, utype, timestamp
        const params = {
            action: "<?= htmlspecialchars($action) ?>",
            utype: "<?= htmlspecialchars($utype) ?>",
            timestamp: `${y}${m}${d}${h}${i}${s}`
        };
        const qrText = JSON.stringify(params);
        document.getElementById('qrText').textContent = qrText;
        document.getElementById('qr').innerHTML = '';
        QRCode.toCanvas(document.getElementById('qr'), qrText, { width: 250 }, function (error) {
            if (error) document.getElementById('qrText').textContent = 'QR生成エラー';
        });
    }
    updateQR();
    setInterval(updateQR, 1000);
    </script>
</body>
</html>
