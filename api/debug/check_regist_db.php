<?php
require_once __DIR__ . '/../../../common/config.php';
require_once __DIR__ . '/../../../common/dbconnect.php';

$pdo = connect();

echo "=== pay_tbl Schema ===\n";
try {
    $stmt = $pdo->query("SHOW CREATE TABLE pay_tbl");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    echo $row['Create Table'] . "\n\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n\n";
}

echo "=== cast_mst Triggers ===\n";
try {
    $stmt = $pdo->query("SHOW TRIGGERS LIKE 'cast_mst'");
    $triggers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (empty($triggers)) {
        echo "No triggers found for cast_mst.\n";
    } else {
        foreach ($triggers as $trigger) {
            echo "Trigger: " . $trigger['Trigger'] . "\n";
            echo "Event: " . $trigger['Event'] . "\n";
            echo "Timing: " . $trigger['Timing'] . "\n";
            echo "Statement: " . $trigger['Statement'] . "\n\n";
        }
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n\n";
}
