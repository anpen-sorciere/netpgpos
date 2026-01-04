<?php
require_once __DIR__ . '/../../common/config.php';
require_once __DIR__ . '/../../common/dbconnect.php';

echo "=== Schema Check ===\n";

try {
    $pdo = new PDO(
        "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
        $user,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // system_config カラム情報
    echo "--- system_config Columns ---\n";
    $stmt = $pdo->query("DESCRIBE system_config");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $col) {
        echo $col['Field'] . ": " . $col['Type'] . "\n";
    }

    // base_api_tokens カラム情報とデータ長
    echo "\n--- base_api_tokens Data Details ---\n";
    $stmt = $pdo->query("SELECT scope_key, LENGTH(access_token) as v_len, access_token FROM base_api_tokens LIMIT 1");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        echo "Scope: " . $row['scope_key'] . "\n";
        echo "Token Length: " . $row['v_len'] . "\n";
        echo "Token Sample: " . substr($row['access_token'], 0, 20) . "...\n";
    }

    // 暗号化テスト
    echo "\n--- Encryption Test ---\n";
    $key_vals = $pdo->query("SELECT value FROM system_config WHERE key_name='encryption_key'")->fetch();
    $current_key = $key_vals['value'] ?? '';
    echo "Current Key Length: " . strlen($current_key) . "\n";
    
    // 空キーでの挙動確認
    $test_data = "test_string";
    $iv = random_bytes(16);
    // 警告をキャッチするためにエラー制御演算子は使わないが、本番環境設定による
    $bin_key = @hex2bin($current_key); 
    echo "hex2bin(key) result type: " . gettype($bin_key) . "\n";
    
    $encrypted = @openssl_encrypt($test_data, 'AES-256-CBC', $bin_key, 0, $iv);
    echo "Encrypt with current key: " . ($encrypted ? "Success" : "Fail") . "\n";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
