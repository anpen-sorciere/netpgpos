<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../../common/dbconnect.php';

// 修復モードフラグ
$fix_mode = isset($_GET['fix']) && $_GET['fix'] === '1';

echo "<h1>Inconsistent Order Scanner</h1>";
echo "<p>ステータスが「発送済み(dispatched)」なのに、「未対応(cast_handled=0)」の商品が残っている注文を検出します。</p>";

try {
    $pdo = connect();
    if (!$pdo) exit;

    // 不整合データの検出
    // base_orders.status = 'dispatched' かつ
    // base_order_items に cast_handled=0 または NULL が存在する
    $sql = "
        SELECT 
            o.base_order_id, 
            o.order_date, 
            o.customer_name, 
            o.status,
            COUNT(oi.id) as total_items,
            SUM(CASE WHEN oi.cast_handled = 0 OR oi.cast_handled IS NULL THEN 1 ELSE 0 END) as unhandled_count
        FROM base_orders o
        INNER JOIN base_order_items oi ON o.base_order_id = oi.base_order_id
        WHERE o.status = 'dispatched'
        GROUP BY o.base_order_id
        HAVING unhandled_count > 0
        ORDER BY o.order_date DESC
    ";

    $stmt = $pdo->query($sql);
    $inconsistent_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($inconsistent_orders)) {
        echo "<h2 style='color:green;'>No inconsistent orders found.</h2>";
        echo "<p>すべてのデータは整合性が取れています。</p>";
    } else {
        echo "<h2 style='color:red;'>" . count($inconsistent_orders) . " Inconsistent Orders Found</h2>";
        
        if ($fix_mode) {
             echo "<div style='background:#d1e7dd; padding:10px; margin-bottom:20px; border:1px solid #badbcc;'><strong>修復を実行しました。</strong></div>";
        } else {
             echo "<div style='background:#fff3cd; padding:10px; margin-bottom:20px; border:1px solid #ffecb5;'>
                    これらの注文は誤って「発送済み」になっています。<br>
                    <a href='?fix=1' style='font-weight:bold; color:#d63384;'>[ここをクリックして一括修復する（ステータスをorderedに戻す）]</a>
                   </div>";
        }

        echo "<table border='1' cellpadding='5'>";
        echo "<thead><tr><th>Order ID</th><th>Date</th><th>Customer</th><th>Total Items</th><th>Unhandled Items</th><th>Status</th><th>Action</th></tr></thead>";

        foreach ($inconsistent_orders as $order) {
            $action_result = "-";
            
            if ($fix_mode) {
                // 修復実行: ステータスを 'ordered' に戻す
                $upd = $pdo->prepare("UPDATE base_orders SET status = 'ordered', updated_at = NOW() WHERE base_order_id = ?");
                $upd->execute([$order['base_order_id']]);
                $action_result = "<span style='color:green;'>Fixed (-> ordered)</span>";
            }

            echo "<tr>";
            echo "<td>" . htmlspecialchars($order['base_order_id']) . "</td>";
            echo "<td>" . htmlspecialchars($order['order_date']) . "</td>";
            echo "<td>" . htmlspecialchars($order['customer_name']) . "</td>";
            echo "<td>" . htmlspecialchars($order['total_items']) . "</td>";
            echo "<td style='font-weight:bold; color:red;'>" . htmlspecialchars($order['unhandled_count']) . "</td>";
            echo "<td>" . htmlspecialchars($order['status']) . "</td>";
            echo "<td>" . $action_result . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
