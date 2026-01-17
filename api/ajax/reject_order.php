<?php
/**
 * 承認待ちアイテムの差し戻しAPI
 * cast_handled を 0 に戻し、キャストに再対応を促す
 */

session_start();
require_once __DIR__ . '/../../../common/config.php';
require_once __DIR__ . '/../../../common/dbconnect.php';

header('Content-Type: application/json; charset=utf-8');

try {
    // 管理者認証チェック
    if (!isset($_SESSION['utype']) || $_SESSION['utype'] !== 'admin') {
        throw new Exception('管理者権限が必要です');
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('POSTメソッドのみ許可');
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $order_id = $input['order_id'] ?? null;
    $item_id = $input['item_id'] ?? null;
    $reason = $input['reason'] ?? '内容の再確認をお願いします';

    if (!$order_id || !$item_id) {
        throw new Exception('order_id と item_id は必須です');
    }

    $pdo = connect();

    // cast_handled を 0 に戻す（未対応）
    $stmt = $pdo->prepare("
        UPDATE base_order_items 
        SET cast_handled = 0, cast_handled_at = NULL, cast_handled_template_id = NULL
        WHERE base_order_id = ? AND id = ? AND cast_handled = 1
    ");
    $stmt->execute([$order_id, $item_id]);

    if ($stmt->rowCount() === 0) {
        throw new Exception('対象のアイテムが見つからないか、既に差し戻し済みです');
    }

    // ログ記録（システムログがあれば）
    try {
        $log_stmt = $pdo->prepare("
            INSERT INTO system_logs (log_type, message, created_at)
            VALUES ('reject_order', ?, NOW())
        ");
        $log_stmt->execute([json_encode([
            'order_id' => $order_id,
            'item_id' => $item_id,
            'reason' => $reason,
            'admin_user' => $_SESSION['user_id'] ?? 'unknown'
        ], JSON_UNESCAPED_UNICODE)]);
    } catch (Exception $e) {
        // ログ記録失敗は無視
    }

    echo json_encode([
        'success' => true,
        'message' => '差し戻しが完了しました。キャストの画面に戻ります。'
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
