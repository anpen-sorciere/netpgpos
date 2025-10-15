<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once('./dbconnect.php');
require_once('./functions.php');
session_start();

$utype = 0;
// utypeはGETパラメータから取得
if (isset($_GET['utype'])) {
    $utype = $_GET['utype'];
    $_SESSION['utype'] = $utype;
} elseif (isset($_SESSION['utype'])) {
    $utype = $_SESSION['utype'];
}

$shop_info = get_shop_info($utype);
$shop_name = $shop_info['name'] ?? '不明な店舗';
$shop_id = $shop_info['id'] ?? null;

$error = [];
$pdo = null;
$invoices = [];
$cast_list = [];
$total_adjusted_amount = 0; // 合計金額の合計を格納する変数

try {
    $pdo = connect();
    $casts = cast_get_all($pdo);

    // cast_idをキーとした連想配列を作成
    foreach ($casts as $cast) {
        $cast_list[$cast['cast_id']] = $cast['cast_name'];
    }

    // 検索条件をPOSTから取得
    // データがない場合は現在の日付を使用
    $start_date = $_POST['c_day'] ?? date('Y-m-d');
    $end_date = $_POST['ec_day'] ?? date('Y-m-d');
    $payment_type = $_POST['p_type'] ?? '0'; // '0'は「全部」を意味する

    // 日付をY-m-dからYYYYMMDD形式に変換してデータベース検索に使用
    $start_ymd = str_replace('-', '', $start_date);
    $end_ymd = str_replace('-', '', $end_date);
    
    // 支払い方法マスターデータを取得
    $sql_payment = "SELECT * FROM payment_mst ORDER BY payment_type";
    $statement_payment = $pdo->prepare($sql_payment);
    $statement_payment->execute();
    $payment_mst_data = $statement_payment->fetchAll(PDO::FETCH_ASSOC);
    $payment_map = array_column($payment_mst_data, 'payment_name', 'payment_type');

    // 伝票の基本情報を取得するSQLクエリ
    // $shop_idがnullでないことを確認してからクエリを実行
    if ($shop_id !== null) {
        $sql_receipts = "SELECT * FROM receipt_tbl WHERE shop_id = ? AND receipt_day BETWEEN ? AND ? ";
        $params_receipts = [intval($shop_id), $start_ymd, $end_ymd];
    
        if ($payment_type != '0') {
            $sql_receipts .= "AND payment_type = ? ";
            $params_receipts[] = intval($payment_type);
        }
    
        $sql_receipts .= " ORDER BY receipt_day DESC, receipt_id DESC";
    
        $statement_receipts = $pdo->prepare($sql_receipts);
        $statement_receipts->execute($params_receipts);
        $receipts = $statement_receipts->fetchAll(PDO::FETCH_ASSOC);

        // 取得した伝票ごとに明細を取得し、計算を行う
        foreach ($receipts as $receipt) {
            $receipt_id = $receipt['receipt_id'];

            // 明細情報を取得
            $sql_details = "SELECT * FROM receipt_detail_tbl WHERE receipt_id = ? ORDER BY receipt_detail_id ASC";
            $statement_details = $pdo->prepare($sql_details);
            $statement_details->execute([$receipt_id]);
            $details = $statement_details->fetchAll(PDO::FETCH_ASSOC);

            // 明細データから小計を計算
            $subtotal_without_tax = 0;
            foreach ($details as $detail) {
                $subtotal_without_tax += $detail['price'] * $detail['quantity'];
            }

            // 税金と調整額を適用した最終金額を計算
            $tax_rate = get_tax_rate();
            $tax_amount = floor($subtotal_without_tax * $tax_rate);
            $subtotal_with_tax = $subtotal_without_tax + $tax_amount;
            $adjusted_amount = $subtotal_with_tax + $receipt['adjust_price'];
    
            $invoices[] = [
                'receipt_id' => $receipt_id,
                'receipt_day' => $receipt['receipt_day'],
                'issuer_id' => $receipt['issuer_id'],
                'customer_name' => $receipt['customer_name'] ?? '不明', // 顧客名を追加
                'payment_type' => $receipt['payment_type'],
                'adjust_price' => $receipt['adjust_price'],
                'subtotal_without_tax' => $subtotal_without_tax,
                'subtotal_with_tax' => $subtotal_with_tax,
                'adjusted_amount' => $adjusted_amount,
            ];
    
            $total_adjusted_amount += $adjusted_amount; // 合計金額を加算
        }
    } else {
        $error['logic'] = "店舗情報が取得できませんでした。管理者にご連絡ください。";
    }

} catch (PDOException $e) {
    $error['db'] = "データベースエラーが発生しました。時間をおいて再度お試しいただくか、管理者にご連絡ください。";
    error_log("Database Error: " . $e->getMessage());
} finally {
    disconnect($pdo);
}

