<?php
// 環境に応じて設定ファイルを選択 (CLI対応)
$local_config = __DIR__ . '/../../../common/config_local.php';
if (file_exists($local_config)) {
    require_once $local_config;
} else {
    require_once __DIR__ . '/../../../common/config.php';
}

// config.php/config_local.phpの変数が使えるはず
// $host, $user, $password, $dbname

// Output buffering to capture content
ob_start();

echo "DB Connection info:\n";
echo "Host: $host\n";
echo "DB: $dbname\n";

try {
    $dsn = "mysql:host={$host};dbname={$dbname};charset=utf8mb4";
    $pdo = new PDO($dsn, $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "Connected successfully.\n\n";

    echo "=== pay_tbl Schema ===\n";
    $stmt = $pdo->query("SHOW CREATE TABLE pay_tbl");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    echo $row['Create Table'] . "\n\n";

    echo "=== cast_mst Triggers ===\n";
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

} catch (PDOException $e) {
    echo "DB Error: " . $e->getMessage() . "\n";
}

$output = ob_get_clean();
file_put_contents(__DIR__ . '/db_output_fixed.txt', $output);
echo "Output written to db_output_fixed.txt\n";

