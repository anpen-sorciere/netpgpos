<?php
/**
 * 注文データ整合性チェック & 自動修復
 * 
 * base_ordersに存在するがbase_order_itemsに商品データがない注文を
 * 自動検出してBASE APIから再取得・保存
 */
set_time_limit(600);
require_once __DIR__ . '/../../../common/config.php';
require_once __DIR__ . '/../classes/base_practical_auto_manager.php';
require_once __DIR__ . '/../ajax/order_data_ajax.php';

$dry_run = !isset($_GET['execute']) || $_GET['execute'] !== 'true';
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50; // 一度に処理する件数

try {
    $pdo = new PDO(
        "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
        $user,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    $manager = new BasePracticalAutoManager();
    
    echo '<h2>注文データ整合性チェック & 自動修復</h2>';
    echo '<pre>';
    echo "モード: " . ($dry_run ? "ドライラン（修復しない）" : "本番実行（修復する）") . "\n";
    echo "処理上限: {$limit}件\n";
    echo "開始時刻: " . date('Y-m-d H:i:s') . "\n\n";
    
    // STEP 1: 不整合データの検出
    echo "=== STEP 1: 不整合データの検出 ===\n";
    $sql = "
        SELECT o.base_order_id, o.order_date, o.customer_name, o.total_amount, o.status
        FROM base_orders o
        LEFT JOIN base_order_items oi ON o.base_order_id = oi.base_order_id
        WHERE oi.id IS NULL
        ORDER BY o.order_date DESC
        LIMIT {$limit}
    ";
    
    $stmt = $pdo->query($sql);
    $missing_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "base_ordersに存在するがbase_order_itemsにない注文: " . count($missing_orders) . "件\n\n";
    
    if (count($missing_orders) === 0) {
        echo "✅ 不整合データはありません。全ての注文に商品データが紐付いています。\n";
        echo '</pre>';
        exit;
    }
    
    // 一覧表示
    echo "不整合データ一覧:\n";
    foreach ($missing_orders as $idx => $order) {
        $num = $idx + 1;
        echo "{$num}. {$order['base_order_id']} - {$order['order_date']} - {$order['customer_name']} - ¥{$order['total_amount']} ({$order['status']})\n";
    }
    
    if ($dry_run) {
        echo "\n⚠️ ドライランモードです。実際には修復しません。\n";
        echo "本番実行するには以下のURLを開いてください:\n";
        echo "https://purplelion51.sakura.ne.jp/netpgpos/api/debug/auto_repair_orders.php?execute=true\n";
        echo "\n※処理件数を変更する場合: ?execute=true&limit=100\n";
        echo '</pre>';
        exit;
    }
    
    // STEP 2: 自動修復
    echo "\n=== STEP 2: 自動修復（BASE APIから再取得） ===\n";
    
    $success_count = 0;
    $error_count = 0;
    $empty_items_count = 0;
    $api_error_count = 0;
    
    foreach ($missing_orders as $idx => $order) {
        $num = $idx + 1;
        $order_id = $order['base_order_id'];
        
        echo "[{$num}/" . count($missing_orders) . "] {$order_id} ... ";
        
        try {
            // BASE APIから詳細取得
            $detail_response = $manager->getDataWithAutoAuth('read_orders', "/orders/detail/{$order_id}");
            
            if (isset($detail_response['order'])) {
                $order_detail = $detail_response['order'];
                $item_count = isset($order_detail['order_items']) ? count($order_detail['order_items']) : 0;
                
                if ($item_count > 0) {
                    // DB保存
                    syncOrdersToDb($pdo, [$order_detail], null);
                    echo "✅ 修復成功（{$item_count}商品）\n";
                    $success_count++;
                } else {
                    echo "⚠️ order_items空（APIには商品データなし）\n";
                    $empty_items_count++;
                }
                
            } else {
                echo "❌ APIレスポンス不正（orderキーなし）\n";
                $api_error_count++;
            }
            
        } catch (Exception $e) {
            $error_msg = $e->getMessage();
            if (strpos($error_msg, '404') !== false) {
                echo "❌ API 404（注文が存在しないか削除済み）\n";
            } else {
                echo "❌ エラー: " . substr($error_msg, 0, 50) . "...\n";
            }
            $error_count++;
        }
        
        // API制限対策（5件ごとに1秒待機）
        if ($num % 5 === 0 && $num < count($missing_orders)) {
            echo "    ... 1秒待機 ...\n";
            sleep(1);
        }
    }
    
    // STEP 3: 結果サマリー
    echo "\n=== STEP 3: 修復結果 ===\n";
    echo "✅ 修復成功: {$success_count}件\n";
    echo "⚠️ 商品データ空: {$empty_items_count}件\n";
    echo "❌ APIエラー: {$api_error_count}件\n";
    echo "❌ その他エラー: {$error_count}件\n";
    
    // STEP 4: 修復後の確認
    echo "\n=== STEP 4: 修復後の確認 ===\n";
    $stmt = $pdo->query("
        SELECT COUNT(*) 
        FROM base_orders o
        LEFT JOIN base_order_items oi ON o.base_order_id = oi.base_order_id
        WHERE oi.id IS NULL
    ");
    $remaining = $stmt->fetchColumn();
    
    echo "残りの不整合データ: {$remaining}件\n";
    
    if ($remaining > 0) {
        echo "\n再度実行することで、さらに修復できます:\n";
        echo "https://purplelion51.sakura.ne.jp/netpgpos/api/debug/auto_repair_orders.php?execute=true\n";
    } else {
        echo "✅ 全ての不整合データが修復されました！\n";
    }
    
    // STEP 5: cast_id紐付け状況
    echo "\n=== STEP 5: cast_id紐付け状況 ===\n";
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total,
            COUNT(cast_id) as with_cast_id
        FROM base_order_items
    ");
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $percentage = $stats['total'] > 0 ? round(($stats['with_cast_id'] / $stats['total']) * 100, 1) : 0;
    echo "全商品: {$stats['total']}件\n";
    echo "cast_id付き: {$stats['with_cast_id']}件 ({$percentage}%)\n";
    
    if ($stats['with_cast_id'] < $stats['total']) {
        echo "\n未紐付けデータがある場合、バックフィルバッチで紐付けできます:\n";
        echo "https://purplelion51.sakura.ne.jp/netpgpos/api/setup/backfill_cast_id_for_pending_orders.php?confirm=yes&execute=true\n";
    }
    
    echo "\n終了時刻: " . date('Y-m-d H:i:s') . "\n";
    echo '</pre>';
    
} catch (Exception $e) {
    echo '<pre>❌ 致命的エラー: ' . htmlspecialchars($e->getMessage()) . '</pre>';
}
?>
