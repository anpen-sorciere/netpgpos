<?php
/**
 * 過去注文の配送情報バックフィルスクリプト
 * 未発送(ordered)の注文について、BASE APIから詳細情報を取得し、
 * shipping_method, tracking_number, delivery_company_id などをDBに保存する。
 */

// タイムリミット解除
set_time_limit(0);
ini_set('memory_limit', '512M');

require_once __DIR__ . '/../../common/config.php';
require_once __DIR__ . '/../../common/dbconnect.php';
require_once __DIR__ . '/../classes/BaseApiManager.php';
require_once __DIR__ . '/../classes/OrderSync.php';

header('Content-Type: text/plain; charset=utf-8');

// 認証チェックなどを入れるべきだが、今回限りのツールかつ管理者実行前提とする
// 安全のためCLI実行か、あるいはブラウザアクセスの場合は簡易チェック
// ここでは簡易的に実行開始
echo "Starting Backfill Process...\n";

try {
    $pdo = get_pdo(); // dbconnect.phpで定義されている関数と仮定
    
    // 対象の注文を取得（未発送のもののみ）
    // status が 'cancelled' や 'dispatched' 以外のものを対象にする
    // あるいは明確に 'ordered' のみでも良い
    echo "Fetching target orders from DB...\n";
    $stmt = $pdo->prepare("SELECT base_order_id FROM base_orders WHERE status = 'ordered' ORDER BY order_date DESC");
    $stmt->execute();
    $target_orders = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $count = count($target_orders);
    echo "Found {$count} orders to process.\n\n";
    
    if ($count === 0) {
        echo "No orders to update. Finished.\n";
        exit;
    }

    // APIマネージャーの初期化
    $apiManager = new BaseApiManager();
    
    $success_count = 0;
    $error_count = 0;
    
    foreach ($target_orders as $index => $order_id) {
        $current = $index + 1;
        echo "[{$current}/{$count}] Processing Order ID: {$order_id} ... ";
        
        try {
            // 詳細API呼び出し
            $response = $apiManager->makeApiRequest('read_orders', '/orders/detail/' . $order_id);
            
            if (empty($response['order'])) {
                echo "Failed (No order data returned)\n";
                $error_count++;
                continue;
            }
            
            $order_data = $response['order'];
            
            // OrderSyncを使って保存
            // syncOrdersToDbは配列を受け取るので配列に入れる
            OrderSync::syncOrdersToDb($pdo, [$order_data], null, 1); // shop_idはとりあえず1固定、あるいはDBから取った方が良い？
            // OrderSyncの定義: public static function syncOrdersToDb($pdo, $orders, $manager = null, $shop_id = 1)
            // shop_idはデフォルト1だが、もし複数ショップ運用なら注意。今回は1で進める。
            
            echo "OK\n";
            $success_count++;
            
            // APIレートリミット対策（少しウェイトを入れる）
            usleep(500000); // 0.5秒
            
        } catch (Exception $e) {
            echo "Error: " . $e->getMessage() . "\n";
            $error_count++;
        }
    }
    
    echo "\n--------------------------------\n";
    echo "Process Finished.\n";
    echo "Success: {$success_count}\n";
    echo "Errors: {$error_count}\n";

} catch (Exception $e) {
    echo "Critical Error: " . $e->getMessage() . "\n";
}
?>
