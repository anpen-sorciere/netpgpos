<?php
$_SERVER['HTTP_HOST'] = 'production'; // Force remote config

require_once __DIR__ . '/../common/config.php';
require_once __DIR__ . '/../common/dbconnect.php';

try {
    $pdo = connect();
    echo "Connected to DB.\n";
    
    // Check base_order_items for birthday keywords
    $sql = "
        SELECT 
            o.base_order_id, o.status, o.updated_at,
            oi.product_name, oi.cast_id, oi.customer_name_from_option
        FROM base_orders o
        JOIN base_order_items oi ON o.base_order_id = oi.base_order_id
        WHERE oi.product_name LIKE '%生誕%' OR oi.product_name LIKE '%誕生%'
        ORDER BY o.order_date DESC
        LIMIT 20
    ";
    
    $stmt = $pdo->query($sql);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Found " . count($rows) . " birthday/event items:\n";
    foreach ($rows as $r) {
        echo "Order: {$r['base_order_id']} | Status: {$r['status']} | Updated: {$r['updated_at']}\n";
        echo "  - Product: {$r['product_name']}\n";
        echo "  - Cast ID: " . ($r['cast_id'] ?? 'NULL') . "\n";
        echo "---------------------------------\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
