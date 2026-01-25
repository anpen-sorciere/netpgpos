<?php
require_once '../../common/config.php';
require_once '../../common/dbconnect.php';

try {
    $pdo = connect();
    echo "<h1>Cast Handled Diagnostic</h1>";
    
    $sql = "
        SELECT 
            boi.id,
            boi.base_order_id,
            boi.product_name,
            boi.cast_id,
            boi.cast_handled,
            boi.shipping_method,
            bo.status as order_status
        FROM base_order_items boi
        JOIN base_orders bo ON boi.base_order_id = bo.base_order_id
        WHERE boi.product_name LIKE '%生誕%' OR boi.product_name LIKE '%誕生%'
        ORDER BY bo.order_date DESC
        LIMIT 20
    ";
    
    $stmt = $pdo->query($sql);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1'><tr><th>Order ID</th><th>Product</th><th>Cast ID</th><th>Handled</th><th>Shipping</th></tr>";
    foreach($rows as $r) {
        echo "<tr>";
        echo "<td>{$r['base_order_id']}</td>";
        echo "<td>{$r['product_name']}</td>";
        echo "<td>{$r['cast_id']}</td>";
        echo "<td><strong>{$r['cast_handled']}</strong></td>";
        echo "<td>{$r['shipping_method']}</td>";
        echo "</tr>";
    }
    echo "</table>";

} catch (Exception $e) {
    echo $e->getMessage();
}
