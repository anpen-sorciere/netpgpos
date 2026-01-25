<?php
require_once '../../common/config.php';
require_once '../../common/dbconnect.php';

try {
    $pdo = connect();
    echo "<h2>Table: base_order_items</h2>";
    $stmt = $pdo->query("DESCRIBE base_order_items");
    echo "<table border=1><tr><th>Field</th><th>Type</th><th>Key</th><th>Extra</th></tr>";
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "<tr><td>{$row['Field']}</td><td>{$row['Type']}</td><td>{$row['Key']}</td><td>{$row['Extra']}</td></tr>";
    }
    echo "</table>";
    
    echo "<h3>Check for duplicate IDs</h3>";
    $dup = $pdo->query("SELECT id, count(*) as c FROM base_order_items GROUP BY id HAVING c > 1 LIMIT 10");
    $dups = $dup->fetchAll(PDO::FETCH_ASSOC);
    if(count($dups) > 0) {
        echo "<b style='color:red'>Duplicate IDs found!</b><br>";
        foreach($dups as $d) {
            echo "ID: {$d['id']} -> {$d['c']} records<br>";
        }
    } else {
        echo "No duplicate IDs found (Good).<br>";
    }

} catch(Exception $e) {
    echo $e->getMessage();
}
