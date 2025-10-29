<?php
/**
 * 注文ステータス更新API
 * BASE APIのwrite_ordersスコープを使用して注文ステータスを更新
 */
session_start();
require_once __DIR__ . '/../../common/config.php';
require_once __DIR__ . '/../../common/dbconnect.php';
require_once __DIR__ . '/../../common/functions.php';
require_once __DIR__ . '/base_practical_auto_manager.php';

header('Content-Type: application/json');

try {
    // リクエストメソッドの確認
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('POSTメソッドのみサポートされています');
    }
    
    // リクエストデータの取得
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('リクエストデータが無効です');
    }
    
    $unique_key = $input['unique_key'] ?? null;
    $status = $input['status'] ?? null;
    $message = $input['message'] ?? '';
    $video_url = $input['video_url'] ?? '';
    
    if (!$unique_key || !$status) {
        throw new Exception('unique_keyとstatusは必須です');
    }
    
    // 認証状態の確認
    $practical_manager = new BasePracticalAutoManager();
    $auth_status = $practical_manager->getAuthStatus();
    
    if (!isset($auth_status['write_orders_only']['authenticated']) || !$auth_status['write_orders_only']['authenticated']) {
        throw new Exception('write_orders_onlyスコープの認証が必要です');
    }
    
    // BASE APIで注文ステータスを更新
    $result = updateOrderStatus($practical_manager, $unique_key, $status, $message, $video_url);
    
    echo json_encode([
        'success' => true,
        'message' => '注文ステータスを更新しました',
        'data' => $result
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

/**
 * BASE APIで注文ステータスを更新
 */
function updateOrderStatus($manager, $unique_key, $status, $message, $video_url) {
    global $base_api_url;
    
    // ステータスマッピング
    $status_map = [
        'dispatched' => 'dispatched',  // 対応済み
        'cancelled' => 'cancelled',    // キャンセル
        'unpaid' => 'unpaid',          // 入金待ち
        'ordered' => 'ordered',        // 未対応
        'unshippable' => 'unshippable' // 対応開始前
    ];
    
    if (!isset($status_map[$status])) {
        throw new Exception('無効なステータスです: ' . $status);
    }
    
    $api_status = $status_map[$status];
    
    // 更新データの準備
    $update_data = [
        'unique_key' => $unique_key,
        'dispatch_status' => $api_status
    ];
    
    // メッセージがある場合は追加
    if (!empty($message)) {
        $update_data['message'] = $message;
    }
    
    // 動画URLがある場合は追加
    if (!empty($video_url)) {
        $update_data['video_url'] = $video_url;
    }
    
    // BASE APIにリクエスト送信
    $url = $base_api_url . '/1/orders/edit_status';
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($update_data),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/x-www-form-urlencoded'
        ]
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);
    
    if ($curl_error) {
        throw new Exception("ネットワークエラー: {$curl_error}");
    }
    
    if ($http_code !== 200) {
        $error_data = json_decode($response, true);
        $error_msg = $error_data['error_description'] ?? "HTTP {$http_code}";
        throw new Exception("APIリクエストエラー: HTTP {$http_code} - {$error_msg}");
    }
    
    $response_data = json_decode($response, true);
    
    return [
        'unique_key' => $unique_key,
        'status' => $api_status,
        'message' => $message,
        'video_url' => $video_url,
        'updated_at' => time()
    ];
}
?>
