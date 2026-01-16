<?php
/**
 * キャスト対応完了API
 * キャストが注文を「対応済み」にしてBASEステータスを更新
 */
session_start();
require_once __DIR__ . '/../../../common/config.php';
require_once __DIR__ . '/../classes/base_practical_auto_manager.php';

header('Content-Type: application/json; charset=utf-8');

try {
    // キャストログイン確認
    if (!isset($_SESSION['cast_id'])) {
        throw new Exception('ログインが必要です');
    }
    
    // POSTメソッド確認
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('POSTメソッドのみサポート');
    }
    
    // リクエストデータ取得
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
    
    // 注文情報取得（変数置換用）
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
    
    // DB接続
    $pdo = new PDO(
        "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
        $user,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    // 注文がこのキャストのものか確認
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM base_order_items 
        WHERE base_order_id = ? AND cast_id = ?
    ");
    $stmt->execute([$order_id, $_SESSION['cast_id']]);
    if ($stmt->fetchColumn() == 0) {
        throw new Exception('この注文への権限がありません');
    }
    
    // BASE API Manager初期化
    $manager = new BasePracticalAutoManager();
    
    // 認証確認
    $auth_status = $manager->getAuthStatus();
    if (!isset($auth_status['write_orders']['authenticated']) || !$auth_status['write_orders']['authenticated']) {
        throw new Exception('BASE API認証が必要です');
    }
    
    // BASE APIでステータス更新
    $update_data = [
        'unique_key' => $order_id,
        'dispatch_status' => 'dispatched', // 発送済み
        'message' => $message
    ];
    
    $response = $manager->makeApiRequest('write_orders', '/1/orders/edit_status', $update_data, 'POST');
    
    // 対応履歴を記録
    $stmt = $pdo->prepare("
        INSERT INTO cast_order_completions 
        (base_order_id, cast_id, completed_at, template_id, template_name, reply_message, base_status_after, success)
        VALUES (?, ?, NOW(), ?, ?, ?, 'dispatched', TRUE)
    ");
    $stmt->execute([
        $order_id,
        $_SESSION['cast_id'],
        $template_id, // template_idを記録
        $template['template_name'],
        $message
    ]);
    
    // ローカルDBのステータスも更新
    $stmt = $pdo->prepare("
        UPDATE base_orders 
        SET status = 'shipping', updated_at = NOW() 
        WHERE base_order_id = ?
    ");
    $stmt->execute([$order_id]);
    
    echo json_encode([
        'success' => true,
        'message' => '対応完了しました',
        'order_id' => $order_id,
        'template_name' => $template['template_name'],
        'reply_message' => $message
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
    
    // エラーログ記録
    if (isset($pdo) && isset($order_id) && isset($_SESSION['cast_id'])) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO cast_order_completions 
                (base_order_id, cast_id, completed_at, message_type, reply_message, success, error_message)
                VALUES (?, ?, NOW(), ?, ?, FALSE, ?)
            ");
            $stmt->execute([
                $order_id,
                $_SESSION['cast_id'],
                $message_type ?? null,
                $message ?? null,
                $e->getMessage()
            ]);
        } catch (Exception $log_error) {
            // ログ記録失敗は無視
        }
    }
}
?>
