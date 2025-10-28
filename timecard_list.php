<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once('../common/dbconnect.php');
require_once('../common/functions.php');
session_start();

$utype_all = 0;
if (isset($_GET['utype'])) {
    $utype_all = $_GET['utype'];
    $_SESSION['utype'] = $utype_all;
} elseif (isset($_SESSION['utype'])) {
    $utype_all = $_SESSION['utype'];
}

$error = [];
$pdo = null;
$cast_timecard_data = [];

// 年月とキャストIDが指定されていなければ現在年月と「全員」を使用
$year = $_GET['year'] ?? date('Y');
$month = $_GET['month'] ?? date('m');
$selected_cast_id = $_GET['cast_id'] ?? 'all'; // デフォルトは「全員」
$selected_shop_mst = $_GET['shop_id'] ?? 'all'; // デフォルトは「全店」

// SQLクエリの準備
$sql = "SELECT * FROM timecard_tbl WHERE eigyo_ymd LIKE ? ";
$params = [$year . $month . '%'];

// 店舗が選択されている場合は絞り込む
if ($selected_shop_mst !== 'all') {
    $sql .= "AND shop_id = ? ";
    $params[] = intval($selected_shop_mst);
}

// キャストが選択されている場合は絞り込む
if ($selected_cast_id !== 'all') {
    $sql .= "AND cast_id = ? ";
    $params[] = intval($selected_cast_id);
}

$sql .= "ORDER BY cast_id ASC, eigyo_ymd ASC";

