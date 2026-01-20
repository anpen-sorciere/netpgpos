<?php
/**
 * 過去注文の配送情報バックフィルスクリプト
 * 未発送(ordered)の注文について、BASE APIから詳細情報を取得し、
 * shipping_method, tracking_number, delivery_company_id などをDBに保存する。
 */

// デバッグ設定（画面にエラーを出す）
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// タイムリミット解除
set_time_limit(0);
ini_set('memory_limit', '512M');

header('Content-Type: text/plain; charset=utf-8');

echo "Script Path: " . __FILE__ . "\n";


require_once __DIR__ . '/../../../common/config.php';
require_once __DIR__ . '/../../../common/dbconnect.php';
require_once __DIR__ . '/../classes/base_practical_auto_manager.php';
require_once __DIR__ . '/../classes/OrderSync.php';

echo "Files loaded. Starting process...\n";



// 認証チェックなどを入れるべきだが、今回限りのツールかつ管理者実行前提とする
// 安全のためCLI実行か、あるいはブラウザアクセスの場合は簡易チェック
// ここでは簡易的に実行開始
echo "Starting Backfill Process...<br>\n";

try {
    // DB接続 (common/dbconnect.php の connect() 関数を使用)
    $pdo = connect();
    if ($pdo === null) {
        throw new Exception("Database connection failed. Check config_local.php or MySQL status.");
    }
    
    // 対象の注文を取得（未発送のもののみ）shop_idも一緒に取得
    echo "Fetching target orders from DB...\n";
    $stmt = $pdo->prepare("SELECT base_order_id, shop_id FROM base_orders WHERE status = 'ordered' ORDER BY shop_id, order_date DESC");
    $stmt->execute();
    $target_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $count = count($target_orders);
    if ($count === 0) {
        echo "No orders to update. Finished.\n";
        exit;
    }

    // shop_id別にグループ化
    $orders_by_shop = [];
    foreach ($target_orders as $row) {
        $shop_id = $row['shop_id'];
        if (!isset($orders_by_shop[$shop_id])) {
            $orders_by_shop[$shop_id] = [];
        }
        $orders_by_shop[$shop_id][] = $row['base_order_id'];
    }

    echo "Found orders in " . count($orders_by_shop) . " shop(s).\n\n";

    $success_count = 0;
    $error_count = 0;
    
    // shop_id別に処理
    foreach ($orders_by_shop as $shop_id => $order_ids) {
        echo "\n=== Processing Shop ID: {$shop_id} (" . count($order_ids) . " orders) ===\n";
        
        try {
            // 各shop_id用のAPIマネージャーを初期化
            $apiManager = new BasePracticalAutoManager($shop_id);
            
            foreach ($order_ids as $index => $order_id) {
                $current = $index + 1;
                $total = count($order_ids);
                echo "[Shop {$shop_id}] [{$current}/{$total}] Processing Order ID: {$order_id} ... ";;
                
                try {
                    // 詳細API呼び出し
                    $response = $apiManager->makeApiRequest('read_orders', '/orders/detail/' . $order_id);
                    
                    if (empty($response['order'])) {
                        echo "Failed (No order data returned)\n";
                        $error_count++;
                        continue;
                    }
                    
                    $order_data = $response['order'];
                    
                    // OrderSyncを使って保存（正しいshop_idを渡す）
                    OrderSync::syncOrdersToDb($pdo, [$order_data], null, $shop_id);
                    
                    echo "OK\n";
                    $success_count++;
                    
                    // APIレートリミット対策（少しウェイトを入れる）
                    usleep(500000); // 0.5秒
                    
                } catch (Exception $e) {
                    echo "Error: " . $e->getMessage() . "\n";
                    $error_count++;
                }
            }
        } catch (Exception $e) {
            echo "\n❌ Shop {$shop_id} Error: " . $e->getMessage() . "\n";
            $error_count += count($order_ids); // このshopの全注文をエラーカウント
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
