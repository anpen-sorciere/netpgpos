<?php
/**
 * キャスト対応報告API (承認フロー対応版)
 * BASE APIは叩かず、DB上のフラグ更新のみ行う
 */
session_start();
require_once __DIR__ . '/../../../common/config.php';

header('Content-Type: application/json; charset=utf-8');

// テストモードフラグ（互換性のために残すが、挙動は同じになる想定）
$test_mode = isset($_GET['test']) && $_GET['test'] === '1';

try {
    if (!isset($_SESSION['cast_id'])) {
        throw new Exception('ログインが必要です');
    }
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('POSTメソッドのみサポート');
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $order_id = $input['order_id'] ?? null;
    $template_id = $input['template_id'] ?? null;
    // 商品特定のためにproduct_nameも必要だが、現状のcast_dashboard.jsからは送られていない恐れ。
    // cast_dashboard.php側でproduct_nameも送るように修正が必要。
    // 一旦、order_idとcast_idで特定できる範囲（そのキャストの担当分すべて）を更新するか、
    // あるいは最初の1つを更新するか。
    // 厳密には product_name も送るべき。
    $product_name = $input['product_name'] ?? null;

    if (!$order_id || !$template_id) {
        throw new Exception('order_idとtemplate_idは必須です');
    }
    
    // DB接続
    $pdo = new PDO(
        "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
        $user,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // 対応済みフラグ更新
    // product_nameがある場合は特定の商品のみ、なければそのキャストのすべての担当詳細を更新（安全策）
    $sql = "
        UPDATE base_order_items 
        SET 
            cast_handled = 1, 
            cast_handled_at = NOW(),
            cast_handled_template_id = ?
        WHERE base_order_id = ? 
        AND cast_id = ?
    ";
    $params = [$template_id, $order_id, $_SESSION['cast_id']];

    if ($product_name) {
        $sql .= " AND product_name = ?";
        $params[] = $product_name;
    } else {
        // product_nameがない場合、未対応のものだけを更新するように制限
        $sql .= " AND cast_handled = 0";
    }

    $stmt = $pdo->prepare($sql);
    $result = $stmt->execute($params);

    if ($stmt->rowCount() === 0) {
        // 更新対象がなかった（既に対応済み、または割り当てがない）
        // エラーにはせず、メッセージで通知
        echo json_encode([
            'success' => true,
            'message' => '既に対応済みか、対象の商品が見つかりませんでした。',
        ]);
        exit;
    }

    // 成功レスポンス
    // reply_message は承認待ちなので返さない、または案内文を返す
    echo json_encode([
        'success' => true,
        'test_mode' => $test_mode,
        'message' => '承認待ちリストへ移動しました。管理者の確認後に送信されます。',
        'order_id' => $order_id,
        'reply_message' => "【承認待ち】管理者の確認をお待ちください。\n（まだお客様には送信されていません）"
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
