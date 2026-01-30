<?php
// エラーレポートを有効にし、すべてのエラーを表示
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// エラーログを有効にし、エラーログファイルのパスを指定
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/log/error.log');

require_once(__DIR__ . '/../common/config.php');
require_once(__DIR__ . '/../common/dbconnect.php');
require_once(__DIR__ . '/../common/functions.php');

session_start();

// URLパラメータからutypeを取得し、セッションに保存する
if (isset($_GET['utype'])) {
    $_SESSION['utype'] = $_GET['utype'];
}

// POSTリクエストがなければ、常にフォームを表示する
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    unset($_SESSION['join']);
    $show_form = true;

    $pdo = connect();
    $casts = cast_get_all($pdo);
    $pdo = null;
} else {
    // POSTリクエストの場合は、セッションにデータを保存
    $_SESSION['join'] = $_POST;
    if (isset($_POST['utype'])) {
        $_SESSION['utype'] = $_POST['utype'];
    }
    $show_form = false;
}

if (!isset($_SESSION['utype'])) {
    echo "エラー: ユーザータイプ情報がありません。";
    exit();
}

$summary_data_view = [];
$total_all_casts_wages = 0;
$hourly_wage = 0;
$jikyu_kingaku = 0;

function format_time_over_24h($ymd_in, $time_in, $ymd_out, $time_out) {
    if (empty($ymd_in) || empty($time_in) || empty($ymd_out) || empty($time_out)) {
        return '未登録';
    }

    $dt_in = new DateTime($ymd_in . ' ' . substr($time_in, 0, 2) . ':' . substr($time_in, 2, 2));
    $dt_out = new DateTime($ymd_out . ' ' . substr($time_out, 0, 2) . ':' . substr($time_out, 2, 2));

    // 翌日の午前5時までを翌日と判断
    if (
        $dt_out->format('Y-m-d H:i') < $dt_in->format('Y-m-d H:i') &&
        (int)$dt_out->format('H') <= 5
    ) {
        $dt_out->modify('+1 day');
    }

    return $dt_out->format('H:i');
}

