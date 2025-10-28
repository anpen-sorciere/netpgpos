<?php
// monthly_wage_summary.php

require_once(__DIR__ . '/../common/dbconnect.php');
require_once(__DIR__ . '/../common/functions.php');

// セッション開始
session_start();

// URLパラメータからutypeを取得し、セッションに保存する
if (isset($_GET['utype'])) {
    $_SESSION['utype'] = $_GET['utype'];
}

$utype = $_SESSION['utype'] ?? null;
// get_shop_info関数を使ってutypeから店舗情報を取得
$shop_info = get_shop_info($utype);
$shop_mst = $shop_info['id'] ?? null;
$shop_name = $shop_info['name'] ?? null;

$casts = [];
$summary_data = [];
$total_net_working_minutes = 0;
$total_daily_wage = 0;
$total_back_price = 0;
$message = '';

// connect()関数を呼び出してPDOオブジェクトを取得
$pdo = connect();

// POSTリクエスト処理 (フォームからの送信)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_pay_rate'])) {
        $selected_cast_id = $_POST['cast_id'] ?? null;
        $new_pay_rate = $_POST['new_pay_rate'] ?? null;
        $selected_month_ym = str_replace('-', '', $_POST['set_month']);
        $next_month_ym = date('Ym', strtotime('first day of next month', strtotime($selected_month_ym . '01')));

        if ($selected_cast_id !== null && $new_pay_rate !== null) {
            try {
                // pay_tblにレコードが存在するか確認
                $sql_check = "SELECT id FROM pay_tbl WHERE cast_id = :cast_id AND set_month = :set_month";
                $stmt_check = $pdo->prepare($sql_check);
                $stmt_check->execute([
                    ':cast_id' => $selected_cast_id,
                    ':set_month' => $next_month_ym
                ]);
                $existing_record = $stmt_check->fetch(PDO::FETCH_ASSOC);

                if ($existing_record) {
                    // 存在すれば更新
                    $sql_update = "UPDATE pay_tbl SET pay = :pay WHERE id = :id";
                    $stmt_update = $pdo->prepare($sql_update);
                    $stmt_update->execute([
                        ':pay' => $new_pay_rate,
                        ':id' => $existing_record['id']
                    ]);
                    $message = "来月の時給を更新しました。";
                } else {
                    // 存在しなければ新規登録
                    $sql_insert = "INSERT INTO pay_tbl (cast_id, set_month, pay) VALUES (:cast_id, :set_month, :pay)";
                    $stmt_insert = $pdo->prepare($sql_insert);
                    $stmt_insert->execute([
                        ':cast_id' => $selected_cast_id,
                        ':set_month' => $next_month_ym,
                        ':pay' => $new_pay_rate
                    ]);
                    $message = "来月の時給を新規登録しました。";
                }
            } catch (PDOException $e) {
                $message = "データベースエラー: " . $e->getMessage();
            }
        }
    } else {
        $selected_cast_id = $_POST['cast_id'] ?? null;
        $selected_month_ym = str_replace('-', '', $_POST['set_month']);
    }
}

// cast_get_all関数を使用してキャストリストを取得
$casts = cast_get_all($pdo);

$selected_cast_id = $selected_cast_id ?? null;
$selected_month_ym = $selected_month_ym ?? date('Ym'); // 初期値を現在月に設定

$next_month_pay_rate = null;

