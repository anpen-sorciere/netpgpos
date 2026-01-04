<?php
// CRONでのDB書き込みテスト用
// User table: debug_db_test (id int NOT NULL, test_text varchar(20), test_int int, ...)

// CLI環境変数のシミュレーション
if (php_sapi_name() === 'cli' && !isset($_SERVER['HTTP_HOST'])) {
    $_SERVER['HTTP_HOST'] = 'localhost';
}

require_once __DIR__ . '/../../common/config.php';
require_once __DIR__ . '/../../common/dbconnect.php';

$log_file_ok = __DIR__ . '/cron_db_test_success.log';
$log_file_ng = __DIR__ . '/cron_db_test_error.log';

try {
    // dbconnect.php で $pdo または $db が生成されていると仮定
    // ここでは念のため再度ネイティブ接続を試みるのではなく、環境変数を使った接続確認を行う
    
    // config.php, dbconnect.php で定義されている変数を使う
    // もし dbconnect.php が $pdo オブジェクトを作らないタイプなら、ここで作る
    // netpgposのcommon/dbconnect.phpはPDOを作成すると想定
    
    // 既存のPDOインスタンスを探す（変数が不明なため、configから再作成が確実）
    if (!isset($pdo) && isset($host, $dbname, $user, $password)) {
        $dsn = "mysql:host={$host};dbname={$dbname};charset=utf8mb4";
        $pdo = new PDO($dsn, $user, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    }

    if (!isset($pdo)) {
        throw new Exception("PDO object not found and connection details missing.");
    }

    // テストデータ挿入
    // idにauto_incrementがない可能性を考慮してtime()を使用
    $id = time(); 
    $text = "CRON_TEST_OK";
    $num = rand(100, 999);

    $sql = "INSERT INTO debug_db_test (id, test_text, test_int) VALUES (:id, :text, :num)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id' => $id, ':text' => $text, ':num' => $num]);

    $msg = date('Y-m-d H:i:s') . " Insert Success: ID={$id}\n";
    echo $msg;
    file_put_contents($log_file_ok, $msg, FILE_APPEND);

} catch (Exception $e) {
    $msg = date('Y-m-d H:i:s') . " Error: " . $e->getMessage() . "\n";
    echo $msg;
    file_put_contents($log_file_ng, $msg, FILE_APPEND);
}
?>
