<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require("./dbconnect.php");
session_start();

// timecard_input.phpから渡されたセッションデータを取得
$timecard_data = $_SESSION['timecard'] ?? [];
$utype = $_SESSION['utype'] ?? null;

// セッションからデータが取得できなかった場合の処理
if (empty($timecard_data)) {
    // データがない場合は、入力画面に戻す
    header('Location: timecard_input.php');
    exit();
}

/**
 * 日付と時間を表示用にフォーマットするヘルパー関数
 *
 * @param string $ymd_key 日付のキー名
 * @param string $time_key 時間のキー名
 * @param array $data セッションデータ
 * @return string フォーマットされた日時文字列、または「未入力」
 */
function format_datetime($ymd_key, $time_key, $data) {
    $ymd = $data[$ymd_key] ?? null;
    $time = $data[$time_key] ?? null;

    if ($ymd && $time) {
        try {
            $date = new DateTime($ymd);
            $formatted_ymd = $date->format('Y年m月d日');
            // explodeの前に$timeが文字列であることを確認し、":"が含まれているかチェック
            if (is_string($time) && strpos($time, ':') !== false) {
                $time_parts = explode(':', $time);
                $formatted_time = sprintf('%02d時%02d分', $time_parts[0], $time_parts[1]);
                return $formatted_ymd . ' ' . $formatted_time;
            }
        } catch (Exception $e) {
            error_log("Date formatting error: " . $e->getMessage());
            return 'フォーマットエラー';
        }
    }
    return '未入力';
}

$in_datetime = format_datetime('in_ymd', 'in_time', $timecard_data);
$out_datetime = format_datetime('out_ymd', 'out_time', $timecard_data);
$break_start_datetime = format_datetime('break_start_ymd', 'break_start_time', $timecard_data);
$break_end_datetime = format_datetime('break_end_ymd', 'break_end_time', $timecard_data);
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0,minimum-scale=1.0">
    <title>タイムカード確認画面</title>
    <link href="https://use.fontawesome.com/releases/v5.6.1/css/all.css" rel="stylesheet">
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
        .result-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .result-table th, .result-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        .result-table th {
            width: 25%;
            background-color: #f8f8f8;
            font-weight: bold;
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
    </style>
</head>
<body>
    <div class="container">
        <h1>タイムカード確認画面</h1>
        <table class="result-table">
            <tbody>
                <tr>
                    <th>キャスト</th>
                    <td><?= htmlspecialchars($timecard_data['cast_name'] ?? '未入力', ENT_QUOTES) ?></td>
                </tr>
                <tr>
                    <th>営業年月日</th>
                    <td><?= htmlspecialchars($timecard_data['eigyo_ymd'] ?? '未入力', ENT_QUOTES) ?></td>
                </tr>
                <tr>
                    <th>出勤時間</th>
                    <td><?= htmlspecialchars($in_datetime, ENT_QUOTES) ?></td>
                </tr>
                <tr>
                    <th>退勤時間</th>
                    <td><?= htmlspecialchars($out_datetime, ENT_QUOTES) ?></td>
                </tr>
                <tr>
                    <th>休憩開始時間</th>
                    <td><?= htmlspecialchars($break_start_datetime, ENT_QUOTES) ?></td>
                </tr>
                <tr>
                    <th>休憩終了時間</th>
                    <td><?= htmlspecialchars($break_end_datetime, ENT_QUOTES) ?></td>
                </tr>
            </tbody>
        </table>
        <div class="control-buttons">
            <a href="timecard_input.php" class="btn btn-secondary">戻る</a>
            <a href="index.php" class="btn btn-secondary">メニューへ</a>
        </div>
    </div>
</body>
</html>