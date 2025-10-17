<?php
// PHPエラーレポートを有効にする
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// dbconnect.phpとfunctions.phpのパス
// Windowsの場合、パス区切り文字は「\」
define('DS', DIRECTORY_SEPARATOR);

// データベース接続切断
function disconnect($pdo)
{
    $pdo = null;
}

// 店舗情報取得
function get_shop_info($identifier)
{
    $shops = [
        1 => ['id' => 1, 'name' => 'ソルシエール', 'utype' => 1024],
        2 => ['id' => 2, 'name' => 'レーヴェス', 'utype' => 2],
        3 => ['id' => 3, 'name' => 'コレクト', 'utype' => 3]
    ];

    foreach ($shops as $shop) {
        if (is_numeric($identifier)) {
            // 数値の場合はIDまたはutypeで比較
            if ($shop['id'] == $identifier || $shop['utype'] == $identifier) {
                return $shop;
            }
        } else {
            // 文字列の場合は名称で比較
            if ($shop['name'] == $identifier) {
                return $shop;
            }
        }
    }

    return ['name' => '不明', 'id' => null, 'utype' => null];
}

// 支払い方法データ取得（単一）
function payment_data_get($pdo, $payment_type)
{
    $stmh = $pdo->prepare("SELECT payment_name FROM payment_mst WHERE payment_type = ? LIMIT 1");
    $stmh->execute([$payment_type]);
    return $stmh->fetch(PDO::FETCH_ASSOC);
}

// 支払い方法データ取得（全件）
function payment_get_all($pdo)
{
    $stmh = $pdo->prepare("SELECT * FROM payment_mst ORDER BY payment_type");
    $stmh->execute();
    return $stmh->fetchAll(PDO::FETCH_ASSOC);
}

// 商品データ取得（単一）
function item_get($pdo, $item_id)
{
    $stmh = $pdo->prepare("SELECT * FROM item_mst WHERE item_id = ? LIMIT 1");
    $stmh->execute([$item_id]);
    return $stmh->fetch(PDO::FETCH_ASSOC);
}

// 商品データ取得（全件）
function item_get_all($pdo)
{
    // item_yomiカラムでソートするように修正
    $sql = "SELECT * FROM item_mst ORDER BY category, item_yomi ASC";
    $stmh = $pdo->prepare($sql);
    $stmh->execute();
    return $stmh->fetchAll(PDO::FETCH_ASSOC);
}

// カテゴリーIDに基づいて商品データを取得する関数
function item_get_category($pdo, $category_id)
{
    if (empty($category_id)) {
        return [];
    }
    $sql = "SELECT * FROM item_mst WHERE category = ? ORDER BY item_yomi ASC";
    $stmh = $pdo->prepare($sql);
    $stmh->execute([$category_id]);
    return $stmh->fetchAll(PDO::FETCH_ASSOC);
}

// キャストデータ取得（単一）
function cast_get($pdo, $cast_id)
{
    $stmh = $pdo->prepare("SELECT * FROM cast_mst WHERE cast_id = ? AND drop_flg = 0 LIMIT 1");
    $stmh->execute([$cast_id]);
    return $stmh->fetch(PDO::FETCH_ASSOC);
}

// キャストデータ取得（全件）
function cast_get_all($pdo, $include_retirees = false)
{
    // 基本のクエリ
    $sql = "SELECT * FROM cast_mst";
    
    // 退職者を含まない場合（通常の挙動）
    if (!$include_retirees) {
        $sql .= " WHERE drop_flg = 0";
    }
    
    // ソート順
    $sql .= " ORDER BY cast_type, cast_yomi, cast_id";
    
    $stmh = $pdo->prepare($sql);
    $stmh->execute();
    
    return $stmh->fetchAll(PDO::FETCH_ASSOC);
}

