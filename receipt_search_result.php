<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

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

$shop_info = get_shop_info($utype);
$shop_name = $shop_info['name'] ?? '不明な店舗';
$shop_mst = $shop_info['id'] ?? null;

$error = [];
$pdo = null;
$invoices = [];
$cast_list = [];
$total_adjusted_amount = 0;

try {
    $pdo = connect();
    if (!$pdo) {
        throw new Exception("データベース接続に失敗しました");
    }
    $casts = cast_get_all($pdo);

    foreach ($casts as $cast) {
        $cast_list[$cast['cast_id']] = $cast['cast_name'];
    }

    // Parameters
    $start_date = $_POST['c_day'] ?? date('Y-m-d');
    $end_date = $_POST['ec_day'] ?? date('Y-m-d');
    $p_receipt_id = $_POST['receipt_id'] ?? '';
    $p_customer_name = $_POST['customer_name'] ?? '';

    $start_ymd = str_replace('-', '', $start_date);
    $end_ymd = str_replace('-', '', $end_date);
    
    // Payment MST
    $sql_payment = "SELECT * FROM payment_mst ORDER BY payment_type";
    $statement_payment = $pdo->prepare($sql_payment);
    $statement_payment->execute();
    $payment_mst_data = $statement_payment->fetchAll(PDO::FETCH_ASSOC);
    $payment_map = array_column($payment_mst_data, 'payment_name', 'payment_type');

    if ($shop_mst !== null) {
        // Build Query
        $sql_receipts = "SELECT * FROM receipt_tbl WHERE shop_id = ? ";
        $params_receipts = [intval($shop_mst)];
        
        // Date Logic: default to range if no specific ID search or if date is provided
        // If receipt_id is provided, maybe ignore date? Usually user expects date range AND ID filter, or ID overrides Date. 
        // User said: "伝票日付(範囲指定か日付指定) ... 伝票番号 ... 顧客名"
        // Let's apply ALL filters effectively.
        
        $sql_receipts .= "AND receipt_day BETWEEN ? AND ? ";
        $params_receipts[] = $start_ymd;
        $params_receipts[] = $end_ymd;

        if (!empty($p_receipt_id)) {
            $sql_receipts .= "AND receipt_id = ? ";
            $params_receipts[] = intval($p_receipt_id);
        }

        if (!empty($p_customer_name)) {
            $sql_receipts .= "AND customer_name LIKE ? ";
            $params_receipts[] = '%' . $p_customer_name . '%';
        }
    
        $sql_receipts .= " ORDER BY receipt_day DESC, receipt_id DESC";
    
        $statement_receipts = $pdo->prepare($sql_receipts);
        $statement_receipts->execute($params_receipts);
        $receipts = $statement_receipts->fetchAll(PDO::FETCH_ASSOC);

        foreach ($receipts as $receipt) {
            $receipt_id = $receipt['receipt_id'];

            $sql_details = "SELECT * FROM receipt_detail_tbl WHERE receipt_id = ? ORDER BY receipt_detail_id ASC";
            $statement_details = $pdo->prepare($sql_details);
            $statement_details->execute([$receipt_id]);
            $details = $statement_details->fetchAll(PDO::FETCH_ASSOC);

            $subtotal_without_tax = 0;
            foreach ($details as $detail) {
                $subtotal_without_tax += $detail['price'] * $detail['quantity'];
            }

            $tax_rate = get_tax_rate();
            $tax_amount = floor($subtotal_without_tax * $tax_rate);
            $subtotal_with_tax = $subtotal_without_tax + $tax_amount;
            $adjusted_amount = $subtotal_with_tax + $receipt['adjust_price'];
    
            $invoices[] = [
                'receipt_id' => $receipt_id,
                'receipt_day' => $receipt['receipt_day'],
                'issuer_id' => $receipt['issuer_id'],
                'staff_id' => $receipt['staff_id'] ?? 0,
                'customer_name' => $receipt['customer_name'] ?? '不明',
                'payment_type' => $receipt['payment_type'],
                'adjust_price' => $receipt['adjust_price'],
                'subtotal_without_tax' => $subtotal_without_tax,
                'subtotal_with_tax' => $subtotal_with_tax,
                'adjusted_amount' => $adjusted_amount,
            ];
            $total_adjusted_amount += $adjusted_amount;
        }
    } else {
        $error['logic'] = "店舗情報が取得できませんでした。";
    }

} catch (PDOException $e) {
    $error['db'] = "データベースエラー: " . $e->getMessage();
} catch (Exception $e) {
    $error['general'] = "エラー: " . $e->getMessage();
} finally {
    disconnect($pdo);
}

