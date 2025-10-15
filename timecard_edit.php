<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once('./dbconnect.php');
require_once('./functions.php');
session_start();

$utype = 0;
if (isset($_GET['utype'])) {
    $utype = $_GET['utype'];
    $_SESSION['utype'] = $utype;
} elseif (isset($_SESSION['utype'])) {
    $utype = $_SESSION['utype'];
}

$error = [];
$pdo = null;

// 店舗情報と店舗IDを取得
$shop_info = get_shop_info($utype);
$shop_id = $shop_info['id'];

$cast_id = $_GET['cast_id'] ?? null;
$eigyo_ymd = $_GET['eigyo_ymd'] ?? null;

// GETパラメータが不足している場合はエラー
if (empty($cast_id) || empty($eigyo_ymd)) {
    $error['param'] = 'キャストIDまたは営業年月日が指定されていません。';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $_SESSION['timecard'] = $_POST;
        
        $pdo = connect();
        
        $cast_id = $_POST['cast_id'] ?? null;
        $eigyo_ymd = $_POST['eigyo_ymd'] ?? null;
        $in_ymd = $_POST['in_ymd'] ?? null;
        $in_time = $_POST['in_time'] ?? null;
        $out_ymd = $_POST['out_ymd'] ?? null;
        $out_time = $_POST['out_time'] ?? null;
        $break_start_ymd = $_POST['break_start_ymd'] ?? null;
        $break_start_time = $_POST['break_start_time'] ?? null;
        $break_end_ymd = $_POST['break_end_ymd'] ?? null;
        $break_end_time = $_POST['break_end_time'] ?? null;

        if (empty($eigyo_ymd)) {
            $error['eigyo_ymd'] = '営業年月日は必須です。';
        }
        
        if ((empty($in_ymd) || empty($in_time)) && (empty($out_ymd) || empty($out_time))) {
            $error['work_time'] = '出勤または退勤の年月と時間は少なくともどちらか一方を必須です。';
        }

        if (!empty($break_start_time) || !empty($break_end_time)) {
            if (empty($break_start_ymd) || empty($break_start_time) || empty($break_end_ymd) || empty($break_end_time)) {
                $error['break_time'] = '休憩時間を入力する場合は、開始・終了の年月と時間を全て入力してください。';
            }
        }
        
        if (empty($error) && !empty($in_ymd) && !empty($in_time) && !empty($out_ymd) && !empty($out_time)) {
            $in_datetime_str = $in_ymd . ' ' . $in_time;
            $out_datetime_str = $out_ymd . ' ' . $out_time;

            try {
                $in_datetime = new DateTime($in_datetime_str);
                $out_datetime = new DateTime($out_datetime_str);

                if ($out_datetime < $in_datetime) {
                    $out_datetime->modify('+1 day');
                    if ($out_datetime < $in_datetime) {
                         $error['time_order'] = '退勤日時が出勤日時より前です。';
                    }
                }
            } catch (Exception $e) {
                 $error['time_order'] = '日付または時間のフォーマットが不正です。';
            }
        }

        if(empty($error)){
            $statement = $pdo->prepare("UPDATE timecard_tbl SET in_ymd=?, in_time=?, out_ymd=?, out_time=?, break_start_ymd=?, break_start_time=?, break_end_ymd=?, break_end_time=? WHERE cast_id=? AND shop_id=? AND eigyo_ymd=?");
            $statement->execute(array(
                str_replace('-', '', $in_ymd),
                str_replace(':', '', $in_time),
                str_replace('-', '', $out_ymd),
                str_replace(':', '', $out_time),
                str_replace('-', '', $break_start_ymd),
                str_replace(':', '', $break_start_time),
                str_replace('-', '', $break_end_ymd),
                str_replace(':', '', $break_end_time),
                intval($cast_id),
                intval($shop_id),
                str_replace('-', '', $eigyo_ymd)
            ));
            
            unset($_SESSION['timecard']);
            disconnect($pdo);
            header('Location: timecard_list.php'); // 更新後は一覧画面へリダイレクト
            exit();
        }
    } catch (PDOException $e) {
        $error['db'] = "データベースエラー: " . $e->getMessage();
        error_log("Database Error: " . $e->getMessage());
    }
} else {
    // 初回表示時（GETリクエスト）
    try {
        $pdo = connect();
        $statement = $pdo->prepare("SELECT * FROM timecard_tbl WHERE cast_id = ? AND shop_id = ? AND eigyo_ymd = ?");
        $statement->execute(array(
            intval($cast_id),
            intval($shop_id),
            str_replace('-', '', $eigyo_ymd)
        ));
        $timecard_data = $statement->fetch(PDO::FETCH_ASSOC);

        if (!$timecard_data) {
            $error['data'] = '指定されたデータが見つかりませんでした。';
        } else {
            // データをセッションに格納してフォームに表示
            $_SESSION['timecard'] = [
                'cast_id' => $timecard_data['cast_id'],
                'eigyo_ymd' => format_ymd($timecard_data['eigyo_ymd']),
                'in_ymd' => format_ymd($timecard_data['in_ymd']),
                'in_time' => format_time($timecard_data['in_time']),
                'out_ymd' => format_ymd($timecard_data['out_ymd']),
                'out_time' => format_time($timecard_data['out_time']),
                'break_start_ymd' => format_ymd($timecard_data['break_start_ymd']),
                'break_start_time' => format_time($timecard_data['break_start_time']),
                'break_end_ymd' => format_ymd($timecard_data['break_end_ymd']),
                'break_end_time' => format_time($timecard_data['break_end_time'])
            ];
        }

        disconnect($pdo);

    } catch (PDOException $e) {
        $error['db'] = "データベースエラー: " . $e->getMessage();
        error_log("Database Error: " . $e->getMessage());
    }
}

