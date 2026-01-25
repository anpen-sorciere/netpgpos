<?php
require_once '../../../common/config.php';
require_once '../../../common/dbconnect.php';

try {
    $pdo = connect();
    echo "<h2>Table: session_orders</h2>";
    $stmt = $pdo->query("DESCRIBE session_orders");
    echo "<table border=1><tr><th>Field</th><th>Type</th><th>Key</th></tr>";
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "<tr><td>{$row['Field']}</td><td>{$row['Type']}</td><td>{$row['Key']}</td></tr>";
    }
    echo "</table>";
} catch(Exception $e) {
    echo $e->getMessage();
}
