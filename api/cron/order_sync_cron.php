<?php
/**
 * 注文データ定期同期スクリプト (Cron用)
 * サーバーのCronで5-10分おきに実行することを想定
 */

// メモリ制限緩和
ini_set('memory_limit', '512M');
set_time_limit(300); // 5分

// 共通ファイルの読み込み (パス解決ロジック)
$search_paths_config = [
    __DIR__ . '/../../../common/config.php',
    __DIR__ . '/../../common/config.php',
    __DIR__ . '/../../config.php'
];
foreach ($search_paths_config as $path) {
    if (file_exists($path)) {
        require_once $path;
        break;
    }
}

$search_paths_db = [
    __DIR__ . '/../../../common/dbconnect.php',
    __DIR__ . '/../../common/dbconnect.php',
    __DIR__ . '/../../dbconnect.php'
];
foreach ($search_paths_db as $path) {
    if (file_exists($path)) {
        require_once $path;
        break;
    }
}
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
            // 1. まず注文一覧（IDリスト）を取得
            $list_response = $manager->getDataWithAutoAuth('read_orders', '/orders', [
                'limit' => 50,
                'order' => 'desc',
                'sort' => 'order_date'
            ]);
            
            $simple_orders = $list_response['orders'] ?? [];
            sync_log("[{$shop_name}] List fetched: " . count($simple_orders) . " orders. Fetching details...");

            $detailed_orders = [];
            foreach ($simple_orders as $idx => $simple_order) {
                $unique_key = $simple_order['unique_key'] ?? null;
                if (!$unique_key) continue;

                // APIの更新日時を取得 (BASE APIは 'updated' または 'ordered' を返す)
                $api_updated = $simple_order['updated'] ?? $simple_order['ordered'] ?? null;

                // DBの更新日時をチェック
                $stmtCheck = $pdo->prepare("SELECT updated_at FROM base_orders WHERE base_order_id = ? AND shop_id = ? LIMIT 1");
                $stmtCheck->execute([$unique_key, $shop_id]);
                $db_row = $stmtCheck->fetch(PDO::FETCH_ASSOC);

                // もしDBにあり、かつAPIの更新日時がDB以下なら、詳細取得をスキップ
                // (注意: BASEのupdatedは文字列なのでstrtotimeで比較)
                if ($db_row && $api_updated && strtotime($api_updated) <= strtotime($db_row['updated_at'])) {
                    // sync_log("Skipping {$unique_key}: Not updated. (DB: {$db_row['updated_at']} / API: {$api_updated})");
                    continue;
                }

                try {
                    // 2. 各注文の詳細データを取得 (ここに order_items が確実に入っている)
                    $detail_response = $manager->getDataWithAutoAuth('read_orders', '/orders/detail/' . $unique_key);
                    
                    if (!empty($detail_response['order'])) {
                        // 詳細データには updated が含まれていない場合があるので、一覧から補完しておく
                        if (empty($detail_response['order']['updated']) && $api_updated) {
                            $detail_response['order']['updated'] = $api_updated;
                        }
                        $detailed_orders[] = $detail_response['order'];
                    }

                    // APIレート制限への配慮 (0.2秒待機 => 1秒間に5回程度)
                    usleep(200000); 

                } catch (Exception $e) {
                     sync_log("!! Failed to fetch detail for {$unique_key}: " . $e->getMessage());
                }
            }

            sync_log("[{$shop_name}] Details fetched. Total: " . count($detailed_orders));

            if (!empty($detailed_orders)) {
                // OrderSync::syncOrdersToDbを利用して保存（shop_idを渡す）
                OrderSync::syncOrdersToDb($pdo, $detailed_orders, $manager, $shop_id);
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
