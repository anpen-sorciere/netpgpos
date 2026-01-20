<?php
/**
 * 特定のキャストに関連する未対応注文のステータスをBASEと同期するAJAX API
 * CAST単位で処理することで、APIリクエスト数を抑えつつ必要なデータを更新する
 */
// エラー表示設定（デバッグ用）
ini_set('display_errors', 0);
ini_set('log_errors', 1);

session_start();
require_once __DIR__ . '/../../../common/config.php';
require_once __DIR__ . '/../classes/base_practical_auto_manager.php';

header('Content-Type: application/json; charset=utf-8');

try {
    // 認証チェック
    if (!isset($_SESSION['utype'])) {
        throw new Exception('認証が必要です');
    }

    $cast_id = $_GET['cast_id'] ?? null;
    if (!$cast_id) {
        throw new Exception('キャストIDが指定されていません');
    }

    // DB接続
    $pdo = new PDO(
        "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
        $user,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );



    // 対象の注文を取得（ordered, unpaidなど未完了のもの）
    // base_order_items に cast_id が含まれる base_order_id を抽出
    $sql = "
        SELECT DISTINCT o.base_order_id, o.status, o.shop_id
        FROM base_orders o
        INNER JOIN base_order_items oi ON o.base_order_id = oi.base_order_id
        WHERE oi.cast_id = :cast_id
        AND o.status IN ('ordered', 'unpaid', '対応中')
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':cast_id' => $cast_id]);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);



    if (empty($orders)) {
        echo json_encode([
            'success' => true,
            'message' => '同期対象の未対応注文はありませんでした。',
            'updated_count' => 0
        ]);
        exit;
    }

    $updated_count = 0;
    $errors = [];
    
    // Shop IDごとにManagerインスタンスをキャッシュ
    $managers = [];

    foreach ($orders as $order) {
        $order_id = $order['base_order_id'];
        $current_status = $order['status'];
        $shop_id = $order['shop_id']; // デフォルト1だがDBから取得

        try {
            if (!isset($managers[$shop_id])) {
                $mgr = new BasePracticalAutoManager($shop_id);
                // 認証チェック
                $status = $mgr->getAuthStatus();
                if (empty($status['read_orders']['authenticated'])) {
                   throw new Exception("Shop ID {$shop_id} のAPI認証が無効です");
                }
                $managers[$shop_id] = $mgr;
            }
            
            $api = $managers[$shop_id];

            // BASE APIから注文詳細を取得
            // orders/detail/{id}
            $detail = $api->makeApiRequest('read_orders', '/orders/detail/' . $order_id);
            
            if (empty($detail['order'])) {
                // 注文が見つからない（ありえないはずだが）
                continue;
            }

            $api_order = $detail['order'];
            // statusは 'dispatch_status' を参照する（OrderSync.php準拠）
            $api_status = $api_order['dispatch_status'] ?? $api_order['order_status'] ?? 'unknown';
            
            // ステータス不一致なら更新
            // BASE側のステータス定義: ordered, dispatched, cancelled, unpaid (入金待ち？要確認)
            // BASE APIの order_status は 'ordered' (未発送), 'dispatched' (発送済み), 'cancelled' (キャンセル) など
            
            // ローカルの status と比較
            // ローカルは 'unpaid' もあるが、BASE側で未入金はどうなっているか？
            // BASEのAPIドキュメント等によると、コンビニ払い等の未入金は 'unpaid' ではなく 'ordered' の一部だが
            // payment_is_paid フラグなどで判別されることが多い。
            // ここではシンプルに、BASEの order_status が 'dispatched' や 'cancelled' になっているのに
            // ローカルが 'ordered'/'unpaid'/'対応中' のままなら更新する、というロジックにする。
            
            if ($api_status !== $current_status) {
                // 特に dispatched や cancelled への変化を取り込む
                // 厳密なチェック: APIが dispatched/cancelled ならローカルもそれに合わせる
                if ($api_status === 'dispatched' || $api_status === 'cancelled') {
                    $upd = $pdo->prepare("UPDATE base_orders SET status = :status, updated_at = NOW() WHERE base_order_id = :id");
                    $upd->execute([':status' => $api_status, ':id' => $order_id]);
                    $updated_count++;
                }
            }

        } catch (Exception $e) {
            $errors[] = "Order {$order_id}: " . $e->getMessage();
        }
        
        // APIレートリミット考慮（少しウェイトを入れる）
        usleep(200000); // 0.2秒
    }

    echo json_encode([
        'success' => true,
        'message' => "同期が完了しました。更新: {$updated_count}件",
        'updated_count' => $updated_count,
        'errors' => $errors
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
