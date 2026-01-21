<?php
// エラー表示を有効化
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
// ログイン処理
session_start();

$uid = $_SESSION['user_id'] ?? null;
$utype = 0;
if(isset($_GET['utype'])) {
    $utype = $_GET['utype'];
    $_SESSION['utype'] = $utype;
} elseif(isset($_SESSION['utype'])) {
    $utype = $_SESSION['utype'];
} else {
    // utypeが設定されていない場合にエラーを表示
    echo "ユーザータイプ情報が無効です。";
    exit();
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NetPG売上管理システム</title>
    <link href="https://use.fontawesome.com/releases/v5.6.1/css/all.css" rel="stylesheet">
    <link href="https://unpkg.com/sanitize.css" rel="stylesheet"/>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <?php
        $shop_name = '';
        if ($utype == 1024) {
            $shop_name = 'ソルシエール';
        } elseif ($utype == 2) {
            $shop_name = 'レーヴェス';
        } elseif ($utype == 3) {            
            $shop_name = 'コレクト';
        } elseif ($utype == 99) {            
            $shop_name = 'テスト店舗';
        } else {
            exit();
        }
        ?>
        <h1><?= htmlspecialchars($shop_name, ENT_QUOTES) ?>管理システム</h1>
        <ul class="menu-list">
            <li class="menu-item"><a href="receipt_input.php?utype=<?= htmlspecialchars($utype) ?>" class="menu-button">伝票入力</a></li>
            <li class="menu-item"><a href="receipt_input_tablet.php?utype=<?= htmlspecialchars($utype) ?>" class="menu-button" style="background:linear-gradient(135deg, #1e90ff, #00bfff); color:white;">⚡ スマート伝票入力 (ホール用)</a></li>
            <li class="menu-item"><a href="timecard_input.php?utype=<?= htmlspecialchars($utype) ?>" class="menu-button">タイムカード入力</a></li>
            <li class="menu-item"><a href="summary.php?utype=<?= htmlspecialchars($utype) ?>" class="menu-button">集計確認</a></li>
            <li class="menu-item"><a href="daily_wage_summary.php?utype=<?= htmlspecialchars($utype) ?>" class="menu-button">日当確認</a></li>
            <li class="menu-item"><a href="staff_sales_summary.php?utype=<?= htmlspecialchars($utype) ?>" class="menu-button">担当売り上げ確認</a></li>
            <li class="menu-item"><a href="monthly_wage_summary.php?utype=<?= htmlspecialchars($utype) ?>" class="menu-button">キャスト月データ確認</a></li>
            <li class="menu-item"><a href="timecard_list.php?utype=<?= htmlspecialchars($utype) ?>" class="menu-button">月別出勤簿一覧</a></li>
            <?php if ($utype == 1024) : ?>
                <li class="menu-item"><a href="online_support.php?utype=1024" class="menu-button">遠隔確認</a></li>
                <li class="menu-item"><a href="online_support_input.php?utype=1024" class="menu-button">遠隔入力</a></li>
            <?php elseif ($utype == 3) : ?>
                <li class="menu-item"><a href="card_sales.php?utype=3" class="menu-button">カード販売仕入れ・全人件費入力</a></li>
                <li class="menu-item"><a href="card_sales_summary.php?utype=3" class="menu-button">カード販売仕入れ・全人件費確認</a></li>
            <?php endif; ?>
            <li class="menu-item"><a href="item_mst.php?utype=<?= htmlspecialchars($utype) ?>" class="menu-button">商品マスタ登録</a></li>
            <li class="menu-item"><a href="cast_regist.php?utype=<?= htmlspecialchars($utype) ?>" class="menu-button">キャスト登録</a></li>
            <li class="menu-item"><a href="paytbl_input.php?utype=<?= htmlspecialchars($utype) ?>" class="menu-button">時給登録確認</a></li>
            <li class="menu-item"><a href="hourly_rate_mst_manage.php" class="menu-button">時給マスタ登録修正</a></li>
            <li class="menu-item"><a href="base_data_sync_top.php?utype=<?= htmlspecialchars($utype) ?>" class="menu-button">BASEデータ同期</a></li>
            <li class="menu-item"><a href="order_system/order_terminal.php?utype=<?= htmlspecialchars($utype) ?>" class="menu-button">店内オーダー（テスト）</a></li>
            <li class="menu-item"><a href="order_system/kitchen_terminal.php?utype=<?= htmlspecialchars($utype) ?>" class="menu-button">キッチン受注（テスト）</a></li>
            <li class="menu-item"><a href="superchat.php?utype=<?= htmlspecialchars($utype) ?>" class="menu-button">スパチャ</a></li>
            <li class="menu-item"><a href="staff_timecard/staff_timecard.php?utype=<?= htmlspecialchars($utype) ?>" class="menu-button">スタッフタイムカード入力</a></li>
            
            <!-- 新機能 -->
            <li class="menu-item"><a href="api/admin_tools.php" class="menu-button" style="background-color: #6f42c1; color: white;">BASE関連ツール・管理メニュー</a></li>
        </ul>
    </div>
</body>
</html>