function format_price($price) {
    return number_format($price) . '円';
}
function format_ymd_disp($ymd) {
    if(strlen($ymd) == 8) {
        return substr($ymd,0,4).'/'.substr($ymd,4,2).'/'.substr($ymd,6,2);
    }
    return $ymd;
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0,minimum-scale=1.0">
    <title>過去伝票検索結果</title>
    <link href="https://unpkg.com/sanitize.css" rel="stylesheet"/>
    <link rel="stylesheet" href="css/style.css">
    <style>
        body { background-color: #f0f4f8; color: #333; }
        .content {
            max-width: 1200px; margin: 0 auto; padding: 2rem;
            background-color: #fff; border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        h1 { color: #007bff; border-bottom: 2px solid #007bff; padding-bottom: 0.5rem; margin-bottom: 1.5rem; text-align: center; }
        .error-message { background-color: #f8d7da; color: #721c24; padding: 1rem; border-radius: 8px; text-align: center; margin-bottom: 10px;}
        .total-summary {
            margin-top: 1rem; margin-bottom: 2rem; text-align: right;
            font-size: 1.5em; font-weight: bold; padding: 1rem;
            border: 2px solid #007bff; border-radius: 8px; background-color: #eaf5ff;
        }
        table { width: 100%; border-collapse: collapse; font-size: 0.9em; table-layout: fixed; background-color: #fff; }
        th, td { border: 1px solid #dee2e6; padding: 12px; text-align: center; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        th { background-color: #007bff; color: white; font-weight: bold; position: sticky; top: 0; z-index: 10; }
        tbody tr:nth-child(odd) { background-color: #f2f2f2; }
        tbody tr:hover { background-color: #e9ecef; }
        .detail-btn { padding: 6px 12px; background-color: #6c757d; color: white; border-radius: 5px; text-decoration: none; }
        .detail-btn:hover { background-color: #5a6268; }
        .back-link { display: block; margin-top: 2rem; text-align: center; font-size: 1.2rem; color: #007bff; text-decoration: none; font-weight: bold; }
        .back-link:hover { text-decoration: underline; }
        .search-cond {
            background: #eee; padding: 10px; border-radius: 5px; margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="content">
        <h1>過去伝票検索結果 (<?= htmlspecialchars($shop_name) ?>)</h1>
        
        <div class="search-cond">
            <strong>検索条件:</strong><br>
            期間: <?= htmlspecialchars($start_date) ?> ～ <?= htmlspecialchars($end_date) ?><br>
            <?php if(!empty($p_receipt_id)) echo "伝票番号: " . htmlspecialchars($p_receipt_id) . "<br>"; ?>
            <?php if(!empty($p_customer_name)) echo "顧客名: " . htmlspecialchars($p_customer_name) . "<br>"; ?>
        </div>

        <?php if (!empty($error)): ?>
            <div class="error-message">
                <?php foreach ($error as $key => $msg): ?>
                    <p><strong><?= htmlspecialchars($key) ?>:</strong> <?= htmlspecialchars($msg) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

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
                        <th>担当者</th>
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
                            $staff_name = ($invoice['staff_id'] ?? 0) != 0 ? ($cast_list[$invoice['staff_id']] ?? '') : '';
                            $payment_name = $payment_map[$invoice['payment_type']] ?? '不明';
                        ?>
                        <tr>
                            <td><?= htmlspecialchars($invoice['receipt_id']) ?></td>
                            <td><?= htmlspecialchars($invoice['customer_name']) ?></td>
                            <td><?= htmlspecialchars($staff_name) ?></td>
                            <td><?= htmlspecialchars($issuer_name) ?></td>
                            <td><?= htmlspecialchars(format_ymd_disp($invoice['receipt_day'])) ?></td>
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

        <a href="receipt_search.php?utype=<?= htmlspecialchars($utype) ?>" class="back-link">再検索</a>
        <a href="index.php?utype=<?= htmlspecialchars($utype) ?>" class="back-link" style="margin-top:10px; font-size:1rem;">メニューに戻る</a>
    </div>
</body>
</html>
