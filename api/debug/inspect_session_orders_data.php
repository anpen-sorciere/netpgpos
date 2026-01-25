<?php
require_once '../../../common/config.php';
require_once '../../../common/dbconnect.php';

try {
    $pdo = connect();
    echo "<h2>Data: session_orders</h2>";
    $stmt = $pdo->query("SELECT id, session_id, item_name, created_at FROM session_orders ORDER BY created_at DESC LIMIT 20");
    echo "<table border=1><tr><th>ID</th><th>Session</th><th>Item</th><th>Date</th></tr>";
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "<tr><td>{$row['id']}</td><td>{$row['session_id']}</td><td>{$row['item_name']}</td><td>{$row['created_at']}</td></tr>";
    }
    echo "</table>";
} catch(Exception $e) {
    echo $e->getMessage();
}
