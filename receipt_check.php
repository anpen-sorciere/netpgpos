<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require("../common/dbconnect.php");
require("../common/functions.php");
session_cache_limiter('none');
session_start();

// セッションデータがない場合は入力画面に戻す
if (!isset($_SESSION['join'])) {
    header('Location: receipt_input.php');
    exit();
}

$pdo = connect();
$uid = $_SESSION['user_id'] ?? null;
$utype = $_SESSION['utype'] ?? 0;

if (!empty($_POST['check'])) {
    $now = new DateTime();
    $receipt_id = intval($now->format('ymdHis'));

    // 伝票基本データ登録
    $shop_mst = $_SESSION['join']['shop_mst'];
    if ($utype == 3) {
        $shop_mst = $utype;
    }

    $receipt_day = str_replace('-', '', $_SESSION['join']['receipt_day'] ?? '');
    $in_date = str_replace('-', '', $_SESSION['join']['in_date'] ?? '');
    $in_time = str_replace(':', '', $_SESSION['join']['in_time'] ?? '');
    $customer_name = $_SESSION['join']['customer_name'] ?? '';
    $issuer_id = $_SESSION['join']['issuer_id'] ?? null;
    $p_type = $_SESSION['join']['p_type'] ?? null;
    $adjust_price = $_SESSION['join']['adjust_price'] ?? 0;
    
    // out_dateとout_timeには空文字列を挿入
    $out_date = '';
    $out_time = '';

    $stmt_base = $pdo->prepare("INSERT INTO receipt_tbl (receipt_id, shop_id, sheet_no, receipt_day, in_date, in_time, out_date, out_time, customer_name, issuer_id, rep_id, payment_type, adjust_price) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt_base->execute([
        $receipt_id,
        $shop_mst,
        $_SESSION['join']['sheet_no'] ?? null,
        $receipt_day,
        $in_date,
        $in_time,
        $out_date,
        $out_time,
        $customer_name,
        $issuer_id,
        0, // rep_idは現状使っていないので0
        $p_type,
        $adjust_price
    ]);

    // 伝票明細登録 (11行登録)
    $stmt_detail = $pdo->prepare("INSERT INTO receipt_detail_tbl (shop_id, receipt_id, receipt_day, item_id, quantity, price, cast_id, cast_back_price) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    for ($i = 1; $i <= 11; $i++) {
        // 全ての行に対して処理を実行
        $item_id = intval($_SESSION['join']["item_name$i"] ?? 0);
        $quantity = intval($_SESSION['join']["suu$i"] ?? 0);
        $price = intval($_SESSION['join']["price$i"] ?? 0);
        $cast_id = $_SESSION['join']["cast_name$i"] ?? null;
        
        $cast_back_price = 0;
        if ($item_id > 0) {
            $item_data = item_get($pdo, $item_id);
            $cast_back_price = ($item_data['back_price'] ?? 0) * $quantity;
        }

        $stmt_detail->execute([
            $shop_mst,
            $receipt_id,
            $receipt_day,
            $item_id,
            $quantity,
            $price,
            $cast_id,
            $cast_back_price
        ]);
    }

    $initday = $_SESSION['join']['receipt_day'] ?? date('Y-m-d');
    $_SESSION['initday'] = $initday;
    $url = "receipt_regist_finish.php?initday=" . urlencode($initday);

    unset($_SESSION['join']); // セッションを破棄
    header("Location: " . $url);
    exit();
}

// 表示用データの準備
$shop_mst = $_SESSION['join']['shop_mst'] ?? null;
$sheet_no = $_SESSION['join']['sheet_no'] ?? '';
$receipt_day = $_SESSION['join']['receipt_day'] ?? '';
$in_date = $_SESSION['join']['in_date'] ?? '';
$in_time = $_SESSION['join']['in_time'] ?? '';
$customer_name = $_SESSION['join']['customer_name'] ?? '';
$issuer_id = $_SESSION['join']['issuer_id'] ?? null;
$p_type = $_SESSION['join']['p_type'] ?? null;
$adjust_price = intval($_SESSION['join']['adjust_price'] ?? 0); 

$p_data = payment_data_get($pdo, $p_type);
$payment_name = $p_data['payment_name'] ?? '未指定';
$issuer_data = cast_get($pdo, $issuer_id);
$issuer_name = $issuer_data['cast_name'] ?? '未指定';

$total_subtotal = 0;
$items_to_display = [];
for ($i = 1; $i <= 11; $i++) {
    $item_id = intval($_SESSION['join']["item_name$i"] ?? 0);
    $quantity = intval($_SESSION['join']["suu$i"] ?? 0);
    $price = intval($_SESSION['join']["price$i"] ?? 0);
    $cast_id = $_SESSION['join']["cast_name$i"] ?? null;

    $item_name = '';
    if ($item_id > 0) {
        $item_data = item_get($pdo, $item_id);
        $item_name = $item_data['item_name'] ?? '不明な商品';
    }
    
    $cast_name = '';
    if ($cast_id > 0) {
        $cast_data = cast_get($pdo, $cast_id);
        $cast_name = $cast_data['cast_name'] ?? '未指定';
    }
    
    $subtotal = $price * $quantity;
    $total_subtotal += $subtotal;

    $items_to_display[] = [
        'item_id' => $item_id,
        'item_name' => $item_name,
        'cast_id' => $cast_id,
        'cast_name' => $cast_name,
        'price' => $price,
        'quantity' => $quantity,
        'subtotal' => $subtotal,
    ];
}
$tax_rate = 0.10;
$total_with_tax = $total_subtotal * (1 + $tax_rate);
$final_total = $total_with_tax + $adjust_price;
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0,minimum-scale=1.0">
    <title>伝票登録確認画面</title>
    <link href="https://use.fontawesome.com/releases/v5.6.1/css/all.css" rel="stylesheet">
    <link href="https://unpkg.com/sanitize.css" rel="stylesheet"/>
    <link rel="stylesheet" href="css/style.css">
    <style>
        table {
            border-collapse: collapse;
            width: 100%;
            margin-top: 20px;
        }
        th, td {
            border: 1px solid #ccc;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
        }
        .right-align {
            text-align: right;
        }
        .check-info {
            font-weight: bold;
        }
        .total-row td {
            font-weight: bold;
        }
        /* ボタンを横並びにするためのスタイルを追加 */
        .btn-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .btn-container form {
            margin: 0;
            padding: 0;
        }
    </style>