// フォームからキャストIDが送信された場合にのみ集計を実行
if ($selected_cast_id !== null) {
    // YYYYMM形式の文字列から年と月を抽出
    $year = substr($selected_month_ym, 0, 4);
    $month = substr($selected_month_ym, 4, 2);
    
    // 来月の年と月を計算
    $next_month_year = date('Y', strtotime('first day of next month', strtotime($selected_month_ym . '01')));
    $next_month_month = date('m', strtotime('first day of next month', strtotime($selected_month_ym . '01')));

    // cast_get関数を使用してキャスト名を取得
    $selected_cast = cast_get($pdo, $selected_cast_id);
    $cast_name = $selected_cast['cast_name'] ?? "不明なキャスト";

    // 選択された月のタイムカード情報を取得
    $sql_timecard = "SELECT * FROM timecard_tbl WHERE cast_id = :cast_id AND eigyo_ymd LIKE :eigyo_ymd ORDER BY eigyo_ymd";
    $stmt_timecard = $pdo->prepare($sql_timecard);
    $stmt_timecard->bindValue(':cast_id', $selected_cast_id, PDO::PARAM_INT);
    $stmt_timecard->bindValue(':eigyo_ymd', $selected_month_ym . '%', PDO::PARAM_STR);
    $stmt_timecard->execute();
    $timecard_records = $stmt_timecard->fetchAll(PDO::FETCH_ASSOC);

    // pay_get関数を使用して時給を取得
    $pay_data = pay_get($pdo, $selected_cast_id, $year, $month);
    // 連想配列からpay_amountの値を取得する
    $pay = $pay_data['pay_amount'] ?? 0;

    // 来月時給を取得
    $next_pay_data = pay_get($pdo, $selected_cast_id, $next_month_year, $next_month_month);
    $next_month_pay_rate = $next_pay_data['pay_amount'] ?? null;
    
    foreach ($timecard_records as $timecard_data) {
        $eigyo_ymd = $timecard_data['eigyo_ymd'];

        // functions.phpのcalculate_working_hours_minutes関数を呼び出して勤務時間を計算
        $working_times = calculate_working_hours_minutes($timecard_data);
        $net_working_time = $working_times['work_time_minutes'];

        $total_net_working_minutes += $net_working_time;

        $daily_wage = round($pay * ($net_working_time / 60));
        $total_daily_wage += $daily_wage;

        // バック金額を1回のクエリで取得 (receipt_detail_tblのcast_idを使用するように修正)
        $sql_back_price = "SELECT SUM(cast_back_price) AS total_back_price_day
                             FROM receipt_detail_tbl
                             WHERE cast_id = :cast_id 
                             AND shop_mst = :shop_mst 
                             AND receipt_day = :receipt_day";

        $stmt_back_price = $pdo->prepare($sql_back_price);
        $stmt_back_price->bindValue(':cast_id', $selected_cast_id, PDO::PARAM_INT);
        $stmt_back_price->bindValue(':shop_mst', $timecard_data['shop_mst'], PDO::PARAM_INT);
        $stmt_back_price->bindValue(':receipt_day', $eigyo_ymd, PDO::PARAM_STR);
        $stmt_back_price->execute();
        $total_back_price_day = $stmt_back_price->fetchColumn() ?? 0;
        
        $total_back_price += $total_back_price_day;
        
        // timecard_tblのshop_mstから店舗名を取得
        $shop_info_day = get_shop_info($timecard_data['shop_mst']);
        $shop_name_day = $shop_info_day['name'] ?? '不明';

        $summary_data[] = [
            'shop_name' => $shop_name_day,
            'date' => $eigyo_ymd,
            'net_working_minutes' => $net_working_time,
            'daily_wage' => $daily_wage,
            'total_back_price' => $total_back_price_day,
        ];
    }
}

// データベース接続を閉じる
disconnect($pdo);

// 6時間あたりのバック金額を計算
$back_per_6_hours = 0;
if ($total_net_working_minutes > 0) {
    $back_per_6_hours = ($total_back_price / $total_net_working_minutes) * 360;
}

// 時給マスターから時給を取得
$calculated_regular_rate = null;
$calculated_short_rate = null;

