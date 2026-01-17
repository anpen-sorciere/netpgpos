<?php
/**
 * 管理者承認実行API
 * キャスト対応済みの注文を承認し、BASE APIを実行する
 */
session_start();
require_once __DIR__ . '/../../../common/config.php';
require_once __DIR__ . '/../classes/base_practical_auto_manager.php';

header('Content-Type: application/json; charset=utf-8');

try {
    // 簡易管理者認証（order_monitor.phpと同様）
    // 本来は厳密な権限チェックが必要だが、現状のセッション構成に依存
    if (!isset($_SESSION['utype'])) {
        throw new Exception('管理者権限が必要です');
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('POSTメソッドのみサポート');
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $order_id = $input['order_id'] ?? null;
    $cast_id = $input['cast_id'] ?? null; // どのキャストの対応を承認するか

    if (!$order_id) {
        throw new Exception('注文IDは必須です');
    }

    // DB接続
    $pdo = new PDO(
        "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
        $user,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // 注文とキャスト対応状況の取得
    // 特定のキャストの承認待ちアイテムがあるか確認
    $sql = "
        SELECT oi.*, o.customer_name 
        FROM base_order_items oi
        INNER JOIN base_orders o ON oi.base_order_id = o.base_order_id
        WHERE oi.base_order_id = ? 
        AND oi.cast_handled = 1 
    ";
    $params = [$order_id];
    
    // cast_idが指定されていれば絞り込み
    if ($cast_id) {
        $sql .= " AND oi.cast_id = ?";
        $params[] = $cast_id;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($items)) {
        throw new Exception('承認待ちの対象商品が見つかりません');
    }

    // BASE API連携用マネージャー
    $manager = new BasePracticalAutoManager();
    $auth_status = $manager->getAuthStatus();
    if (!isset($auth_status['write_orders']['authenticated']) || !$auth_status['write_orders']['authenticated']) {
        throw new Exception('BASE API認証が必要です（管理者側）');
    }

    // テンプレート情報の取得とメッセージ構築
    // 1つの注文に複数の商品が含まれる場合、まとめて1通送るか、別々に送るか？
    // 現状の仕様では「注文単位」でステータス変更するため、まとめて処理するのが自然。
    // ただし、テンプレートが異なる場合は結合する。

    $messages = [];
    $processed_items = [];

    foreach ($items as $item) {
        if (!$item['cast_handled_template_id']) continue;

        $stmt = $pdo->prepare("SELECT * FROM reply_message_templates WHERE id = ?");
        $stmt->execute([$item['cast_handled_template_id']]);
        $tmpl = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($tmpl) {
            // 変数置換
            $msg = $tmpl['template_body'];
            $msg = str_replace('{customer_name}', $item['customer_name_from_option'] ?: $items[0]['customer_name'], $msg);
            $msg = str_replace('{product_name}', $item['product_name'], $msg);
            $msg = str_replace('{order_id}', $order_id, $msg);
            
            // キャスト名を取得
            $stmt_cast = $pdo->prepare("SELECT cast_name FROM cast_mst WHERE cast_id = ?");
            $stmt_cast->execute([$item['cast_id']]);
            $cast_name = $stmt_cast->fetchColumn();
            $msg = str_replace('{cast_name}', $cast_name, $msg);

            $messages[] = $msg;
            $processed_items[] = $item['product_name'];
        }
    }

    if (empty($messages)) {
        throw new Exception('送信するメッセージを作成できませんでした（テンプレート未設定など）');
    }

    // メッセージ結合（改行で区切る）またはカスタムメッセージ使用
    if (!empty($input['custom_message'])) {
        $final_message = $input['custom_message'];
    } else {
        $final_message = implode("\n\n--------------------------------\n\n", $messages);
    }

    // プレビューモードならここで終了
    if (!empty($input['preview'])) {
        echo json_encode([
            'success' => true,
            'preview' => true,
            'message' => $final_message,
            'processed_items' => $processed_items
        ]);
        exit;
    }

    // BASE API実行 (ステータス更新 & メッセージ送信)
    $update_data = [
        'unique_key' => $order_id,
        'dispatch_status' => 'dispatched',
        'message' => $final_message
    ];
    
    $manager->makeApiRequest('write_orders', '/1/orders/edit_status', $update_data, 'POST');

    // 履歴記録 (cast_order_completionsへの保存)
    // 複数の商品がある場合は代表して最初の1件分のログを残すか、または商品ごとに残すか。
    // 元々 cast_complete_order.php では order_id 単位で1レコードだったようなのでそれに合わせる。
    // ただし items 分ループして template_id が異なる可能性考慮が必要だが、
    // 現状はまとめて送っているので、使用した template_id（の配列など）を記録したいところ。
    // シンプルに、ここで承認した情報を記録する。
    
    // itemsの最初の要素の情報を使う（定型文は結合されているが、メインの識別として）
    $main_item = $items[0]; 
    $tmpl_id_log = $main_item['cast_handled_template_id'];
    
    // テンプレート名取得（まだ取得してない場合のため再取得）
    $stmt_tmpl = $pdo->prepare("SELECT template_name FROM reply_message_templates WHERE id = ?");
    $stmt_tmpl->execute([$tmpl_id_log]);
    $tmpl_name_log = $stmt_tmpl->fetchColumn();

    $stmt_log = $pdo->prepare("
        INSERT INTO cast_order_completions 
        (base_order_id, cast_id, completed_at, template_id, template_name, reply_message, base_status_after, success)
        VALUES (?, ?, NOW(), ?, ?, ?, 'dispatched', TRUE)
    ");
    $stmt_log->execute([
        $order_id,
        $cast_id ?: $main_item['cast_id'], // 指定なければitemから
        $tmpl_id_log,
        $tmpl_name_log,
        $final_message
    ]);

    // DB更新 (承認済みとして処理完了)
    // base_ordersのステータス更新
    $pdo->prepare("UPDATE base_orders SET status = 'shipping', updated_at = NOW() WHERE base_order_id = ?")->execute([$order_id]);

    // ログ記録などがあればここで行う

    echo json_encode([
        'success' => true,
        'message' => '承認し、BASEへの反映を完了しました。',
        'processed_items' => $processed_items
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
