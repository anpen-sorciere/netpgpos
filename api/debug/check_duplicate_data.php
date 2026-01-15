<?php
// 重複データの調査スクリプト
require_once __DIR__ . '/../../../common/config.php';

try {
    $pdo = new PDO(
        "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
        $user,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    echo '<h2>重複データ調査</h2>';
    echo '<pre>';
    
    $target_order_id = 'AF5306D6CE8DA80E';
    
    // 1. base_order_itemsの該当データ
    echo "=== base_order_items の該当データ ===\n";
    $stmt = $pdo->prepare("
        SELECT * FROM base_order_items 
        WHERE base_order_id = ?
        ORDER BY id
    ");
    $stmt->execute([$target_order_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "件数: " . count($items) . "件\n\n";
    
    foreach ($items as $idx => $item) {
        echo "--- レコード " . ($idx + 1) . " ---\n";
        echo "ID: {$item['id']}\n";
        echo "product_id: {$item['product_id']}\n";
        echo "product_name: {$item['product_name']}\n";
        echo "quantity: {$item['quantity']}\n";
        echo "price: {$item['price']}\n";
        echo "cast_id: " . ($item['cast_id'] ?? 'NULL') . "\n";
        echo "customer_name_from_option: " . ($item['customer_name_from_option'] ?? 'NULL') . "\n";
        echo "\n";
    }
    
    // 2. base_ordersの該当データ
    echo "=== base_orders の該当データ ===\n";
    $stmt = $pdo->prepare("SELECT * FROM base_orders WHERE base_order_id = ?");
    $stmt->execute([$target_order_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($order) {
        echo "注文日: {$order['order_date']}\n";
        echo "顧客名: {$order['customer_name']}\n";
        echo "合計金額: {$order['total_amount']}\n";
        echo "ステータス: {$order['status']}\n";
    } else {
        echo "該当なし\n";
    }
    
    // 3. テーブル構造確認（主キー・ユニークキー）
    echo "\n=== base_order_items のテーブル構造 ===\n";
    $stmt = $pdo->query("SHOW CREATE TABLE base_order_items");
    $create_table = $stmt->fetch(PDO::FETCH_ASSOC);
    echo $create_table['Create Table'] . "\n";
    
    // 4. 全体の重複チェック
    echo "\n=== 全体の重複チェック ===\n";
    $stmt = $pdo->query("
        SELECT 
            base_order_id, 
            product_id,
            COUNT(*) as count
        FROM base_order_items
        GROUP BY base_order_id, product_id
        HAVING count > 1
        ORDER BY count DESC
        LIMIT 10
    ");
    $duplicates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($duplicates) > 0) {
        echo "重複データ: " . count($duplicates) . "組\n\n";
        foreach ($duplicates as $dup) {
            echo "注文ID: {$dup['base_order_id']}, 商品ID: {$dup['product_id']}, 件数: {$dup['count']}\n";
        }
    } else {
        echo "重複データなし\n";
    }
    
    // 5. 推奨される対処
    echo "\n=== 対処方法 ===\n";
    echo "1. 重複データの削除（古いレコードを削除）\n";
    echo "2. ユニークキー制約の追加（再発防止）\n";
    echo "   ALTER TABLE base_order_items ADD UNIQUE KEY unique_order_product (base_order_id, product_id);\n";
    
    echo '</pre>';
    
} catch (PDOException $e) {
    echo '<pre>❌ エラー: ' . htmlspecialchars($e->getMessage()) . '</pre>';
}
?>
