<?php
// エラーレポートを有効化
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once(__DIR__ . '/../common/config.php');
require_once(__DIR__ . '/../common/dbconnect.php');
require_once(__DIR__ . '/../common/functions.php');

session_start();

// utype取得
if (isset($_GET['utype'])) {
    $_SESSION['utype'] = $_GET['utype'];
}
if (!isset($_SESSION['utype'])) {
    echo "エラー: ユーザータイプ情報がありません。";
    exit();
}
$utype = $_SESSION['utype'];
$shop_info = get_shop_info($utype);
$shop_id = $shop_info['id'];
$shop_name = $shop_info['name'];

$start_date = date('Y-m-d');
$end_date = date('Y-m-d');
$show_result = false;

$total_sales = 0;
$total_personnel_cost = 0;
$profit = 0;
$cost_ratio = 0;

$calc_days_count = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $start_date = $_POST['c_day'];
    $end_date = $_POST['ec_day'];
    $show_result = true;
    
    // YYYYMMDD形式に変換
    $start_ymd = str_replace('-', '', $start_date);
    $end_ymd = str_replace('-', '', $end_date);

    try {
        $pdo = connect();

        // ---------------------------------------------------------
        // 1. 売上集計 (Total Sales)
        // summary_result.php のロジックに基づく
        // ---------------------------------------------------------
        $sql_receipts = "SELECT * FROM receipt_tbl WHERE shop_id = ? AND receipt_day BETWEEN ? AND ?";
        $stmt_receipts = $pdo->prepare($sql_receipts);
        $stmt_receipts->execute([$shop_id, $start_ymd, $end_ymd]);
        $receipts = $stmt_receipts->fetchAll(PDO::FETCH_ASSOC);

        foreach ($receipts as $receipt) {
            $receipt_id = $receipt['receipt_id'];
            
            // 明細取得
            $sql_details = "SELECT * FROM receipt_detail_tbl WHERE receipt_id = ?";
            $stmt_details = $pdo->prepare($sql_details);
            $stmt_details->execute([$receipt_id]);
            $details = $stmt_details->fetchAll(PDO::FETCH_ASSOC);
            
            $subtotal_without_tax = 0;
            foreach ($details as $detail) {
                $subtotal_without_tax += $detail['price'] * $detail['quantity'];
            }
            
            // 消費税処理 (functions.php get_tax_rate() 使用)
            $tax_rate = get_tax_rate(); 
            $tax_amount = floor($subtotal_without_tax * $tax_rate);
            $subtotal_with_tax = $subtotal_without_tax + $tax_amount;
            $adjusted_amount = $subtotal_with_tax + $receipt['adjust_price'];
            
            $total_sales += $adjusted_amount;
        }

        // ---------------------------------------------------------
        // 2. 人件費集計 (Total Personnel Costs)
        // daily_wage_summary.php のロジックに基づく (期間内ループ)
        // ---------------------------------------------------------
        
        $period = new DatePeriod(
            new DateTime($start_date),
            new DateInterval('P1D'),
            (new DateTime($end_date))->modify('+1 day')
        );

        foreach ($period as $date) {
            $current_ymd_hyphen = $date->format('Y-m-d'); // 2026-01-31
            $current_ymd_no_hyphen = $date->format('Ymd'); // 20260131
            $ym_array = explode('-', $current_ymd_hyphen); // [2026, 01, 31]
            
            $calc_days_count++;

            // その日に出勤またはバックが発生しているキャストIDを取得
            $stmt_cast_ids = $pdo->prepare("
                SELECT cast_id FROM timecard_tbl WHERE shop_id = ? AND eigyo_ymd = ?
                UNION
                SELECT cast_id FROM receipt_detail_tbl WHERE shop_id = ? AND receipt_day = ? AND cast_id > 0
            ");
            $stmt_cast_ids->execute([$shop_id, $current_ymd_no_hyphen, $shop_id, $current_ymd_no_hyphen]);
            $relevant_cast_ids = $stmt_cast_ids->fetchAll(PDO::FETCH_COLUMN, 0);
            $relevant_cast_ids = array_unique($relevant_cast_ids);

            foreach ($relevant_cast_ids as $cast_id) {
                // 時給計算
                $daily_hourly_wage_total = 0;
                
                $stmt_timecard = $pdo->prepare("SELECT * FROM timecard_tbl WHERE cast_id = ? AND shop_id = ? AND eigyo_ymd = ?");
                $stmt_timecard->execute([$cast_id, $shop_id, $current_ymd_no_hyphen]);
                $timecard_data = $stmt_timecard->fetch(PDO::FETCH_ASSOC);

                if ($timecard_data) {
                    $times = calculate_working_hours_minutes($timecard_data);
                    $total_minutes = $times['work_time_minutes'];
                    
                    // その月の時給を取得
                    $pay_data = pay_get($pdo, $cast_id, $ym_array[0], $ym_array[1]);
                    $hourly_wage_rate = (int)($pay_data['pay_amount'] ?? 0);
                    
                    if ($hourly_wage_rate > 0) {
                        $daily_hourly_wage_total = ceil($hourly_wage_rate * ($total_minutes / 60));
                    }
                }

                // バック計算
                $daily_back_total = 0;
                $stmt_back = $pdo->prepare("
                    SELECT SUM(im.back_price * rd.quantity) AS total_back
                    FROM receipt_detail_tbl AS rd
                    JOIN item_mst AS im ON rd.item_id = im.item_id
                    WHERE rd.shop_id = ? AND rd.cast_id = ? AND rd.receipt_day = ? AND im.back_price > 0
                ");
                $stmt_back->execute([$shop_id, $cast_id, $current_ymd_no_hyphen]);
                $back_row = $stmt_back->fetch(PDO::FETCH_ASSOC);
                $daily_back_total = (int)($back_row['total_back'] ?? 0);

                // 人件費加算
                $total_personnel_cost += ($daily_hourly_wage_total + $daily_back_total);
            }
        }
        
        // ---------------------------------------------------------
        // 3. 損益計算
        // ---------------------------------------------------------
        $profit = $total_sales - $total_personnel_cost;
        if ($total_sales > 0) {
            $cost_ratio = ($total_personnel_cost / $total_sales) * 100;
        }

    } catch (Exception $e) {
        $error_msg = "エラーが発生しました: " . $e->getMessage();
    } finally {
        if ($pdo) {
            disconnect($pdo);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0,minimum-scale=1.0">
    <title>損益確認画面</title>
    <link href="https://use.fontawesome.com/releases/v5.6.1/css/all.css" rel="stylesheet">
    <link href="https://unpkg.com/sanitize.css" rel="stylesheet"/>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }
        .form-section {
            background-color: #f9f9f9;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .date-inputs {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }
        .result-section {
            background-color: #fff;
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 30px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
        .result-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid #eee;
            font-size: 1.2em;
        }
        .result-row:last-child {
            border-bottom: none;
        }
        .result-label {
            font-weight: bold;
            color: #555;
        }
        .result-value {
            font-weight: bold;
            font-size: 1.5em;
        }
        .profit-plus {
            color: #28a745;
        }
        .profit-minus {
            color: #dc3545;
        }
        .total-sales {
            color: #007bff;
        }
        .total-cost {
            color: #fd7e14;
        }
        .control-buttons {
            margin-top: 30px;
            text-align: center;
            display: flex;
            justify-content: center;
            gap: 15px;
        }
        h1 {
            text-align: center;
            margin-bottom: 30px;
            color: #333;
            border-bottom: 2px solid #007bff;
            padding-bottom: 10px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>損益確認 (<?= htmlspecialchars($shop_name) ?>)</h1>

        <?php if (isset($error_msg)): ?>
            <div class="alert alert-danger" style="background:#f8d7da; color:#721c24; padding:10px; margin-bottom:20px; border-radius:5px;">
                <?= htmlspecialchars($error_msg) ?>
            </div>
        <?php endif; ?>

        <section class="form-section">
            <form action="" method="POST">
                <div class="date-inputs">
                    <label>期間指定：</label>
                    <input type="date" name="c_day" value="<?= htmlspecialchars($start_date) ?>" required>
                    <span>〜</span>
                    <input type="date" name="ec_day" value="<?= htmlspecialchars($end_date) ?>" required>
                </div>
                <div class="text-center" style="text-align:center;">
                    <button type="submit" class="btn btn-primary" style="padding: 10px 30px; font-size:1.1em;">
                        <i class="fas fa-calculator"></i> 集計する
                    </button>
                </div>
            </form>
        </section>

        <?php if ($show_result): ?>
            <section class="result-section">
                <h3 style="text-align:center; margin-bottom:20px;">集計結果 (<?= $calc_days_count ?>日間)</h3>
                
                <div class="result-row">
                    <span class="result-label">売上合計</span>
                    <span class="result-value total-sales">
                        <?= number_format($total_sales) ?>円
                    </span>
                </div>
                
                <div class="result-row">
                    <span class="result-label">人件費合計 (時給+バック)</span>
                    <span class="result-value total-cost">
                        - <?= number_format($total_personnel_cost) ?>円
                    </span>
                </div>
                
                <div class="result-row" style="background-color: #f8f9fa; margin-top: 10px; border-radius: 5px; padding: 20px;">
                    <span class="result-label" style="font-size: 1.3em;">損益 (利益)</span>
                    <span class="result-value <?= ($profit >= 0) ? 'profit-plus' : 'profit-minus' ?>" style="font-size: 2em;">
                        <?= number_format($profit) ?>円
                    </span>
                </div>

                <div class="result-row">
                    <span class="result-label">人件費率</span>
                    <span class="result-value">
                        <?= number_format($cost_ratio, 1) ?>%
                    </span>
                </div>
            </section>
        <?php endif; ?>

        <div class="control-buttons">
            <a href="index.php?utype=<?= htmlspecialchars($utype) ?>" class="btn btn-secondary">
                <i class="fas fa-home"></i> メニューへ戻る
            </a>
        </div>
    </div>
</body>
</html>
