<?php
// キャストダッシュボードのデータ状況デバッグ
require_once __DIR__ . '/../../../common/config.php';

try {
    $pdo = new PDO(
        "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
        $user,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    echo '<h2>キャストダッシュボード - データ診断</h2>';
    echo '<pre>';
    
    // 1. 現在のログインキャスト（セッションから）
    session_start();
    $logged_in_cast_id = $_SESSION['cast_id'] ?? null;
    $logged_in_cast_name = $_SESSION['cast_name'] ?? null;
    
    echo "=== ログイン情報 ===\n";
    echo "キャストID: " . ($logged_in_cast_id ?? '未ログイン') . "\n";
    echo "キャスト名: " . ($logged_in_cast_name ?? '未ログイン') . "\n\n";
    
    // 2. base_order_itemsの全体状況
    echo "=== base_order_items 全体状況 ===\n";
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total,
            COUNT(cast_id) as with_cast_id,
            COUNT(DISTINCT cast_id) as unique_casts
        FROM base_order_items
    ");
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "全商品数: {$stats['total']}件\n";
    echo "cast_id付き: {$stats['with_cast_id']}件\n";
    echo "ユニークなキャスト数: {$stats['unique_casts']}名\n\n";
    
    // 3. キャスト別の商品数
    echo "=== キャスト別商品数 ===\n";
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
    ");
    $cast_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($cast_items) > 0) {
        foreach ($cast_items as $item) {
            $name = $item['cast_name'] ?? '(不明)';
            echo "ID:{$item['cast_id']} {$name} - {$item['item_count']}件\n";
        }
    } else {
        echo "❌ cast_id付きのデータが0件です\n";
        echo "→ これが原因でダッシュボードにデータが表示されません\n";
    }
    
    echo "\n=== 解決策 ===\n";
    
    if ($stats['with_cast_id'] == 0) {
        echo "【現状】\n";
        echo "・既存データ({$stats['total']}件)にはcast_idが紐付いていません\n";
        echo "・これは仕様変更前のデータなので正常です\n\n";
        
        echo "【次のステップ】\n";
        echo "1. order_monitor.phpを開いて差分同期を実行\n";
        echo "   → http://localhost/netpgpos/api/order_monitor.php\n\n";
        
        echo "2. 新規注文があればcast_id付きで保存されます\n\n";
        
        echo "3. 過去データも表示したい場合:\n";
        echo "   バックフィルバッチを実行してください\n";
        echo "   → http://localhost/netpgpos/api/setup/backfill_cast_id_for_pending_orders.php?confirm=yes\n";
    } else {
        echo "✅ cast_id付きのデータがあります\n";
        echo "ダッシュボードに表示されるはずです。\n\n";
        
        if ($logged_in_cast_id) {
            // ログイン中のキャストのデータ確認
            $stmt = $pdo->prepare("
                SELECT COUNT(*) 
                FROM base_order_items 
                WHERE cast_id = ?
            ");
            $stmt->execute([$logged_in_cast_id]);
            $my_count = $stmt->fetchColumn();
            
            echo "ログイン中のキャスト（ID:{$logged_in_cast_id}）のデータ: {$my_count}件\n";
            
            if ($my_count == 0) {
                echo "→ このキャストのデータがないため表示されません\n";
            }
        }
    }
    
    echo '</pre>';
    
} catch (PDOException $e) {
    echo '<pre>❌ エラー: ' . htmlspecialchars($e->getMessage()) . '</pre>';
}
?>
