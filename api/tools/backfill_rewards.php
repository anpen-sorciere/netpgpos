<?php
/**
 * 特典申請データ(membership_rewards)バックフィルツール
 * 過去の未対応注文に対してBASE APIから詳細を取得し、カラムを埋める
 */

// タイムアウト延長
set_time_limit(600);
ini_set('memory_limit', '512M');

// CLI/Web両対応（環境変数はdbconnect.phpが処理）
// $_SERVER['HTTP_HOST'] trick not needed for Web

require_once __DIR__ . '/../../../common/config.php';
require_once __DIR__ . '/../../../common/dbconnect.php';
require_once __DIR__ . '/../classes/OrderSync.php';
require_once __DIR__ . '/../classes/base_practical_auto_manager.php';

echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>Backfill Rewards</title></head><body>";
echo "<h1>Backfill Membership Rewards</h1>";
echo "<pre>";

try {
    $pdo = connect();
    if (!$pdo) {
        echo "FATAL: Database connection failed.\n";
        exit(1);
    }
    // shop_idごとに処理するためソート
    $sql = "SELECT base_order_id, shop_id FROM base_orders 
            WHERE status IN ('ordered', 'unpaid') 
            ORDER BY shop_id, order_date DESC";
    
    $stmt = $pdo->query($sql);
    $targets = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($targets)) {
        echo "No target orders found.\n";
        exit;
    }

    echo "Found " . count($targets) . " orders to check.\n\n";

    // shop_idごとにグルーピング
    $orders_by_shop = [];
    foreach ($targets as $t) {
        $orders_by_shop[$t['shop_id']][] = $t['base_order_id'];
    }

    foreach ($orders_by_shop as $shop_id => $order_ids) {
        echo "--- Processing Shop ID: {$shop_id} (" . count($order_ids) . " orders) ---\n";
        
        try {
            $manager = new BasePracticalAutoManager($shop_id);
            $auth = $manager->getAuthStatus();
            if (empty($auth['read_orders']['authenticated'])) {
                echo "Skip Shop {$shop_id}: Not authenticated.\n";
                continue;
            }

            $batch_size = 10;
            $chunks = array_chunk($order_ids, $batch_size);

            foreach ($chunks as $chunk) {
                $detailed_orders = [];
                foreach ($chunk as $order_id) {
                    try {
                        // 詳細取得
                        $res = $manager->getDataWithAutoAuth('read_orders', '/orders/detail/' . $order_id);
                        if (!empty($res['order'])) {
                            $detailed_orders[] = $res['order'];
                            echo "Fetched: {$order_id}\n";
                        } else {
                            echo "Failed/Empty: {$order_id}\n";
                        }
                        usleep(200000); // 0.2s wait
                    } catch (Exception $e) {
                        echo "Error fetching {$order_id}: " . $e->getMessage() . "\n";
                    }
                }

                if (!empty($detailed_orders)) {
                    // OrderSyncを使って保存 (これでmembership_rewardsも入る)
                    OrderSync::syncOrdersToDb($pdo, $detailed_orders, $manager, $shop_id);
                    echo "Saved batch of " . count($detailed_orders) . " orders.\n";
                }
                
                flush(); // ブラウザへの出力バッファをフラッシュ
            }

        } catch (Exception $e) {
            echo "Shop processing error: " . $e->getMessage() . "\n";
        }
        echo "\n";
    }

    echo "All done.\n";

} catch (Exception $e) {
    echo "Fatal Error: " . $e->getMessage();
}

echo "</pre></body></html>";
