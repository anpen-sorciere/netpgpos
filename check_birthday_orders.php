<?php
require_once '../common/dbconnect.php';
require_once '../common/functions.php';


try {
    $pdo = connect();
    
    echo "<h1>Birthday/Event Order Debug</h1>";
    echo "<a href='../../index.php'>Back to Menu</a><hr>";

    // Search for items with keywords
    $sql = "
        SELECT 
            boi.*, 
            bo.customer_name, bo.status as order_status,
            cm.cast_name as assigned_cast_name
        FROM base_order_items boi
        JOIN base_orders bo ON boi.base_order_id = bo.base_order_id
        LEFT JOIN cast_mst cm ON boi.cast_id = cm.cast_id
        WHERE boi.product_name LIKE :k1 OR boi.product_name LIKE :k2
        ORDER BY bo.order_date DESC
        LIMIT 50
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':k1' => '%生誕%', ':k2' => '%誕生%']);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' cellpadding='5' style='border-collapse:collapse; width:100%;'>";
    echo "<tr style='background:#ccc;'>
            <th>Date</th>
            <th>Order ID</th>
            <th>Status</th>
            <th>Item Title</th>
            <th>Assigned Cast ID</th>
            <th>Assigned Cast Name</th>
            <th>Cast Handled</th>
          </tr>";
          
    foreach($items as $item) {
        $color = $item['cast_id'] > 0 ? '#eaffea' : '#ffeaea';
        echo "<tr style='background:{$color}'>";
        echo "<td>" . htmlspecialchars($item['order_date'] ?? 'N/A') . "</td>";
        echo "<td>" . htmlspecialchars($item['base_order_id']) . "</td>";
        echo "<td>" . htmlspecialchars($item['order_status']) . "</td>";
        echo "<td>" . htmlspecialchars($item['product_name']) . "</td>";
        echo "<td><b>" . htmlspecialchars($item['cast_id']) . "</b></td>";
        echo "<td>" . htmlspecialchars($item['assigned_cast_name']) . "</td>";
        echo "<td>" . htmlspecialchars($item['cast_handled']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Also list all casts to check naming
    echo "<h2>All Casts</h2>";
    $casts = $pdo->query("SELECT * FROM cast_mst WHERE cast_status = '在籍'")->fetchAll();
    echo "<div style='display:flex; flex-wrap:wrap; gap:10px;'>";
    foreach($casts as $c) {
        echo "<div style='border:1px solid #ccc; padding:5px;'>ID:{$c['cast_id']} {$c['cast_name']}</div>";
    }
    echo "</div>";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
