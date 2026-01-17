<?php
/**
 * 注文データ定期同期スクリプト (Cron用)
 * サーバーのCronで5-10分おきに実行することを想定
 */

// メモリ制限緩和
ini_set('memory_limit', '512M');
set_time_limit(300); // 5分

require_once __DIR__ . '/../../common/config.php';
require_once __DIR__ . '/../../common/dbconnect.php';
require_once __DIR__ . '/../../common/functions.php';
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
    $manager = new BasePracticalAutoManager();
    
    // 認証チェック
    $auth = $manager->getAuthStatus();
    if (empty($auth['read_orders']['authenticated'])) {
        sync_log("❌ Error: Not authenticated. Please run auto_auth.php first.");
        exit;
    }
    
    // DB接続
    $pdo = new PDO(
        "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
        $user,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // 未対応の注文のみを取得するため、直近のデータを取得
    // limit=50で十分（短間隔で回す前提）
    $response = $manager->getDataWithAutoAuth('read_orders', '/orders', [
        'limit' => 50,
        'order' => 'desc',
        'sort' => 'order_date'
    ]);

    $orders = $response['orders'] ?? [];
    sync_log("Fetched " . count($orders) . " orders from BASE.");

    if (!empty($orders)) {
        // syncOrdersToDb関数（common/functions.php）を利用して保存
        // 第3引数のmanagerは詳細取得用
        $saved = syncOrdersToDb($pdo, $orders, $manager);
        
        // 保存結果のログ出し（syncOrdersToDbが詳細を返さない場合は簡易ログ）
        sync_log("Sync completed. Check DB for updates.");
    }

} catch (Exception $e) {
    sync_log("❌ Error: " . $e->getMessage());
}

sync_log("=== Sync End ===\n");
?>
