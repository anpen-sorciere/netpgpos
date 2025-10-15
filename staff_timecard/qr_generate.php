<?php
// QR打刻API（出勤/退勤登録）
require_once('../dbconnect.php');
require_once('../functions.php');
header('Content-Type: application/json; charset=UTF-8');

$input = json_decode(file_get_contents('php://input'), true);
$staff_id = $input['staff_id'] ?? null;
$qr = $input['qr'] ?? null;
if (!$staff_id || !$qr) {
    echo json_encode(['status'=>'error','message'=>'スタッフIDまたはQR内容がありません']);
    exit;
}
// 仮：QR内容から出勤/退勤判定（例：qrにaction:in/out, timestamp, shop_id等を含む）
// ここでは単純にaction=inなら出勤、outなら退勤とする
$pdo = connect();
$action = null;
if (strpos($qr, 'action=in') !== false) $action = 'in';
if (strpos($qr, 'action=out') !== false) $action = 'out';
if (!$action) {
    echo json_encode(['status'=>'error','message'=>'QR内容が不正です']);
    disconnect($pdo);
    exit;
}
$now = date('Y-m-d H:i:s');
if ($action === 'in') {
    // 出勤登録（例：timecard_tblにinsert）
    $stmt = $pdo->prepare('INSERT INTO timecard_tbl (cast_id, in_ymd, in_time) VALUES (?, ?, ?)');
    $stmt->execute([$staff_id, date('Ymd'), date('Hi')]);
    echo json_encode(['status'=>'ok','message'=>'出勤を登録しました']);
} else {
    // 退勤登録（例：timecard_tblのout_ymd, out_timeをupdate）
    $stmt = $pdo->prepare('UPDATE timecard_tbl SET out_ymd=?, out_time=? WHERE cast_id=? AND out_ymd IS NULL');
    $stmt->execute([date('Ymd'), date('Hi'), $staff_id]);
    echo json_encode(['status'=>'ok','message'=>'退勤を登録しました']);
}
disconnect($pdo);
