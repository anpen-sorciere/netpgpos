<?php
// 稼働DBから全テーブルのCREATE文を収集し、schema.sql を生成
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config.php';

header('Content-Type: text/plain; charset=UTF-8');

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    // テーブル一覧取得
    $tables = [];
    foreach ($pdo->query('SHOW TABLES') as $row) {
        $tables[] = array_values($row)[0];
    }

    $out = "-- netpgpos schema export\n-- generated at " . date('c') . "\n\nSET FOREIGN_KEY_CHECKS=0;\n\n";

    foreach ($tables as $t) {
        // CREATE TABLE
        $stmt = $pdo->query("SHOW CREATE TABLE `{$t}`");
        $create = $stmt->fetch(PDO::FETCH_NUM);
        if ($create && isset($create[1])) {
            $out .= "-- -----------------------------\n";
            $out .= "-- Table structure for `{$t}`\n";
            $out .= "-- -----------------------------\n";
            $out .= $create[1] . ";\n\n";
        }
    }

    $out .= "SET FOREIGN_KEY_CHECKS=1;\n";

    // 保存先
    $schemaPath = __DIR__ . '/schema.sql';
    file_put_contents($schemaPath, $out);

    echo "Exported schema to: " . realpath($schemaPath) . "\n";
    echo "Tables: " . implode(', ', $tables) . "\n";

} catch (Throwable $e) {
    http_response_code(500);
    echo "Export failed: " . $e->getMessage() . "\n";
}
?>

