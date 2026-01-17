<?php
/**
 * 注文データ定期同期スクリプト (Cron用)
 * サーバーのCronで5-10分おきに実行することを想定
 */

// メモリ制限緩和
ini_set('memory_limit', '512M');
set_time_limit(300); // 5分

require_once __DIR__ . '/../../../common/config.php';
require_once __DIR__ . '/../../../common/dbconnect.php';
require_once __DIR__ . '/../classes/OrderSync.php';
require_once __DIR__ . '/../classes/base_practical_auto_manager.php';

// ログファイル設定
$log_file = __DIR__ . '/sync_log.txt';
function sync_log($message) {
    global $log_file;
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[{$timestamp}] {$message}\n", FILE_APPEND);
}

sync_log("=== Sync Start ===");

try {
    // DB接続
    $pdo = new PDO(
        "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
        $user,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // 有効な店舗を取得
    $stmt = $pdo->query("SELECT shop_id, shop_name FROM shop_mst WHERE base_is_active = 1");
    $shops = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($shops)) {
        sync_log("No active shops found.");
        exit;
    }

    foreach ($shops as $shop) {
        $shop_id = $shop['shop_id'];
        $shop_name = $shop['shop_name'];
        sync_log("--- Processing Shop [ID: {$shop_id}] {$shop_name} ---");

        try {
            $manager = new BasePracticalAutoManager($shop_id);
            
            // 認証チェック
            $auth = $manager->getAuthStatus();
            if (empty($auth['read_orders']['authenticated'])) {
                sync_log("❌ [{$shop_name}] Error: Not authenticated. Please configure tokens first.");
                continue; // 次の店舗へ
            }

            // 未対応の注文のみを取得するため、直近のデータを取得
            $response = $manager->getDataWithAutoAuth('read_orders', '/orders', [
                'limit' => 50,
                'order' => 'desc',
                'sort' => 'order_date'
            ]);

            $orders = $response['orders'] ?? [];
            sync_log("[{$shop_name}] Fetched " . count($orders) . " orders from BASE.");

            if (!empty($orders)) {
                // OrderSync::syncOrdersToDbを利用して保存（shop_idを渡す）
                OrderSync::syncOrdersToDb($pdo, $orders, $manager, $shop_id);
                sync_log("[{$shop_name}] Sync completed.");
            }
        } catch (Exception $e) {
            sync_log("❌ [{$shop_name}] Error: " . $e->getMessage());
        }
    }

} catch (Exception $e) {
    sync_log("❌ Error (Global): " . $e->getMessage());
}

sync_log("=== Sync End ===\n");
?>