// hourly_rate_mstから通常時給と短時間時給を検索
if ($back_per_6_hours > 0) {
    $pdo = connect(); // 再接続
    $sql_regular = "SELECT MAX(hourly_rate) FROM hourly_rate_mst WHERE regular_work <= :back_per_6_hours";
    $stmt_regular = $pdo->prepare($sql_regular);
    $stmt_regular->bindValue(':back_per_6_hours', round($back_per_6_hours), PDO::PARAM_INT);
    $stmt_regular->execute();
    $calculated_regular_rate = $stmt_regular->fetchColumn();

    $sql_short = "SELECT MAX(hourly_rate) FROM hourly_rate_mst WHERE short_time_work <= :back_per_6_hours";
    $stmt_short = $pdo->prepare($sql_short);
    $stmt_short->bindValue(':back_per_6_hours', round($back_per_6_hours), PDO::PARAM_INT);
    $stmt_short->execute();
    $calculated_short_rate = $stmt_short->fetchColumn();
    
    disconnect($pdo); // 接続を閉じる
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>月次キャスト給与集計</title>
    <meta name="viewport" content="width=device-width,initial-scale=1.0,minimum-scale=1.0">
    <link href="https://unpkg.com/sanitize.css" rel="stylesheet"/>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <h1>月次キャスト給与集計</h1>

        <?php if ($message): ?>
            <div class="message">
                <p><?php echo h($message); ?></p>
            </div>
        <?php endif; ?>

        <form method="POST" action="" class="form-table">
            <table>
                <tr>
                    <th><label for="cast_id">キャスト名</label></th>
                    <td>
                        <select name="cast_id" id="cast_id" required>
                            <option value="">--選択してください--</option>
                            <?php foreach ($casts as $cast): ?>
                                <option value="<?php echo h($cast['cast_id']); ?>" <?php echo ($selected_cast_id == $cast['cast_id']) ? 'selected' : ''; ?>>
                                    <?php echo h($cast['cast_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="set_month">対象月</label></th>
                    <td>
                        <input type="month" name="set_month" id="set_month" value="<?php echo h(date('Y-m', strtotime($selected_month_ym . '01'))); ?>" required>
                    </td>
                </tr>
            </table>
            <div class="control-buttons">
                <button type="submit" class="btn btn-primary">集計</button>
            </div>
        </form>
    </div>

    <?php if ($selected_cast_id !== null && empty($summary_data)): ?>
        <div class="container" style="text-align: center;">
            <p>該当するデータが見つかりませんでした。</p>
        </div>
    <?php endif; ?>

    <?php if (!empty($summary_data)): ?>
        <div class="container">
            <h3><?php echo h($cast_name); ?> - <?php echo h(date('Y年m月', strtotime($selected_month_ym . '01'))); ?> の集計</h3>
            <p><strong>時給: <?php echo number_format($pay); ?>円</strong></p>
            <table class="result-table">
                <thead>
                    <tr>
                        <th>勤務店</th>
                        <th>日付</th>
                        <th>実働時間</th>
                        <th>日当</th>
                        <th>バック金額</th>
                        <th>日給合計</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($summary_data as $day): ?>
                        <tr>
                            <td><?php echo h($day['shop_name']); ?></td>
                            <td><?php echo h(format_ymd($day['date'])); ?></td>
                            <td><?php echo format_minutes_to_hours_minutes($day['net_working_minutes']); ?></td>
                            <td><?php echo number_format($day['daily_wage']); ?>円</td>
                            <td><?php echo number_format($day['total_back_price']); ?>円</td>
                            <td><?php echo number_format($day['daily_wage'] + $day['total_back_price']); ?>円</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="summary-block">
                <h3>月次合計</h3>
                <p><strong>合計実働時間: <?php echo format_minutes_to_hours_minutes($total_net_working_minutes); ?></strong></p>
                <p><strong>合計日当: <?php echo number_format($total_daily_wage); ?>円</strong></p>
                <p><strong>合計バック: <?php echo number_format($total_back_price); ?>円</strong></p>
                <p><strong>月給合計: <?php echo number_format($total_daily_wage + $total_back_price); ?>円</strong></p>
                <?php
                if ($total_net_working_minutes > 0) {
                    echo "<p><strong>6時間あたりのバック金額: " . number_format(round($back_per_6_hours)) . "円</strong></p>";
                }
                ?>
            </div>

            <div class="control-buttons">
                <?php if (!is_null($calculated_regular_rate)): ?>
                    <form method="POST" style="display:inline-block;">
                        <input type="hidden" name="cast_id" value="<?= h($selected_cast_id) ?>">
                        <input type="hidden" name="set_month" value="<?= h(date('Y-m', strtotime($selected_month_ym . '01'))) ?>">
                        <input type="hidden" name="new_pay_rate" value="<?= h($calculated_regular_rate) ?>">
                        <input type="hidden" name="update_pay_rate" value="1">
                        <button type="submit" class="btn btn-primary">通常時給を反映 (<?= number_format($calculated_regular_rate) ?>円)</button>
                    </form>
                <?php endif; ?>
                
                <?php if (!is_null($calculated_short_rate)): ?>
                    <form method="POST" style="display:inline-block;">
                        <input type="hidden" name="cast_id" value="<?= h($selected_cast_id) ?>">
                        <input type="hidden" name="set_month" value="<?= h(date('Y-m', strtotime($selected_month_ym . '01'))) ?>">
                        <input type="hidden" name="new_pay_rate" value="<?= h($calculated_short_rate) ?>">
                        <input type="hidden" name="update_pay_rate" value="1">
                        <button type="submit" class="btn btn-secondary">短時間時給を反映 (<?= number_format($calculated_short_rate) ?>円)</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
    
    <div class="control-buttons">
        <a href="index.php?utype=<?= h($utype) ?>" class="btn btn-secondary">メニューに戻る</a>
    </div>

</body>
</html>