</head>
<body>
    <div class="content">
        <h1>入力情報の確認</h1>
        <p>ご入力情報に変更が必要な場合、下のボタンを押し、変更を行ってください。</p>
        <p>登録情報はあとから変更することもできます。</p>
        <hr>

        <h2>伝票基本情報</h2>
        <table>
            <tbody>
                <tr>
                    <th>店舗コード</th>
                    <td><span class="check-info"><?= ($utype == 3) ? 'コレクト' : htmlspecialchars($shop_mst ?? '', ENT_QUOTES); ?></span></td>
                    <th>座席番号</th>
                    <td><span class="check-info"><?= htmlspecialchars($sheet_no ?? '', ENT_QUOTES); ?></span></td>
                </tr>
                <tr>
                    <th>伝票集計日付</th>
                    <td><span class="check-info"><?= htmlspecialchars($receipt_day ?? '', ENT_QUOTES); ?></span></td>
                    <td></td>
                    <td></td>
                </tr>
                <tr>
                    <th>入店日付</th>
                    <td><span class="check-info"><?= htmlspecialchars($in_date ?? '', ENT_QUOTES); ?></span></td>
                    <th>入店時間</th>
                    <td><span class="check-info"><?= htmlspecialchars($in_time ?? '', ENT_QUOTES); ?></span></td>
                </tr>
                <tr>
                    <th>顧客名</th>
                    <td><span class="check-info"><?= htmlspecialchars($customer_name ?? '', ENT_QUOTES); ?></span></td>
                    <th>伝票起票者</th>
                    <td><span class="check-info"><?= htmlspecialchars($issuer_name ?? '', ENT_QUOTES); ?></span></td>
                </tr>
                <tr>
                    <th>支払い方法</th>
                    <td><span class="check-info"><?= htmlspecialchars($payment_name ?? '', ENT_QUOTES); ?></span></td>
                    <th>調整額</th>
                    <td><span class="check-info"><?= number_format($adjust_price); ?></span></td>
                </tr>
            </tbody>
        </table>
        
        <h2>伝票明細</h2>
        <table>
            <thead>
                <tr>
                    <th>商品コード</th>
                    <th>商品名</th>
                    <th>キャストID</th>
                    <th>キャスト名</th>
                    <th class="right-align">単価</th>
                    <th class="right-align">数量</th>
                    <th class="right-align">小計</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($items_to_display as $item): ?>
                <tr>
                    <td><?= htmlspecialchars($item['item_id'], ENT_QUOTES); ?></td>
                    <td><?= htmlspecialchars($item['item_name'], ENT_QUOTES); ?></td>
                    <td><?= htmlspecialchars($item['cast_id'], ENT_QUOTES); ?></td>
                    <td><?= htmlspecialchars($item['cast_name'], ENT_QUOTES); ?></td>
                    <td class="right-align"><?= number_format($item['price']); ?></td>
                    <td class="right-align"><?= number_format($item['quantity']); ?></td>
                    <td class="right-align"><?= number_format($item['subtotal']); ?></td>
                </tr>
                <?php endforeach; ?>
                <tr class="total-row">
                    <td colspan="6" style="text-align: right;">小計合計</td>
                    <td class="right-align"><?= number_format($total_subtotal); ?></td>
                </tr>
                <tr class="total-row">
                    <td colspan="6" style="text-align: right;">税抜合計</td>
                    <td class="right-align"><?= number_format($total_subtotal); ?></td>
                </tr>
                <tr class="total-row">
                    <td colspan="6" style="text-align: right;">消費税(10%)</td>
                    <td class="right-align"><?= number_format($total_subtotal * $tax_rate); ?></td>
                </tr>
                <tr class="total-row">
                    <td colspan="6" style="text-align: right;">税込み合計</td>
                    <td class="right-align"><?= number_format($total_with_tax); ?></td>
                </tr>
                <tr class="total-row">
                    <td colspan="6" style="text-align: right;">調整額</td>
                    <td class="right-align"><?= number_format($adjust_price); ?></td>
                </tr>
                <tr class="total-row">
                    <td colspan="6" style="text-align: right;">最終合計</td>
                    <td class="right-align"><?= number_format($final_total); ?></td>
                </tr>
            </tbody>
        </table>
        
        <br>
        <div class="btn-container">
            <a href="receipt_input.php?utype=<?= htmlspecialchars($utype ?? '', ENT_QUOTES); ?>&is_back=1" class="btn back-btn">変更する</a>
            <form action="" method="POST">
                <input type="hidden" name="check" value="checked">
                <button type="submit" class="btn next-btn">登録する</button>
            </form>
        </div>
        <div class="clear"></div>
    </div>
</body>
</html>
<?php
// 処理の最後にデータベース接続を閉じる
disconnect($pdo);
?>
