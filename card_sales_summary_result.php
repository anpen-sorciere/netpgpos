<?php
require_once(__DIR__ . '/../common/config.php');
require_once(__DIR__ . '/../common/dbconnect.php');
require_once(__DIR__ . '/../common/functions.php');
session_start();

if (!isset($_SESSION['join'])) {
    header('Location: card_sales_summary.php');
    exit();
}

$c_month = $_SESSION['join']['c_month'] ?? null;
$ec_month = $_SESSION['join']['ec_month'] ?? null;
$utype = $_SESSION['utype'] ?? null;

if (!$c_month || !$ec_month) {
    header('Location: card_sales_summary.php');
    exit();
}

$startDate = DateTime::createFromFormat('Y-m-d', $c_month);
$endDate = DateTime::createFromFormat('Y-m-d', $ec_month);

if (!$startDate || !$endDate) {
    header('Location: card_sales_summary.php');
    exit();
}

$startYmd = $startDate->format('Ymd');
$endYmd = $endDate->format('Ymd');

$records = [];
$totals = [
    'sales_amount' => 0,
    'purchase_cost' => 0,
    'personnel_cost' => 0,
];

$db = connect();
$stmt = $db->prepare("SELECT * FROM card_sales_temp_tbl WHERE data_day BETWEEN ? AND ? ORDER BY data_day");
$stmt->execute([$startYmd, $endYmd]);
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $records[] = $row;
    $totals['sales_amount'] += (int)$row['sales_amount'];
    $totals['purchase_cost'] += (int)$row['purchase_cost'];
    $totals['personnel_cost'] += (int)$row['personnel_cost'];
}
$db = null;

?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0,minimum-scale=1.0">
    <title>カード販売仕入れデータ確認画面</title>
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
            max-width: 900px;
            background-color: #fff;
            border-radius: 12px;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.1);
            padding: 30px;
        }
        h1 {
            color: #2c3e50;
            text-align: center;
            margin-bottom: 15px;
        }
        .summary-text {
            text-align: center;
            font-size: 1rem;
            color: #555;
            margin-bottom: 25px;
        }
        .result-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .result-table th,
        .result-table td {
            padding: 12px;
            border: 1px solid #e0e6ed;
            text-align: center;
        }
        .result-table th {
            background-color: #f8f9fb;
            color: #34495e;
            font-weight: 600;
        }
        .result-table tfoot td {
            font-weight: bold;
            background-color: #f1f6fb;
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
            text-decoration: none;
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
        .empty-state {
            text-align: center;
            color: #777;
            padding: 40px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>カード販売仕入れ集計結果</h1>
        <p class="summary-text">
            集計対象期間: <?= htmlspecialchars($startDate->format('Y年m月d日')) ?> 〜 <?= htmlspecialchars($endDate->format('Y年m月d日')) ?>
        </p>

        <?php if (!empty($records)): ?>
            <table class="result-table">
                <thead>
                    <tr>
                        <th>日付</th>
                        <th>カード売上</th>
                        <th>カード仕入買取</th>
                        <th>人件費総計</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($records as $record): ?>
                        <tr>
                            <td><?= htmlspecialchars(DateTime::createFromFormat('Ymd', $record['data_day'])->format('Y-m-d')) ?></td>
                            <td><?= number_format($record['sales_amount']) ?></td>
                            <td><?= number_format($record['purchase_cost']) ?></td>
                            <td><?= number_format($record['personnel_cost']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td>合計</td>
                        <td><?= number_format($totals['sales_amount']) ?></td>
                        <td><?= number_format($totals['purchase_cost']) ?></td>
                        <td><?= number_format($totals['personnel_cost']) ?></td>
                    </tr>
                </tfoot>
            </table>
        <?php else: ?>
            <div class="empty-state">
                <p>指定期間のデータは見つかりませんでした。</p>
            </div>
        <?php endif; ?>

        <div class="control-buttons">
            <a href="card_sales_summary.php?utype=<?= htmlspecialchars($utype) ?>" class="btn-secondary">戻る</a>
            <a href="index.php?utype=<?= htmlspecialchars($utype) ?>" class="btn-primary">メニューへ</a>
        </div>
    </div>
</body>
</html>
