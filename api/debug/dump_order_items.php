<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../../common/dbconnect.php';

$order_id = $_GET['order_id'] ?? '';

if (!$order_id) {
    echo "Please provide order_id (e.g. ?order_id=12345)";
    exit;
}

try {
    $pdo = connect();
    if (!$pdo) {
        echo "Database connection failed.";
        exit;
    }

    echo "<h1>Order Debug: " . htmlspecialchars($order_id) . "</h1>";

    // base_orders
    $stmt = $pdo->prepare("SELECT * FROM base_orders WHERE base_order_id = ?");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    echo "<h2>base_orders</h2>";
    if ($order) {
        echo "<table border='1' cellpadding='5'>";
        foreach ($order as $k => $v) {
            echo "<tr><th>{$k}</th><td>" . htmlspecialchars($v ?? 'NULL') . "</td></tr>";
        }
        echo "</table>";
    } else {
        echo "<p>Order not found in base_orders</p>";
    }

    // base_order_items
    $stmt = $pdo->prepare("SELECT * FROM base_order_items WHERE base_order_id = ?");
    $stmt->execute([$order_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<h2>base_order_items (" . count($items) . ")</h2>";
    echo "<table border='1' cellpadding='5'>";
    echo "<thead><tr>
            <th>ID</th>
            <th>Item Name</th>
            <th>Cast ID</th>
            <th>Cast Handled</th>
            <th>Surprise Date</th>
            <th>Shipping Method</th>
          </tr></thead>";
    
    foreach ($items as $item) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($item['base_order_item_id']) . " (" . $item['id'] . ")</td>";
        echo "<td>" . htmlspecialchars($item['product_name']) . "</td>";
        echo "<td>" . htmlspecialchars($item['cast_id'] ?? 'NULL') . "</td>";
        echo "<td>" . htmlspecialchars($item['cast_handled'] ?? 'NULL') . "</td>";
        echo "<td>" . htmlspecialchars($item['item_surprise_date'] ?? 'NULL') . "</td>";
        echo "<td>" . htmlspecialchars($item['shipping_method'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