if (!$show_form) {
    $utype = $_SESSION['utype'];
    $ymd = explode('-', $_SESSION['join']['c_day']);
    $c_day_format = $ymd[0] . $ymd[1] . $ymd[2];
    $shop_info = get_shop_info($utype);
    $shop_id = $shop_info['id'];
    $shop_name = $shop_info['name'];
    $cast_id = $_SESSION['join']['cast_id'];

    $cast_name = "";
    if ($cast_id == '0') {
        $cast_name = "全員";
    } else {
        $pdo_cast = connect();
        $cast_data_temp = cast_get($pdo_cast, $cast_id);
        $cast_name = $cast_data_temp['cast_name'] ?? '不明';
        $pdo_cast = null;
    }

    $pdo = connect();

    if ($cast_id == '0') {
        $stmh_cast_ids = $pdo->prepare("
            SELECT cast_id FROM timecard_tbl WHERE shop_id = ? AND eigyo_ymd = ?
            UNION
            SELECT cast_id FROM receipt_detail_tbl WHERE shop_id = ? AND receipt_day = ? AND cast_id > 0
        ");
        $stmh_cast_ids->execute(array($shop_id, $c_day_format, $shop_id, $c_day_format));
        $relevant_cast_ids = $stmh_cast_ids->fetchAll(PDO::FETCH_COLUMN, 0);
        $relevant_cast_ids = array_unique($relevant_cast_ids);

        $all_summary = [];

        foreach ($relevant_cast_ids as $current_cast_id) {
            $cast_data_row = cast_get($pdo, $current_cast_id);
            if (!$cast_data_row) continue;

            $cast_summary = [
                'cast_name' => $cast_data_row['cast_name'],
                'in_time' => '',
                'out_time' => '',
                'hourly_wage_total' => 0,
                'back_wage_total' => 0,
                'total_wage' => 0,
                'hourly_wage_note' => null
            ];

            $stmh_timecard = $pdo->prepare("SELECT * FROM timecard_tbl WHERE cast_id = ? AND shop_id = ? AND eigyo_ymd = ?");
            $stmh_timecard->execute(array($current_cast_id, $shop_id, $c_day_format));
            $timecard_data = $stmh_timecard->fetch(PDO::FETCH_ASSOC);

            $jikyu_kingaku = 0;
            if ($timecard_data) {
                $times = calculate_working_hours_minutes($timecard_data);
                $total_minutes = $times['work_time_minutes'];
                $pay_data = pay_get($pdo, $current_cast_id, $ymd[0], $ymd[1]);
                $hourly_wage = (int)($pay_data['pay_amount'] ?? 0);

                if ($hourly_wage == 0) {
                    $cast_summary['hourly_wage_note'] = '時給未登録';
                } else {
                    $jikyu_kingaku = ceil($hourly_wage * ($total_minutes / 60));
                }
                
                $cast_summary['in_time'] = substr($timecard_data['in_time'], 0, 2) . ':' . substr($timecard_data['in_time'], 2, 2);
                $cast_summary['out_time'] = format_time_over_24h(
                    $timecard_data['in_ymd'],
                    $timecard_data['in_time'],
                    $timecard_data['out_ymd'],
                    $timecard_data['out_time']
                );
                $cast_summary['hourly_wage_total'] = $jikyu_kingaku;
            }

            $stmh_receipt = $pdo->prepare("
                SELECT SUM(im.back_price * rd.quantity) AS total_back_wage
                FROM receipt_detail_tbl AS rd
                JOIN item_mst AS im ON rd.item_id = im.item_id
                WHERE rd.shop_id = ? AND rd.cast_id = ? AND rd.receipt_day = ? AND im.back_price > 0
            ");
            $stmh_receipt->execute(array($shop_id, $current_cast_id, $c_day_format));
            $back_data = $stmh_receipt->fetch(PDO::FETCH_ASSOC);

            $cast_summary['back_wage_total'] = intval($back_data['total_back_wage']);
            $cast_summary['total_wage'] = $cast_summary['hourly_wage_total'] + $cast_summary['back_wage_total'];
            $total_all_casts_wages += $cast_summary['total_wage'];

            $all_summary[] = $cast_summary;
        }
    } else {
        // 個別キャストの場合
        // timecard_tbl は shop_id カラムを使用
        $stmh_timecard = $pdo->prepare("SELECT * FROM timecard_tbl WHERE cast_id = ? AND shop_id = ? AND eigyo_ymd = ?");
        $stmh_timecard->execute(array($cast_id, $shop_id, $c_day_format));
        $timecard_data = $stmh_timecard->fetch(PDO::FETCH_ASSOC);

        $work_minutes = 0;
        $break_minutes = 0;
        $hourly_wage = 0;
        $jikyu_kingaku = 0;

        if ($timecard_data) {
            $times = calculate_working_hours_minutes($timecard_data);
            $work_minutes = $times['work_time_minutes'];
            $break_minutes = $times['break_time_minutes'];

            $pay_data = pay_get($pdo, $cast_id, $ymd[0], $ymd[1]);
            if (is_array($pay_data)) {
                $hourly_wage = (int)($pay_data['pay_amount'] ?? 0);
            }
            if ($hourly_wage > 0) {
                $jikyu_kingaku = ceil($hourly_wage * ($work_minutes / 60));
            } else {
                $jikyu_kingaku = '時給が登録されていません';
            }
        }

        $stmh_receipt = $pdo->prepare("
            SELECT
                rd.item_id,
                SUM(rd.quantity) AS total_quantity,
                im.item_name,
                im.back_price
            FROM
                receipt_detail_tbl AS rd
            JOIN
                item_mst AS im ON rd.item_id = im.item_id
            WHERE
                rd.shop_id = ? AND rd.cast_id = ? AND rd.receipt_day = ? AND im.back_price > 0
            GROUP BY
                rd.item_id
            ORDER BY
                rd.item_id
        ");
        $stmh_receipt->execute(array($shop_id, $cast_id, $c_day_format));
        $summary_data_view = $stmh_receipt->fetchAll(PDO::FETCH_ASSOC);

        $total_back_wage = 0;
        foreach($summary_data_view as $item) {
            $total_back_wage += (int)$item['total_quantity'] * (int)$item['back_price'];
        }
        
        $total_all_casts_wages = (is_numeric($jikyu_kingaku) ? $jikyu_kingaku : 0) + $total_back_wage;
    }
    $pdo = null;
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0,minimum-scale=1.0">
    <title>日当集計データ確認画面</title>
    <link href="https://use.fontawesome.com/releases/v5.6.1/css/all.css" rel="stylesheet">
    <link href="https://unpkg.com/sanitize.css" rel="stylesheet"/>
    <link rel="stylesheet" href="css/style.css">
    <style>
    /* 印刷時に日当合計の数字を強調（既存の約2倍） */
    @media print {
        .summary-table tfoot .grand-total {
            font-size: 20pt !important;
            font-weight: bold !important;
            color: #d32f2f !important;
        }
    }
    </style>
</head>
<body>
    <div class="container">
        <?php if ($show_form): ?>
            <section class="form-section no_print">
                <h1>日当集計データ入力</h1>
                <form action="daily_wage_summary.php" method="POST">
                    <table class="form-table">
                        <tr>
                            <th>集計日</th>
                            <td><input type="date" name="c_day" value="<?php echo date('Y-m-d'); ?>"></td>
                        </tr>
                        <tr>
                            <th>キャスト</th>
                            <td>
                                <select name="cast_id">
                                    <option value="0">全員</option>
                                    <?php foreach ($casts as $cast): ?>
                                        <option value="<?php echo htmlspecialchars($cast['cast_id']); ?>">
                                            <?php echo htmlspecialchars($cast['cast_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                    </table>
                    <input type="hidden" name="utype" value="<?php echo htmlspecialchars($_SESSION['utype'] ?? ''); ?>">
                    <div class="control-buttons">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> 集計する</button>
                        <a href="index.php?utype=<?php echo htmlspecialchars($_SESSION['utype'] ?? ''); ?>" class="btn btn-secondary" style="text-decoration:none; display:inline-block;"><i class="fas fa-home"></i> メニューへ</a>
                    </div>
                </form>
            </section>
        <?php else: ?>
            <div class="summary-header no_print">
                <h1>日当集計データ</h1>
                <p>
                    集計日：<strong><?php echo htmlspecialchars($ymd[0])?>年<?php echo htmlspecialchars($ymd[1])?>月<?php echo htmlspecialchars($ymd[2])?>日</strong><br>
                    店舗：<strong><?php echo htmlspecialchars($shop_name); ?></strong><br>
                    キャスト：<strong><?php echo htmlspecialchars($cast_name); ?></strong>
                </p>
            </div>
            <div class="print_only">
                <div class="print_header">
                    <p>
                        <?php echo htmlspecialchars($ymd[0])?>年<?php echo htmlspecialchars($ymd[1])?>月<?php echo htmlspecialchars($ymd[2])?>日 | 
                        店舗：<?php echo htmlspecialchars($shop_name); ?> | 
                        キャスト：<?php echo htmlspecialchars($cast_name); ?>
                    </p>
                </div>
            </div>
            <?php if ($cast_id == '0'): ?>
                <section class="table-section">
                    <h2>キャスト別 日当サマリー</h2>
                    <table class="summary-table">
                        <thead>
                            <tr>
                                <th>キャスト名</th>
                                <th>出勤時間</th>
                                <th>退勤時間</th>
                                <th class="right-align">時給金額</th>
                                <th class="right-align">バック金額</th>
                                <th class="right-align">合計総計金額</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($all_summary as $cast_data): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($cast_data['cast_name']); ?></td>
                                <td><?php echo htmlspecialchars($cast_data['in_time'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($cast_data['out_time'] ?? ''); ?></td>
                                <td class="right-align">
                                    <?php if(isset($cast_data['hourly_wage_note'])): ?>
                                        <span class="error"><?php echo htmlspecialchars($cast_data['hourly_wage_note']); ?></span>
                                    <?php else: ?>
                                        <?php echo number_format($cast_data['hourly_wage_total']); ?>円
                                    <?php endif; ?>
                                </td>
                                <td class="right-align"><?php echo number_format($cast_data['back_wage_total']); ?>円</td>
                                <td class="right-align highlight"><?php echo number_format($cast_data['total_wage']); ?>円</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr class="total-row">
                                <td colspan="5" class="right-align">全キャスト合計総計</td>
                                <td class="right-align highlight"><?php echo number_format($total_all_casts_wages); ?>円</td>
                            </tr>
                        </tfoot>
                    </table>
                </section>
            <?php else: ?>
                <section class="table-section">
                    <h2>キャスト情報</h2>
                    <table class="detail-table">
                        <tbody>
                            <?php if ($timecard_data): ?>
                            <tr>
                                <th>出勤時間</th>
                                <td>
                                    <?php echo htmlspecialchars(format_ymd($timecard_data['in_ymd'])) . ' ' . htmlspecialchars(format_time($timecard_data['in_time'])); ?>
                                </td>
                                <th>退勤時間</th>
                                <td>
                                    <?php echo htmlspecialchars(format_ymd($timecard_data['out_ymd'])) . ' ' . htmlspecialchars(format_time($timecard_data['out_time'])); ?>
                                </td>
                            </tr>
                            <tr>
                                <th>勤務時間</th>
                                <td><?php echo floor($work_minutes / 60) . '時間' . ($work_minutes % 60) . '分'; ?></td>
                                <th>休憩時間</th>
                                <td><?php echo floor($break_minutes / 60) . '時間' . ($break_minutes % 60) . '分'; ?></td>
                            </tr>
                            <tr>
                                <th>休憩開始</th>
                                <td>
                                    <?php echo !empty($timecard_data['break_start_ymd']) ? htmlspecialchars(format_ymd($timecard_data['break_start_ymd'])) . ' ' . htmlspecialchars(format_time($timecard_data['break_start_time'])) : '未登録'; ?>
                                </td>
                                <th>休憩終了</th>
                                <td>
                                    <?php echo !empty($timecard_data['break_end_ymd']) ? htmlspecialchars(format_ymd($timecard_data['break_end_ymd'])) . ' ' . htmlspecialchars(format_time($timecard_data['break_end_time'])) : '未登録'; ?>
                                </td>
                            </tr>
                            <tr>
                                <th>時給</th>
                                <td>
                                    <?php if (is_numeric($hourly_wage) && $hourly_wage > 0): ?>
                                        <?php echo number_format($hourly_wage); ?>円
                                    <?php else: ?>
                                        <span class="error">未登録</span>
                                    <?php endif; ?>
                                </td>
                                <th>時給金額</th>
                                <td>
                                    <?php if (is_numeric($jikyu_kingaku)): ?>
                                        <?php echo number_format($jikyu_kingaku); ?>円
                                    <?php else: ?>
                                        <span class="error"><?php echo htmlspecialchars($jikyu_kingaku); ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php else: ?>
                            <tr>
                                <td colspan="4">勤務データがありません。</td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </section>
                <section class="table-section">
                    <h2>売上バック明細</h2>
                    <table class="detail-back-table">
                        <thead>
                            <tr>
                                <th class="center-align">商品ID</th>
                                <th>商品名</th>
                                <th class="right-align">数量</th>
                                <th class="right-align">バック金額</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $total_back_wage = 0;
                            foreach ($summary_data_view as $row) {
                                $back_amount = (int)$row['total_quantity'] * (int)$row['back_price'];
                                $total_back_wage += $back_amount;
                                echo '<tr>';
                                echo '<td class="center-align">' . htmlspecialchars($row['item_id']) . '</td>';
                                echo '<td>' . htmlspecialchars($row['item_name']) . '</td>';
                                echo '<td class="right-align">' . number_format($row['total_quantity']) . '</td>';
                                echo '<td class="right-align">' . number_format($back_amount) . '円</td>';
                                echo '</tr>';
                            }
                            ?>
                        </tbody>
                        <tfoot>
                            <tr class="total-row">
                                <td colspan="3" class="right-align">売上バック合計</td>
                                <td class="right-align highlight"><?php echo number_format($total_back_wage); ?>円</td>
                            </tr>
                            <tr class="total-row">
                                <td colspan="3" class="right-align">日当合計</td>
                                <td class="right-align highlight grand-total" style="font-size: 20pt !important; font-weight: bold !important; color: #d32f2f !important;"><?php echo number_format($total_all_casts_wages); ?>円</td>
                            </tr>
                        </tfoot>
                    </table>
                </section>
            <?php endif; ?>
            <div class="control-buttons no_print">
                <a href="daily_wage_summary.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> 戻る</a>
                <a href="index.php?utype=<?php echo htmlspecialchars($utype); ?>" class="btn btn-secondary"><i class="fas fa-home"></i> メニューへ</a>
                <button onclick="window.print();" class="btn btn-secondary"><i class="fas fa-print"></i> 印刷</button>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
