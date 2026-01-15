<?php
// DBの同期状態を確認（API不要）
require_once __DIR__ . '/../../../common/config.php';

try {
    $pdo = new PDO(
        "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
        $user,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    echo '<h2>DB同期状態の確認</h2>';
    echo '<pre>';
    
    // 1. base_orders の件数
    $stmt = $pdo->query("SELECT COUNT(*) FROM base_orders");
    $order_count = $stmt->fetchColumn();
    echo "✅ base_orders: {$order_count}件\n";
    
    // 2. base_order_items の件数
    $stmt = $pdo->query("SELECT COUNT(*) FROM base_order_items");
    $item_count = $stmt->fetchColumn();
    echo "✅ base_order_items: {$item_count}件\n\n";
    
    // 3. 最新の注文5件
    echo "=== 最新の注文5件 ===\n";
    $stmt = $pdo->query("
        SELECT base_order_id, order_date, customer_name, total_amount, status 
        FROM base_orders 
        ORDER BY order_date DESC 
        LIMIT 5
    ");
    $latest_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($latest_orders) > 0) {
        foreach ($latest_orders as $order) {
            echo "ID: {$order['base_order_id']} | ";
            echo "日時: {$order['order_date']} | ";
            echo "顧客: {$order['customer_name']} | ";
            echo "金額: ¥{$order['total_amount']} | ";
            echo "状態: {$order['status']}\n";
        }
    } else {
        echo "❌ データがありません\n";
    }
    
    // 4. キャストIDが紐付いている商品の件数
    echo "\n=== キャスト紐付け状況 ===\n";
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total,
            COUNT(cast_id) as with_cast_id
        FROM base_order_items
    ");
    $cast_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    $with_cast = $cast_stats['with_cast_id'];
    $total = $cast_stats['total'];
    $percentage = $total > 0 ? round(($with_cast / $total) * 100, 1) : 0;
    
    echo "全商品: {$total}件\n";
    echo "キャストID付き: {$with_cast}件 ({$percentage}%)\n";
    
    // 5. キャスト別の商品数
    echo "\n=== キャスト別商品数 ===\n";
    $stmt = $pdo->query("
        SELECT 
            oi.cast_id,
            cm.cast_name,
            COUNT(*) as item_count
        FROM base_order_items oi
        LEFT JOIN cast_mst cm ON oi.cast_id = cm.cast_id
        WHERE oi.cast_id IS NOT NULL
        GROUP BY oi.cast_id, cm.cast_name
        ORDER BY item_count DESC
        LIMIT 10
    ");
    $cast_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($cast_items) > 0) {
        foreach ($cast_items as $item) {
            $cast_name = $item['cast_name'] ?? '(不明)';
            echo "キャスト名: {$cast_name} (ID:{$item['cast_id']}) - {$item['item_count']}件\n";
        }
    } else {
        echo "キャスト紐付けデータなし\n";
    }
    
    // 6. サプライズ注文の件数
    echo "\n=== サプライズ注文 ===\n";
    $stmt = $pdo->query("SELECT COUNT(*) FROM base_orders WHERE is_surprise = 1");
    $surprise_count = $stmt->fetchColumn();
    echo "サプライズ注文: {$surprise_count}件\n";
    
    echo "\n";
    echo "=== 準備完了 ===\n";
    echo "次回のAPI制限リセット: 08:00（あと" . (strtotime('08:00') > time() ? ceil((strtotime('08:00') - time()) / 60) : 0) . "分）\n";
    echo "リセット後にorder_monitor.phpを開いて、差分同期をテストしてください。\n";
    
    echo '</pre>';
    
} catch (PDOException $e) {
    echo '<pre>❌ エラー: ' . htmlspecialchars($e->getMessage()) . '</pre>';
}
?>