// 時給情報取得
function pay_get($pdo, $cast_id, $year, $month)
{
    // pay_tblのみから時給データを取得する
    $set_month = $year . str_pad($month, 2, '0', STR_PAD_LEFT);
    $stmh = $pdo->prepare("SELECT pay FROM pay_tbl WHERE cast_id = ? AND set_month = ? ORDER BY id DESC LIMIT 1");
    $stmh->execute([$cast_id, $set_month]);
    $pay_data = $stmh->fetch(PDO::FETCH_ASSOC);

    if ($pay_data) {
        return ['pay_amount' => $pay_data['pay'] ?? 0];
    } else {
        // データがない場合は0を返す
        return ['pay_amount' => 0];
    }
}

// 日付（YYYYMMDD）をY-m-d形式にフォーマット
function format_ymd($ymd) {
    if (empty($ymd) || strlen($ymd) !== 8) {
        return '';
    }
    return substr($ymd, 0, 4) . '-' . substr($ymd, 4, 2) . '-' . substr($ymd, 6, 2);
}

// 時間（HHMM）をH:i形式にフォーマット
function format_time($hm) {
    if (empty($hm) || strlen($hm) !== 4) {
        return '';
    }
    return substr($hm, 0, 2) . ':' . substr($hm, 2, 2);
}

// 日付と時間を結合してDateTimeオブジェクトを作成
function create_datetime_from_ymd_time($ymd, $time) {
    if (empty($ymd) || empty($time)) {
        return null;
    }
    try {
        $datetime_str = format_ymd($ymd) . ' ' . format_time($time);
        return new DateTime($datetime_str);
    } catch (Exception $e) {
        return null;
    }
}

// 個別の勤務時間計算
function calculate_working_hours_minutes($row)
{
    $work_minutes = 0;
    $break_minutes = 0;

    try {
        $in_datetime = create_datetime_from_ymd_time($row['in_ymd'], $row['in_time']);
        $out_datetime = create_datetime_from_ymd_time($row['out_ymd'], $row['out_time']);

        if ($in_datetime && $out_datetime) {
            // 翌日をまたぐ勤務の場合の調整
            if ($out_datetime < $in_datetime) {
                $out_datetime->modify('+1 day');
            }
            
            $work_interval = $in_datetime->diff($out_datetime);
            $work_minutes = $work_interval->i + ($work_interval->h * 60) + ($work_interval->days * 24 * 60);
        }

        // 休憩時間の計算（開始と終了の両方が入力されている場合のみ）
        $break_start_datetime = create_datetime_from_ymd_time($row['break_start_ymd'], $row['break_start_time']);
        $break_end_datetime = create_datetime_from_ymd_time($row['break_end_ymd'], $row['break_end_time']);
        
        if ($break_start_datetime && $break_end_datetime) {
            // 休憩が翌日をまたぐ場合の考慮
            if ($break_end_datetime < $break_start_datetime) {
                $break_end_datetime->modify('+1 day');
            }
            $break_interval = $break_start_datetime->diff($break_end_datetime);
            $break_minutes = $break_interval->i + ($break_interval->h * 60);
        }

    } catch (Exception $e) {
        error_log("DateTime parsing error in calculate_working_hours: " . $e->getMessage());
        return ['work_time_minutes' => 0, 'break_time_minutes' => 0];
    }
    
    // 総勤務時間から総休憩時間を引く
    $work_time_with_break_minutes = $work_minutes - $break_minutes;

    return [
        'work_time_minutes' => max(0, $work_time_with_break_minutes),
        'break_time_minutes' => $break_minutes
    ];
}

// 税率を取得
function get_tax_rate()
{
    // 将来的にデータベースから取得するように変更することも可能
    return 0.10; // 10%
}

// 分を時間と分にフォーマットする関数
function format_minutes_to_hours_minutes($minutes)
{
    if ($minutes < 0) {
        return '00:00';
    }
    $hours = floor($minutes / 60);
    $minutes = $minutes % 60;
    return sprintf('%02d:%02d', $hours, $minutes);
}

// HTMLエスケープ処理
function h($str)
{
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}
