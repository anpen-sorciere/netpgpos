<?php
/**
 * キャスト対応完了 - テストモード
 * 
 * ?test=1 パラメータでドライラン実行
 * BASE APIには送信せず、動作確認のみ
 */
session_start();
require_once __DIR__ . '/../../../common/config.php';

header('Content-Type: application/json; charset=utf-8');

$test_mode = isset($_GET['test']) && $_GET['test'] === '1';

try {
    if (!isset($_SESSION['cast_id'])) {
        throw new Exception('ログインが必要です');
    }
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('POSTメソッドのみサポート');
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        throw new Exception('リクエストデータが無効');
    }
    
    $order_id = $input['order_id'] ?? null;
    $template_id = $input['template_id'] ?? null;
    
    if (!$order_id || !$template_id) {
        throw new Exception('order_idとtemplate_idは必須');
    }
    
    // DB接続
    $pdo = new PDO(
        "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
        $user,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    // 定型文取得
    $stmt = $pdo->prepare("
        SELECT * FROM reply_message_templates 
        WHERE id = ? AND is_active = 1 AND allow_cast_use = 1
    ");
    $stmt->execute([$template_id]);
    $template = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$template) {
        throw new Exception('指定された定型文が見つからないか、使用できません');
    }
    
    // 注文情報取得
    $stmt = $pdo->prepare("
        SELECT o.*, oi.product_name, oi.customer_name_from_option
        FROM base_orders o
        INNER JOIN base_order_items oi ON o.base_order_id = oi.base_order_id
        WHERE o.base_order_id = ? AND oi.cast_id = ?
        LIMIT 1
    ");
    $stmt->execute([$order_id, $_SESSION['cast_id']]);
    $order_info = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order_info) {
        throw new Exception('この注文への権限がありません');
    }
    
    // 変数置換
    $message = $template['template_body'];
    $message = str_replace('{customer_name}', $order_info['customer_name_from_option'] ?: $order_info['customer_name'], $message);
    $message = str_replace('{product_name}', $order_info['product_name'], $message);
    $message = str_replace('{order_id}', $order_id, $message);
    $message = str_replace('{cast_name}', $_SESSION['cast_name'], $message);
    
    if ($test_mode) {
        // テストモード: BASE APIを叩かない
        echo json_encode([
            'success' => true,
            'test_mode' => true,
            'message' => '✅ テストモード: BASE APIは実行されません',
            'order_id' => $order_id,
            'template_name' => $template['template_name'],
            'reply_message' => $message,
            'would_send_to_base' => [
                'unique_key' => $order_id,
                'dispatch_status' => 'dispatched',
                'message' => $message
            ]
        ]);
        exit;
    }
    
    // 本番モード: BASE API実行
    require_once __DIR__ . '/../classes/base_practical_auto_manager.php';
    $manager = new BasePracticalAutoManager();
    
    $auth_status = $manager->getAuthStatus();
    if (!isset($auth_status['write_orders']['authenticated']) || !$auth_status['write_orders']['authenticated']) {
        throw new Exception('BASE API認証が必要です');
    }
    
    $update_data = [
        'unique_key' => $order_id,
        'dispatch_status' => 'dispatched',
        'message' => $message
    ];
    
    $response = $manager->makeApiRequest('write_orders', '/1/orders/edit_status', $update_data, 'POST');
    
    // 履歴記録
    $stmt = $pdo->prepare("
        INSERT INTO cast_order_completions 
        (base_order_id, cast_id, completed_at, template_id, template_name, reply_message, base_status_after, success)
        VALUES (?, ?, NOW(), ?, ?, ?, 'dispatched', TRUE)
    ");
    $stmt->execute([
        $order_id,
        $_SESSION['cast_id'],
        $template_id,
        $template['template_name'],
        $message
    ]);
    
    // ローカルDB更新
    $stmt = $pdo->prepare("
        UPDATE base_orders 
        SET status = 'shipping', updated_at = NOW() 
        WHERE base_order_id = ?
    ");
    $stmt->execute([$order_id]);
    
    echo json_encode([
        'success' => true,
        'test_mode' => false,
        'message' => '対応完了しました',
        'order_id' => $order_id,
        'template_name' => $template['template_name'],
        'reply_message' => $message
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'test_mode' => $test_mode,
        'error' => $e->getMessage()
    ]);
}
?>