try {
    $pdo = connect();
    $casts = cast_get_all($pdo, 0);

    // 店舗リストを動的に取得
    $shops = [
        ['id' => 'all', 'name' => '全店'],
        ['id' => 1, 'name' => 'ソルシエール'],
        ['id' => 2, 'name' => 'レーヴェス'],
        ['id' => 3, 'name' => 'コレクト']
    ];

    $statement = $pdo->prepare($sql);
    $statement->execute($params);
    $timecard_list = $statement->fetchAll(PDO::FETCH_ASSOC);

    // キャストごとにデータを集計
    $cast_timecard_data = [];
    foreach ($casts as $cast) {
        $cast_id = $cast['cast_id'];
        $cast_name = $cast['cast_name'];
        $cast_timecard_data[$cast_id] = [
            'cast_name' => $cast_name,
            'details' => [],
            'total_work_minutes' => 0,
            'total_break_minutes' => 0,
        ];
    }
    
    // タイムカードデータをキャストに紐づけ
    foreach ($timecard_list as $row) {
        $cast_id = $row['cast_id'];
        if (isset($cast_timecard_data[$cast_id])) {
            $cast_timecard_data[$cast_id]['details'][] = $row;
            
            // 勤務時間と休憩時間を計算して合計に加算
            $calculated_times = calculate_working_hours_minutes($row);
            $cast_timecard_data[$cast_id]['total_work_minutes'] += $calculated_times['work_time_minutes'];
            $cast_timecard_data[$cast_id]['total_break_minutes'] += $calculated_times['break_time_minutes'];
        }
    }

    // 特定のキャストが選択されている場合、そのキャストのデータのみに絞り込む
    if ($selected_cast_id !== 'all' && isset($cast_timecard_data[$selected_cast_id])) {
        $cast_timecard_data = [$selected_cast_id => $cast_timecard_data[$selected_cast_id]];
    }

} catch (PDOException $e) {
    $error['db'] = "データベースエラー: " . $e->getMessage();
    error_log("Database Error: " . $e->getMessage());
} finally {
    disconnect($pdo);
}

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0,minimum-scale=1.0">
    <title>出勤簿一覧</title>
    <link href="https://unpkg.com/sanitize.css" rel="stylesheet"/>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .result-table {
            margin-top: 20px;
        }
        .result-table th, .result-table td {
            text-align: center;
        }
        .summary-block {
            padding: 15px;
            background-color: #f8f8f8;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
        }
        .summary-block h3 {
            margin: 0 0 10px 0;
            color: #333;
        }
        .summary-block p {
            margin: 5px 0;
            font-size: 1.1em;
            font-weight: bold;
        }
        .action-link {
            display: inline-block;
            padding: 5px 10px;
            background-color: #3498db;
            color: #fff;
            border-radius: 4px;
            text-decoration: none;
            transition: background-color 0.2s;
        }
        .action-link:hover {
            background-color: #2980b9;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>出勤簿一覧</h1>

        <?php if (!empty($error)): ?>
            <div class="error">
                <?php foreach ($error as $msg): ?>
                    <p><?= htmlspecialchars($msg, ENT_QUOTES) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form action="" method="get" class="control-group">
            <input type="hidden" name="utype" value="<?= htmlspecialchars($utype_all) ?>">
            
            <div class="control-group">
                <label for="shop_id">店舗:</label>
                <select name="shop_id" id="shop_id">
                    <?php foreach ($shops as $shop): ?>
                        <option value="<?= htmlspecialchars($shop['id']) ?>" <?= ($shop['id'] == $selected_shop_mst) ? 'selected' : ''; ?>>
                            <?= htmlspecialchars($shop['name'], ENT_QUOTES); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="control-group">
                <label for="cast_id">キャスト:</label>
                <select name="cast_id" id="cast_id">
                    <option value="all" <?= ($selected_cast_id === 'all') ? 'selected' : ''; ?>>全員</option>
                    <?php foreach ($casts as $row): ?>
                        <option value="<?= htmlspecialchars($row["cast_id"]) ?>" <?= ($row["cast_id"] == $selected_cast_id) ? 'selected' : ''; ?>>
                            <?= htmlspecialchars($row["cast_name"], ENT_QUOTES); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="control-group">
                <label for="year">年月:</label>
                <select name="year" id="year">
                    <?php for ($y = date('Y') - 5; $y <= date('Y') + 1; $y++): ?>
                        <option value="<?= $y ?>" <?= ($y == $year) ? 'selected' : ''; ?>><?= $y ?>年</option>
                    <?php endfor; ?>
                </select>
                <select name="month" id="month">
                    <?php for ($m = 1; $m <= 12; $m++): ?>
                        <option value="<?= sprintf('%02d', $m) ?>" <?= ($m == $month) ? 'selected' : ''; ?>><?= $m ?>月</option>
                    <?php endfor; ?>
                </select>
            </div>
            
            <div class="control-buttons">
                <button type="submit" class="btn btn-primary">表示</button>
            </div>
        </form>
    </div>

    <?php if (empty($cast_timecard_data) || (count($cast_timecard_data) == 1 && empty(current($cast_timecard_data)['details']))): ?>
        <div class="container" style="text-align: center;">
            <p>該当するデータが見つかりませんでした。</p>
        </div>
    <?php endif; ?>

    <?php foreach ($cast_timecard_data as $cast_id => $data): ?>
        <?php if (!empty($data['details'])): ?>
            <div class="container">
                <div class="summary-block">
                    <h3><?= htmlspecialchars($data['cast_name']) ?></h3>
                    <p>合計勤務時間: <?= format_minutes_to_hours_minutes($data['total_work_minutes']) ?></p>
                    <p>合計休憩時間: <?= format_minutes_to_hours_minutes($data['total_break_minutes']) ?></p>
                </div>

                <table class="result-table">
                    <thead>
                        <tr>
                            <th>店舗</th>
                            <th>営業年月日</th>
                            <th>出勤時間</th>
                            <th>退勤時間</th>
                            <th>休憩時間</th>
                            <th>実働時間</th>
                            <th>修正</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($data['details'] as $detail): ?>
                            <?php
                                $in_datetime = create_datetime_from_ymd_time($detail['in_ymd'], $detail['in_time']);
                                $out_datetime = create_datetime_from_ymd_time($detail['out_ymd'], $detail['out_time']);
                                $break_start_datetime = create_datetime_from_ymd_time($detail['break_start_ymd'], $detail['break_start_time']);
                                $break_end_datetime = create_datetime_from_ymd_time($detail['break_end_ymd'], $detail['break_end_time']);

                                $calculated_times = calculate_working_hours_minutes($detail);
                                $work_time_formatted = format_minutes_to_hours_minutes($calculated_times['work_time_minutes']);
                                $break_time_formatted = format_minutes_to_hours_minutes($calculated_times['break_time_minutes']);
                            ?>
                            <tr>
                                <td><?= htmlspecialchars(get_shop_info($detail['shop_mst'])['name'] ?? '不明') ?></td>
                                <td><?= htmlspecialchars(format_ymd($detail['eigyo_ymd'])) ?></td>
                                <td><?= $in_datetime ? htmlspecialchars($in_datetime->format('Y-m-d H:i')) : '-' ?></td>
                                <td><?= $out_datetime ? htmlspecialchars($out_datetime->format('Y-m-d H:i')) : '-' ?></td>
                                <td><?= $break_time_formatted ?></td>
                                <td><?= $work_time_formatted ?></td>
                                <td><a href="timecard_edit.php?cast_id=<?= $cast_id ?>&eigyo_ymd=<?= htmlspecialchars($detail['eigyo_ymd']) ?>" class="action-link">修正</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    <?php endforeach; ?>

    <div class="control-buttons">
        <a href="index.php?utype=<?= htmlspecialchars($utype_all) ?>" class="btn btn-secondary">メニューに戻る</a>
    </div>
</body>
</html>