// 金額をカンマ区切りでフォーマットするヘルパー関数
function format_price($price) {
    return number_format($price) . '円';
}

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0,minimum-scale=1.0">
    <title>伝票一覧</title>
    <link href="https://unpkg.com/sanitize.css" rel="stylesheet"/>
    <link rel="stylesheet" href="css/style.css">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", sans-serif;
            background-color: #f0f4f8;
            color: #333;
            line-height: 1.6;
        }
        .content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
            background-color: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        h1 {
            color: #007bff;
            border-bottom: 2px solid #007bff;
            padding-bottom: 0.5rem;
            margin-bottom: 1.5rem;
            text-align: center;
        }
        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            padding: 1rem;
            margin-bottom: 1.5rem;
            border: 1px solid #f5c6cb;
            border-radius: 8px;
            text-align: center;
        }
        .date-selector {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            margin-bottom: 1.5rem;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            background-color: #e9ecef;
            border-radius: 8px;
        }
        .date-selector label {
            font-weight: bold;
        }
        .date-selector input, .date-selector select, .date-selector button {
            padding: 0.5rem 1rem;
            border-radius: 6px;
            border: 1px solid #ced4da;
            font-size: 1rem;
        }
        .date-selector button {
            background-color: #007bff;
            color: white;
            border: none;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }
        .date-selector button:hover {
            background-color: #0056b3;
        }
        .total-summary {
            margin-top: 1rem;
            margin-bottom: 2rem;
            text-align: right;
            font-size: 1.5em;
            font-weight: bold;
            padding: 1rem;
            border: 2px solid #007bff;
            border-radius: 8px;
            background-color: #eaf5ff;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9em;
            table-layout: fixed;
            background-color: #fff;
        }
        th, td {
            border: 1px solid #dee2e6;
            padding: 12px;
            text-align: center;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        th {
            background-color: #007bff;
            color: white;
            font-weight: bold;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        tbody tr:nth-child(odd) {
            background-color: #f2f2f2;
        }
        tbody tr:hover {
            background-color: #e9ecef;
        }
        .detail-btn {
            display: inline-block;
            padding: 6px 12px;
            background-color: #6c757d;
            color: white;
            border-radius: 5px;
            text-decoration: none;
            transition: background-color 0.3s ease;
        }
        .detail-btn:hover {
            background-color: #5a6268;
        }
        .back-link {
            display: block;
            margin-top: 2rem;
            text-align: center;
            font-size: 1.2rem;
            color: #007bff;
            text-decoration: none;
            font-weight: bold;
        }
        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="content">
        <h1>伝票一覧 (<?= htmlspecialchars($shop_name) ?>)</h1>
        <?php if (!empty($error)): ?>
            <div class="error-message">
                <?php foreach ($error as $msg): ?>
                    <p><?= htmlspecialchars($msg, ENT_QUOTES) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    
        <div class="date-selector">
            <form action="summary_result.php?utype=<?= htmlspecialchars($utype) ?>" method="POST">
                <label for="c_day">確認開始日付</label>
                <input type="date" name="c_day" id="c_day" value="<?= htmlspecialchars($start_date) ?>">
                <label for="ec_day">確認終了日付</label>
                <input type="date" name="ec_day" id="ec_day" value="<?= htmlspecialchars($end_date) ?>">
                <label for="p_type">支払い方法</label>
                <select name="p_type" id="p_type">
                    <option value="0" <?= ($payment_type == '0') ? 'selected' : ''; ?>>全部</option>
                    <?php foreach($payment_mst_data as $row): ?>
                        <option value="<?= htmlspecialchars($row["payment_type"]) ?>" <?= ($row["payment_type"] == $payment_type) ? 'selected' : ''; ?>>
                            <?= htmlspecialchars($row["payment_name"], ENT_QUOTES); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit">表示</button>
            </form>
        </div>

        <?php if (empty($invoices)): ?>
            <p>該当する伝票データが見つかりませんでした。</p>
        <?php else: ?>
            <div class="total-summary">
                合計金額: <?= format_price($total_adjusted_amount) ?>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>伝票番号</th>
                        <th>顧客名</th>
                        <th>起票者</th>
                        <th>伝票日付</th>
                        <th>小計(税別)</th>
                        <th>小計(税込)</th>
                        <th>調整額</th>
                        <th>合計金額</th>
                        <th>支払い方法</th>
                        <th>詳細</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($invoices as $invoice): ?>
                        <?php
                            $issuer_name = $cast_list[$invoice['issuer_id']] ?? '不明';
                            $payment_name = $payment_map[$invoice['payment_type']] ?? '不明';
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($invoice['receipt_id']) ?></td>
                            <td><?= htmlspecialchars($invoice['customer_name']) ?></td>
                            <td><?= htmlspecialchars($issuer_name) ?></td>
                            <td><?= htmlspecialchars(format_ymd($invoice['receipt_day'])) ?></td>
                            <td><?= format_price($invoice['subtotal_without_tax']) ?></td>
                            <td><?= format_price($invoice['subtotal_with_tax']) ?></td>
                            <td><?= format_price($invoice['adjust_price']) ?></td>
                            <td><?= format_price($invoice['adjusted_amount']) ?></td>
                            <td><?= htmlspecialchars($payment_name) ?></td>
                            <td><a href="receipt_detail.php?receipt_id=<?= urlencode($invoice['receipt_id']) ?>" class="detail-btn">詳細</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <a href="index.php?utype=<?= htmlspecialchars($utype) ?>" class="back-link">メニューに戻る</a>
    </div>
</body>
</html>