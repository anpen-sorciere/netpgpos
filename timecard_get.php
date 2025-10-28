<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once('../common/dbconnect.php');
require_once('../common/functions.php');

header('Content-Type: application/json; charset=UTF-8');

$cast_id = $_GET['cast_id'] ?? null;
$eigyo_ymd = $_GET['eigyo_ymd'] ?? null;
$shop_mst = $_GET['shop_id'] ?? null;

$response = [
    'exists' => false,
    'in_ymd' => '',
    'in_time' => '',
    'out_ymd' => '',
    'out_time' => '',
    'break_start_ymd' => '',
    'break_start_time' => '',
    'break_end_ymd' => '',
    'break_end_time' => ''
];

if (empty($cast_id) || empty($eigyo_ymd) || empty($shop_mst)) {
    echo json_encode($response);
    exit;
}

try {
    $pdo = connect();
    $statement = $pdo->prepare("SELECT * FROM timecard_tbl WHERE cast_id = ? AND shop_id = ? AND eigyo_ymd = ?");
    $statement->execute([$cast_id, $shop_mst, str_replace('-', '', $eigyo_ymd)]);
    $timecard_data = $statement->fetch(PDO::FETCH_ASSOC);

    if ($timecard_data) {
        $response['exists'] = true;
        $response['in_ymd'] = format_ymd($timecard_data['in_ymd']);
        $response['in_time'] = format_time($timecard_data['in_time']);
        $response['out_ymd'] = format_ymd($timecard_data['out_ymd']);
        $response['out_time'] = format_time($timecard_data['out_time']);
        $response['break_start_ymd'] = format_ymd($timecard_data['break_start_ymd']);
        $response['break_start_time'] = format_time($timecard_data['break_start_time']);
        $response['break_end_ymd'] = format_ymd($timecard_data['break_end_ymd']);
        $response['break_end_time'] = format_time($timecard_data['break_end_time']);
    }
} catch (PDOException $e) {
    // �G���[��JSON�ŕԂ����A���O�ɋL�^
    error_log("Database Error in timecard_get.php: " . $e->getMessage());
} finally {
    disconnect($pdo);
}

echo json_encode($response);
exit;