$cast_id_selected = $_SESSION['timecard']['cast_id'] ?? null;
$eigyo_ymd_default = $_SESSION['timecard']['eigyo_ymd'] ?? null;
$in_ymd_value = $_SESSION['timecard']['in_ymd'] ?? '';
$in_time_value = $_SESSION['timecard']['in_time'] ?? '';
$out_ymd_value = $_SESSION['timecard']['out_ymd'] ?? '';
$out_time_value = $_SESSION['timecard']['out_time'] ?? '';
$break_start_ymd_value = $_SESSION['timecard']['break_start_ymd'] ?? '';
$break_start_time_value = $_SESSION['timecard']['break_start_time'] ?? '';
$break_end_ymd_value = $_SESSION['timecard']['break_end_ymd'] ?? '';
$break_end_time_value = $_SESSION['timecard']['break_end_time'] ?? '';

// キャストリストを取得
try {
    $pdo = connect();
    $casts = cast_get_all($pdo, 0);
} catch (PDOException $e) {
    $error['db'] = "キャストリストの取得に失敗しました。";
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
    <title>タイムカード修正</title>
    <link href="https://unpkg.com/sanitize.css" rel="stylesheet"/>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .control-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .error {
            color: red;
            font-size: 0.9em;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="content">
        <form action="" method="POST">
            <h1>タイムカード修正</h1>
            <?php if (!empty($error)): ?>
                <div class="error-message">
                    <?php foreach ($error as $msg): ?>
                        <p><?= htmlspecialchars($msg, ENT_QUOTES) ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <br>
            
            <label for="cast_id">キャスト</label>
            <select name="cast_id" id="cast_id" disabled>
                <?php foreach ($casts as $row): ?>
                    <option value="<?= htmlspecialchars($row["cast_id"]) ?>" <?= ($row["cast_id"] == $cast_id_selected) ? 'selected' : ''; ?>>
                        <?= htmlspecialchars($row["cast_name"], ENT_QUOTES); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <input type="hidden" name="cast_id" value="<?= htmlspecialchars($cast_id_selected, ENT_QUOTES); ?>">
            
            <br>
            
            <div class="control control-group">
                <label for="eigyo_ymd">営業年月日</label>
                <input type="date" name="eigyo_ymd" id="eigyo_ymd" value="<?= htmlspecialchars($eigyo_ymd_default, ENT_QUOTES); ?>" disabled>
                <input type="hidden" name="eigyo_ymd" value="<?= htmlspecialchars($eigyo_ymd_default, ENT_QUOTES); ?>">
            </div>
            <?php if (isset($error['eigyo_ymd'])) echo '<div class="error">' . htmlspecialchars($error['eigyo_ymd'], ENT_QUOTES) . '</div>'; ?>
            <?php if (isset($error['work_time'])) echo '<div class="error">' . htmlspecialchars($error['work_time'], ENT_QUOTES) . '</div>'; ?>

            <div class="control">
                <label for="in_ymd">出勤年月日</label>
                <input type="date" name="in_ymd" id="in_ymd" value="<?= htmlspecialchars($in_ymd_value, ENT_QUOTES); ?>">
                <label for="in_time">出勤時間</label>
                <input id="in_time" type="time" name="in_time" value="<?= htmlspecialchars($in_time_value, ENT_QUOTES); ?>">
            </div>

            <div class="control">
                <label for="out_ymd">退勤年月日</label>
                <input type="date" name="out_ymd" id="out_ymd" value="<?= htmlspecialchars($out_ymd_value, ENT_QUOTES); ?>">
                <label for="out_time">退勤時間</label>
                <input id="out_time" type="time" name="out_time" value="<?= htmlspecialchars($out_time_value, ENT_QUOTES); ?>">
            </div>
            <?php if (isset($error['time_order'])) echo '<div class="error">' . htmlspecialchars($error['time_order'], ENT_QUOTES) . '</div>'; ?>
            
            <div class="control">
                <label for="break_start_ymd">休憩開始年月日</label>
                <input type="date" name="break_start_ymd" id="break_start_ymd" value="<?= htmlspecialchars($break_start_ymd_value, ENT_QUOTES); ?>">
                <label for="break_start_time">休憩開始時間</label>
                <input type="time" id="break_start_time" name="break_start_time" value="<?= htmlspecialchars($break_start_time_value, ENT_QUOTES); ?>">
            </div>
            <div class="control">
                <label for="break_end_ymd">休憩終了年月日</label>
                <input type="date" name="break_end_ymd" id="break_end_ymd" value="<?= htmlspecialchars($break_end_ymd_value, ENT_QUOTES); ?>">
                <label for="break_end_time">休憩終了時間</label>
                <input type="time" id="break_end_time" name="break_end_time" value="<?= htmlspecialchars($break_end_time_value, ENT_QUOTES); ?>">
            </div>
            <?php if (isset($error['break_time'])) echo '<div class="error">' . htmlspecialchars($error['break_time'], ENT_QUOTES) . '</div>'; ?>
            
            <div class="control">
                <button type="submit" class="btn">修正する</button>
                <a href="timecard_list.php">一覧に戻る</a>
            </div>
        </form>
    </div>
</body>
</html>
