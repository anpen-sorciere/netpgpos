<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once(__DIR__ . '/../common/config.php');
require_once(__DIR__ . '/../common/dbconnect.php');
require_once(__DIR__ . '/../common/functions.php');
$pdo = connect();
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
$shop_id = $shop_info['id'] ?? null;

$selected_date = $_POST['target_date'] ?? date('Y-m-d');

$working_casts = [];
$formatted_date = DateTime::createFromFormat('Y-m-d', $selected_date);
$target_ymd = $formatted_date ? $formatted_date->format('Ymd') : date('Ymd');
$customer_map = [];
$cast_totals = [];
$cast_work_minutes = [];
$cast_pay = [];
$cast_targets = [];

if ($shop_id !== null) {
    try {
        $stmt = $pdo->prepare("
            SELECT DISTINCT tc.cast_id, cm.cast_name
            FROM timecard_tbl tc
            INNER JOIN cast_mst cm ON tc.cast_id = cm.cast_id
            WHERE tc.eigyo_ymd = ? AND tc.shop_id = ? AND cm.cast_type = 0
            ORDER BY cm.cast_yomi ASC
        ");
        $stmt->execute([$target_ymd, $shop_id]);
        $working_casts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!empty($working_casts)) {
            $castIds = array_column($working_casts, 'cast_id');
            $placeholders = implode(',', array_fill(0, count($castIds), '?'));
            $customer_stmt = $pdo->prepare("
                SELECT staff_id, customer_name
                FROM receipt_tbl
                WHERE receipt_day = ? AND staff_id IN ($placeholders)
            ");
            $customer_stmt->execute(array_merge([$target_ymd], $castIds));
            while ($row = $customer_stmt->fetch(PDO::FETCH_ASSOC)) {
                if (!isset($customer_map[$row['staff_id']])) {
                    $customer_map[$row['staff_id']] = [];
                }
                if (!empty($row['customer_name'])) {
                    $customer_map[$row['staff_id']][] = $row['customer_name'];
                }
            }
            
            $receipt_stmt = $pdo->prepare("
                SELECT receipt_id, staff_id
                FROM receipt_tbl
                WHERE receipt_day = ? AND staff_id IN ($placeholders)
            ");
            $receipt_stmt->execute(array_merge([$target_ymd], $castIds));
            $staff_receipts = $receipt_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (!empty($staff_receipts)) {
                $receiptIds = array_column($staff_receipts, 'receipt_id');
                $receiptIdPlaceholders = implode(',', array_fill(0, count($receiptIds), '?'));
                
                $detail_stmt = $pdo->prepare("
                    SELECT d.receipt_id, d.price, d.quantity
                    FROM receipt_detail_tbl d
                    WHERE d.receipt_id IN ($receiptIdPlaceholders)
                ");
                $detail_stmt->execute($receiptIds);
                $detail_totals = [];
                while ($detail = $detail_stmt->fetch(PDO::FETCH_ASSOC)) {
                    $rid = $detail['receipt_id'];
                    $detail_totals[$rid] = ($detail_totals[$rid] ?? 0) + ($detail['price'] * $detail['quantity']);
                }
                
                foreach ($staff_receipts as $sr) {
                    $sid = $sr['staff_id'];
                    $rid = $sr['receipt_id'];
                    $cast_totals[$sid] = ($cast_totals[$sid] ?? 0) + ($detail_totals[$rid] ?? 0);
                }
                
            }
            
            $time_stmt = $pdo->prepare("
                SELECT cast_id, in_ymd, in_time, out_ymd, out_time, break_start_ymd, break_start_time, break_end_ymd, break_end_time
                FROM timecard_tbl
                WHERE eigyo_ymd = ? AND shop_id = ? AND cast_id IN ($placeholders)
            ");
            $time_stmt->execute(array_merge([$target_ymd, $shop_id], $castIds));
            while ($time_row = $time_stmt->fetch(PDO::FETCH_ASSOC)) {
                $times = calculate_working_hours_minutes($time_row);
                $cast_id = $time_row['cast_id'];
                $cast_work_minutes[$cast_id] = ($cast_work_minutes[$cast_id] ?? 0) + ($times['work_time_minutes'] ?? 0);
            }
            
            $set_month = $formatted_date ? $formatted_date->format('Ym') : date('Ym');
            $pay_stmt = $pdo->prepare("
                SELECT cast_id, pay
                FROM pay_tbl
                WHERE set_month = ? AND cast_id IN ($placeholders)
            ");
            $pay_stmt->execute(array_merge([$set_month], $castIds));
            while ($pay_row = $pay_stmt->fetch(PDO::FETCH_ASSOC)) {
                $cast_pay[$pay_row['cast_id']] = (int)$pay_row['pay'];
            }
        }
    } catch (PDOException $e) {
        error_log("Error fetching working casts: " . $e->getMessage());
    }
}

disconnect($pdo);
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0,minimum-scale=1.0">
    <title>担当売り上げ確認</title>
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
            max-width: 800px;
            margin: 2rem auto;
            padding: 2.5rem;
            background-color: #fff;
            border-radius: 12px;
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12);
        }
        h1 {
            color: #2c3e50;
            border-bottom: 2px solid #3498db;
            padding-bottom: 0.75rem;
            margin-bottom: 2rem;
            text-align: center;
        }
        .form-section {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            margin-bottom: 2rem;
        }
        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        .form-group label {
            font-weight: bold;
            color: #555;
        }
        .form-group input[type="date"] {
            padding: 0.65rem 0.75rem;
            border: 1px solid #ced4da;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }
        .form-group input[type="date"]:focus {
            outline: none;
            border-color: #3498db;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.25);
        }
        .btn {
            display: inline-block;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 999px;
            font-size: 1rem;
            font-weight: bold;
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
            text-decoration: none;
            text-align: center;
        }
        .btn-primary {
            background-color: #3498db;
            color: #fff;
            box-shadow: 0 6px 18px rgba(52, 152, 219, 0.35);
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 24px rgba(52, 152, 219, 0.45);
        }
        .btn-secondary {
            background-color: #ecf0f1;
            color: #34495e;
            box-shadow: 0 6px 18px rgba(0, 0, 0, 0.15);
        }
        .btn-secondary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 24px rgba(0, 0, 0, 0.2);
        }
        .actions {
            display: flex;
            justify-content: center;
            gap: 1rem;
            margin-top: 2rem;
        }
        .info-card {
            background-color: #eaf5ff;
            border-left: 4px solid #3498db;
            padding: 1rem 1.25rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            color: #2c3e50;
        }
        .table-container {
            margin-top: 2rem;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.95rem;
        }
        th, td {
            border: 1px solid #dee2e6;
            padding: 12px;
            text-align: left;
        }
        th {
            background-color: #3498db;
            color: #fff;
            font-weight: bold;
        }
        tbody tr:nth-child(even) {
            background-color: #f8f9fb;
        }
        .below-target {
            background-color: #e74c3c !important;
            color: #fff;
        }
        .below-target td {
            color: #fff;
        }
        .empty-state {
            text-align: center;
            color: #777;
            padding: 2rem 0;
        }
    </style>
</head>
<body>
    <div class="content">
        <h1>担当売り上げ確認 (<?= htmlspecialchars($shop_name) ?>)</h1>

        <div class="info-card">
            担当キャストごとの売上集計を表示します。対象日を選択し「表示」を押してください。
        </div>

        <form action="staff_sales_summary.php?utype=<?= htmlspecialchars($utype) ?>" method="POST" class="form-section">
            <div class="form-group">
                <label for="target_date">対象日</label>
                <input type="date" id="target_date" name="target_date" value="<?= htmlspecialchars($selected_date) ?>" required>
            </div>
            <div class="actions">
                <button type="submit" class="btn btn-primary">表示</button>
            </div>
        </form>

        <div class="table-container">
            <?php if (!empty($working_casts)): ?>
                <table>
                    <thead>
                        <tr>
                            <th>キャスト名</th>
                            <th>時給</th>
                            <th>勤務時間</th>
                            <th>担当顧客一覧</th>
                            <th>目標売上</th>
                            <th>金額</th>
                            <th>達成率</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($working_casts as $cast): ?>
                            <?php
                                $customers = $customer_map[$cast['cast_id']] ?? [];
                                $customer_text = !empty($customers) ? implode(', ', $customers) : '';
                                $minutes = $cast_work_minutes[$cast['cast_id']] ?? 0;
                                $hours = intdiv($minutes, 60);
                                $mins = $minutes % 60;
                                $work_display = $minutes > 0 ? sprintf('%d時間%d分', $hours, $mins) : '-';
                                $pay_amount = $cast_pay[$cast['cast_id']] ?? 0;
                                $target_amount = ($pay_amount > 0 && $minutes > 0) ? (int)round($pay_amount * 2 * ($minutes / 60), 0) : 0;
                                $sales_amount = $cast_totals[$cast['cast_id']] ?? 0;
                                $achievement_value = ($target_amount > 0) ? round(($sales_amount / $target_amount) * 100, 1) : null;
                                $achievement = ($achievement_value !== null) ? $achievement_value . '%' : '-';
                                $row_class = ($achievement_value !== null && $achievement_value < 100) ? 'below-target' : '';
                            ?>
                            <tr class="<?= $row_class ?>">
                                <td><?= htmlspecialchars($cast['cast_name'] ?? '不明') ?></td>
                                <td><?= $pay_amount > 0 ? number_format($pay_amount) . '円' : '-' ?></td>
                                <td><?= htmlspecialchars($work_display) ?></td>
                                <td><?= htmlspecialchars($customer_text) ?></td>
                                <td><?= $target_amount > 0 ? number_format($target_amount) . '円' : '-' ?></td>
                                <td><?= number_format($sales_amount) ?>円</td>
                                <td><?= $achievement ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    対象日に出勤したキャストは見つかりませんでした。
                </div>
            <?php endif; ?>
        </div>

        <div class="actions">
            <a href="index.php?utype=<?= htmlspecialchars($utype) ?>" class="btn btn-secondary">メニューへ戻る</a>
        </div>
    </div>
</body>
</html>

