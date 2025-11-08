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

if ($shop_id !== null) {
    try {
        $stmt = $pdo->prepare("
            SELECT DISTINCT tc.cast_id, cm.cast_name
            FROM timecard_tbl tc
            LEFT JOIN cast_mst cm ON tc.cast_id = cm.cast_id
            WHERE tc.eigyo_ymd = ? AND tc.shop_id = ?
            ORDER BY cm.cast_yomi ASC
        ");
        $stmt->execute([$target_ymd, $shop_id]);
        $working_casts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching working casts: " . $e->getMessage());
    }
}
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
                            <th>金額</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($working_casts as $cast): ?>
                            <tr>
                                <td><?= htmlspecialchars($cast['cast_name'] ?? '不明') ?></td>
                                <td>0円</td>
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

