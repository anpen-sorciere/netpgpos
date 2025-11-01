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

// HTTP_REFERERが存在し、かつURLがindex.phpで終わる場合にクリアする
// または、POSTリクエストでない（初回アクセスやリロード）場合にクリア
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], 'index.php') !== false) {
    unset($_SESSION['timecard']);
    unset($_SESSION['join']);
}

$error = [];
$focus_id = '';
$pdo = null;

// 店舗情報と店舗IDを取得
$shop_info = get_shop_info($utype);
$shop_mst = $shop_info['id'];

// フォームの入力値をセッションから取得、または初期値を設定
// POSTデータがあればPOSTを優先
$cast_id_selected = $_POST['cast_id'] ?? ($_SESSION['timecard']['cast_id'] ?? ($_SESSION['join']['cast_id'] ?? null));
$eigyo_ymd_default = $_POST['eigyo_ymd'] ?? ($_SESSION['timecard']['eigyo_ymd'] ?? ($_SESSION['join']['eigyo_ymd'] ?? date('Y-m-d')));
$in_ymd_value = $_POST['in_ymd'] ?? ($_SESSION['timecard']['in_ymd'] ?? $eigyo_ymd_default);
$in_time_value = $_POST['in_time'] ?? ($_SESSION['timecard']['in_time'] ?? '');
$out_ymd_value = $_POST['out_ymd'] ?? ($_SESSION['timecard']['out_ymd'] ?? $eigyo_ymd_default);
$out_time_value = $_POST['out_time'] ?? ($_SESSION['timecard']['out_time'] ?? '');
$break_start_ymd_value = $_POST['break_start_ymd'] ?? ($_SESSION['timecard']['break_start_ymd'] ?? '');
$break_start_time_value = $_POST['break_start_time'] ?? ($_SESSION['timecard']['break_start_time'] ?? '');
$break_end_ymd_value = $_POST['break_end_ymd'] ?? ($_SESSION['timecard']['break_end_ymd'] ?? '');
$break_end_time_value = $_POST['break_end_time'] ?? ($_SESSION['timecard']['break_end_time'] ?? '');

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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // フォーム送信時の処理
    try {
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

        // バリデーションチェック
        if (empty($eigyo_ymd)) {
            $error['eigyo_ymd'] = '営業年月日は必須です。';
            $focus_id = 'eigyo_ymd';
        }
        
        if (empty($cast_id)) {
            $error['cast_id'] = 'キャストは必須です。';
            if (empty($focus_id)) $focus_id = 'cast_id';
        }

        if ((empty($in_ymd) && empty($in_time)) && (empty($out_ymd) && empty($out_time))) {
            $error['work_time'] = '出勤または退勤の年月と時間はどちらか一方を必須です。';
            if (empty($focus_id)) $focus_id = 'in_ymd';
        }

        // 休憩開始・終了が入力されているかを個別にチェック
        $has_break_start_ymd = !empty($break_start_ymd);
        $has_break_start_time = !empty($break_start_time);
        $has_break_end_ymd = !empty($break_end_ymd);
        $has_break_end_time = !empty($break_end_time);

        // 休憩開始日時チェック: 年月日か時間どちらか片方だけ入力されている場合はエラー
        if ($has_break_start_ymd xor $has_break_start_time) {
            $error['break_time_start'] = '休憩開始年月日と時間の両方を入力してください。';
            if (empty($focus_id)) $focus_id = $has_break_start_ymd ? 'break_start_time' : 'break_start_ymd';
        }

        // 休憩終了日時チェック: 年月日か時間どちらか片方だけ入力されている場合はエラー
        if ($has_break_end_ymd xor $has_break_end_time) {
            $error['break_time_end'] = '休憩終了年月日と時間の両方を入力してください。';
            if (empty($focus_id)) $focus_id = $has_break_end_ymd ? 'break_end_time' : 'break_end_ymd';
        }

        // 休憩終了が入力されているのに、開始が入力されていない場合はエラー
        if (!isset($error['break_time_end']) && ($has_break_end_ymd && $has_break_end_time) && !($has_break_start_ymd && $has_break_start_time)) {
            $error['break_time_missing_start'] = '休憩終了を入力する場合は、開始も入力してください。';
            if (empty($focus_id)) $focus_id = 'break_start_ymd';
        }

        // 出勤・退勤日時の順序チェック
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
                        if (empty($focus_id)) $focus_id = 'out_ymd';
                    }
                }
            } catch (Exception $e) {
                 $error['time_order'] = '日付または時間のフォーマットが不正です。';
                 if (empty($focus_id)) $focus_id = 'in_ymd';
            }
        }

        if(empty($error)){
            // キャストIDからキャスト名を取得
            $cast_name = null;
            foreach ($casts as $cast) {
                if ($cast['cast_id'] == $cast_id) {
                    $cast_name = $cast['cast_name'];
                    break;
                }
            }
            
            // 入力値をセッションに保存
            $_SESSION['timecard'] = $_POST;
            $_SESSION['timecard']['cast_name'] = $cast_name;

            // データの存在チェック
            $statement = $pdo->prepare("SELECT count(*) as cnt FROM timecard_tbl WHERE cast_id = ? AND shop_id = ? AND eigyo_ymd = ?");
            $statement->execute(array(
                intval($cast_id),
                intval($shop_mst),
                str_replace('-', '', $eigyo_ymd)
            ));
            $count = $statement->fetch(PDO::FETCH_ASSOC)['cnt'];

            // 日付と時刻のフォーマットを'YYYYMMDD'と'HHMM'に変換
            $eigyo_ymd_formatted = str_replace('-', '', $eigyo_ymd);
            $in_ymd_formatted = !empty($in_ymd) ? str_replace('-', '', $in_ymd) : '';
            $in_time_formatted = !empty($in_time) ? str_replace(':', '', $in_time) : '';
            $out_ymd_formatted = !empty($out_ymd) ? str_replace('-', '', $out_ymd) : '';
            $out_time_formatted = !empty($out_time) ? str_replace(':', '', $out_time) : '';
            $break_start_ymd_formatted = !empty($break_start_ymd) ? str_replace('-', '', $break_start_ymd) : '';
            $break_start_time_formatted = !empty($break_start_time) ? str_replace(':', '', $break_start_time) : '';
            $break_end_ymd_formatted = !empty($break_end_ymd) ? str_replace('-', '', $break_end_ymd) : '';
            $break_end_time_formatted = !empty($break_end_time) ? str_replace(':', '', $break_end_time) : '';
            
            if ($count == 0) {
                // 新規挿入
                $statement = $pdo->prepare("INSERT INTO timecard_tbl (cast_id, shop_id, eigyo_ymd, in_ymd, in_time, out_ymd, out_time, break_start_ymd, break_start_time, break_end_ymd, break_end_time) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $statement->execute(array(
                    intval($cast_id),
                    intval($shop_mst),
                    $eigyo_ymd_formatted,
                    $in_ymd_formatted,
                    $in_time_formatted,
                    $out_ymd_formatted,
                    $out_time_formatted,
                    $break_start_ymd_formatted,
                    $break_start_time_formatted,
                    $break_end_ymd_formatted,
                    $break_end_time_formatted
                ));
            } else {
                // 更新
                $statement = $pdo->prepare("UPDATE timecard_tbl SET in_ymd=?, in_time=?, out_ymd=?, out_time=?, break_start_ymd=?, break_start_time=?, break_end_ymd=?, break_end_time=? WHERE cast_id=? AND shop_id=? AND eigyo_ymd=?");
                $statement->execute(array(
                    $in_ymd_formatted,
                    $in_time_formatted,
                    $out_ymd_formatted,
                    $out_time_formatted,
                    $break_start_ymd_formatted,
                    $break_start_time_formatted,
                    $break_end_ymd_formatted,
                    $break_end_time_formatted,
                    intval($cast_id),
                    intval($shop_mst),
                    $eigyo_ymd_formatted
                ));
            }
            
            disconnect($pdo);
            header('Location: timecard_result.php');
            exit();
        }
    } catch (PDOException $e) {
        $error['db'] = "データベースエラー: " . $e->getMessage();
        error_log("Database Error: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0,minimum-scale=1.0">
    <title>タイムカード入力</title>
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
            flex-direction: column;
        }
        .container {
            width: 100%;
            max-width: 800px;
            margin: 20px auto;
            background-color: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            padding: 30px;
        }
        h1 {
            color: #3498db;
            text-align: center;
            margin-bottom: 20px;
        }
        .form-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .form-table th, .form-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        .form-table th {
            width: 20%;
            background-color: #f8f8f8;
            font-weight: bold;
        }
        .form-table td input[type="date"],
        .form-table td input[type="time"],
        .form-table td select {
            width: 90%;
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 4px;
            transition: border-color 0.3s;
        }
        .form-table td input[type="time"] {
            width: 120px;
        }
        .form-table td input:focus,
        .form-table td select:focus {
            outline: none;
            border-color: #3498db;
        }
        /* 必須表示のコンテナ */
        .required-label-container {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .required-label {
            color: #e74c3c;
            font-size: 0.9em;
            white-space: nowrap;
        }
        .control-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .control-buttons {
            display: flex;
            justify-content: flex-end;
            gap: 15px;
            margin-top: 20px;
        }
        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 30px;
            font-size: 1em;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .btn-primary {
            background-color: #3498db;
            color: #fff;
            box-shadow: 0 4px 15px rgba(52, 152, 219, 0.4);
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(52, 152, 219, 0.6);
        }
        .btn-secondary {
            background-color: #ecf0f1;
            color: #333;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        .btn-secondary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.2);
        }
        .error {
            color: #e74c3c;
            font-size: 0.9em;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>タイムカード入力</h1>
        <form action="" method="POST">
            <?php if (!empty($error)): ?>
                <div class="error-message">
                    <?php foreach ($error as $msg): ?>
                        <p><?= htmlspecialchars($msg, ENT_QUOTES) ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <table class="form-table">
                <tbody>
                    <tr>
                        <th><label for="cast_id">キャスト</label></th>
                        <td>
                            <select name="cast_id" id="cast_id">
                                <option value="">キャストを選択してください</option>
                                <?php foreach ($casts as $row): ?>
                                    <option value="<?= htmlspecialchars($row["cast_id"]) ?>" <?= ($row["cast_id"] == $cast_id_selected) ? 'selected' : ''; ?>>
                                        <?= htmlspecialchars($row["cast_name"], ENT_QUOTES); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($error['cast_id'])) echo '<div class="error">' . htmlspecialchars($error['cast_id'], ENT_QUOTES) . '</div>'; ?>
                        </td>
                        <th>
                            <div class="required-label-container">
                                <label for="eigyo_ymd">営業年月日</label>
                                <span class="required-label">（必須）</span>
                            </div>
                        </th>
                        <td>
                            <div class="control-group">
                                <input type="date" name="eigyo_ymd" id="eigyo_ymd" value="<?= htmlspecialchars($eigyo_ymd_default, ENT_QUOTES); ?>">
                                <button type="button" id="sync_dates_btn" class="btn btn-secondary">営業年月日に統一</button>
                            </div>
                            <?php if (isset($error['eigyo_ymd'])) echo '<div class="error">' . htmlspecialchars($error['eigyo_ymd'], ENT_QUOTES) . '</div>'; ?>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="in_ymd">出勤年月日</label></th>
                        <td><input type="date" name="in_ymd" id="in_ymd" value="<?= htmlspecialchars($in_ymd_value, ENT_QUOTES); ?>"></td>
                        <th><label for="in_time">出勤時間</label></th>
                        <td><input id="in_time" type="time" name="in_time" value="<?= htmlspecialchars($in_time_value, ENT_QUOTES); ?>"></td>
                    </tr>
                    <tr>
                        <th><label for="out_ymd">退勤年月日</label></th>
                        <td><input type="date" name="out_ymd" id="out_ymd" value="<?= htmlspecialchars($out_ymd_value, ENT_QUOTES); ?>"></td>
                        <th><label for="out_time">退勤時間</label></th>
                        <td><input id="out_time" type="time" name="out_time" value="<?= htmlspecialchars($out_time_value, ENT_QUOTES); ?>"></td>
                    </tr>
                    <tr>
                        <th><label for="break_start_ymd">休憩開始年月日</label></th>
                        <td><input type="date" name="break_start_ymd" id="break_start_ymd" value="<?= htmlspecialchars($break_start_ymd_value, ENT_QUOTES); ?>"></td>
                        <th><label for="break_start_time">休憩開始時間</label></th>
                        <td><input type="time" id="break_start_time" name="break_start_time" value="<?= htmlspecialchars($break_start_time_value, ENT_QUOTES); ?>"></td>
                    </tr>
                    <tr>
                        <th><label for="break_end_ymd">休憩終了年月日</label></th>
                        <td><input type="date" name="break_end_ymd" id="break_end_ymd" value="<?= htmlspecialchars($break_end_ymd_value, ENT_QUOTES); ?>"></td>
                        <th><label for="break_end_time">休憩終了時間</label></th>
                        <td><input type="time" id="break_end_time" name="break_end_time" value="<?= htmlspecialchars($break_end_time_value, ENT_QUOTES); ?>"></td>
                    </tr>
                </tbody>
            </table>

            <?php if (isset($error['work_time'])) echo '<div class="error">' . htmlspecialchars($error['work_time'], ENT_QUOTES) . '</div>'; ?>
            <?php if (isset($error['time_order'])) echo '<div class="error">' . htmlspecialchars($error['time_order'], ENT_QUOTES) . '</div>'; ?>
            <?php if (isset($error['break_time_start'])) echo '<div class="error">' . htmlspecialchars($error['break_time_start'], ENT_QUOTES) . '</div>'; ?>
            <?php if (isset($error['break_time_end'])) echo '<div class="error">' . htmlspecialchars($error['break_time_end'], ENT_QUOTES) . '</div>'; ?>
            <?php if (isset($error['break_time_missing_start'])) echo '<div class="error">' . htmlspecialchars($error['break_time_missing_start'], ENT_QUOTES) . '</div>'; ?>
            
            <div class="control-buttons">
                <button type="submit" class="btn btn-primary">確認する</button>
                <input value="メニューへ" onclick="location.href='index.php'" type="button" class="btn btn-secondary">
            </div>
        </form>
    </div>
    
    <script>
        document.getElementById('sync_dates_btn').addEventListener('click', function() {
            const eigyoDate = document.getElementById('eigyo_ymd').value;
            
            if (eigyoDate) {
                document.getElementById('in_ymd').value = eigyoDate;
                document.getElementById('out_ymd').value = eigyoDate;
                document.getElementById('break_start_ymd').value = eigyoDate;
                document.getElementById('break_end_ymd').value = eigyoDate;
            } else {
                alert('営業年月日が選択されていません。');
            }
        });

        const castSelect = document.getElementById('cast_id');
        const eigyoDateInput = document.getElementById('eigyo_ymd');

        const fetchTimecardData = async () => {
            const castId = castSelect.value;
            const eigyoYmd = eigyoDateInput.value;
            const shopId = '<?php echo htmlspecialchars($shop_mst, ENT_QUOTES); ?>';
            
            // 入力値保持のため、POSTの場合はデータ取得をスキップ
            if ('<?php echo $_SERVER['REQUEST_METHOD']; ?>' === 'POST') {
                return;
            }

            if (castId && eigyoYmd) {
                const url = `timecard_get.php?cast_id=${castId}&eigyo_ymd=${eigyoYmd}&shop_id=${shopId}`;
                console.log('Fetching timecard data from:', url);
                try {
                    const response = await fetch(url);
                    const data = await response.json();
                    console.log('Received data:', data);
                    
                    if (data.exists) {
                        console.log('Data exists, updating form fields');
                        document.getElementById('in_ymd').value = data.in_ymd || '';
                        document.getElementById('in_time').value = data.in_time || '';
                        document.getElementById('out_ymd').value = data.out_ymd || '';
                        document.getElementById('out_time').value = data.out_time || '';
                        document.getElementById('break_start_ymd').value = data.break_start_ymd || '';
                        document.getElementById('break_start_time').value = data.break_start_time || '';
                        document.getElementById('break_end_ymd').value = data.break_end_ymd || '';
                        document.getElementById('break_end_time').value = data.break_end_time || '';
                    } else {
                        console.log('No data exists, clearing form fields');
                        document.getElementById('in_ymd').value = eigyoYmd;
                        document.getElementById('in_time').value = '';
                        document.getElementById('out_ymd').value = eigyoYmd;
                        document.getElementById('out_time').value = '';
                        document.getElementById('break_start_ymd').value = '';
                        document.getElementById('break_start_time').value = '';
                        document.getElementById('break_end_ymd').value = '';
                        document.getElementById('break_end_time').value = '';
                    }
                } catch (error) {
                    console.error('データの取得に失敗しました:', error);
                }
            }
        };

        castSelect.addEventListener('change', fetchTimecardData);
        eigyoDateInput.addEventListener('change', fetchTimecardData);

        document.addEventListener('DOMContentLoaded', () => {
            // エラーがある場合、該当要素にフォーカスを当てる
            const focusId = '<?php echo htmlspecialchars($focus_id, ENT_QUOTES); ?>';
            if (focusId) {
                document.getElementById(focusId).focus();
            } else if (castSelect.value === '') {
                // 初回アクセス時、キャスト選択にフォーカスを当てる
                castSelect.focus();
            } else {
                // キャストと日付が既に設定されている場合はデータを取得
                if (castSelect.value && eigyoDateInput.value) {
                    fetchTimecardData();
                }
            }
        });
    </script>
</body>
</html>
