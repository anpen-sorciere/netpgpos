<?php
// DB状態チェック
require_once __DIR__ . '/../../../common/config.php';
require_once __DIR__ . '/../../../common/dbconnect.php';

echo "=== DB Check ===\n";

try {
    $pdo = new PDO(
        "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
        $user,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // system_configテーブル確認
    echo "Checking system_config...\n";
    $stmt = $pdo->query("SHOW TABLES LIKE 'system_config'");
    if ($stmt->rowCount() == 0) {
        echo "[WARNING] system_config table DOES NOT EXIST!\n";
    } else {
        echo "system_config table exists.\n";
        $stmt = $pdo->query("SELECT * FROM system_config");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            echo "Key: " . $row['key_name'] . " (Value length: " . strlen($row['value']) . ")\n";
        }
    }

    // base_api_tokensテーブル確認
    echo "Checking base_api_tokens...\n";
    $stmt = $pdo->query("SELECT * FROM base_api_tokens");
    $tokens = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($tokens as $token) {
        echo "Scope: " . $token['scope_key'] . "\n";
        echo "Updated: " . $token['updated_at'] . "\n";
        echo "Access Expires: " . date('Y-m-d H:i:s', $token['access_expires']) . "\n";
        echo "Refresh Expires: " . date('Y-m-d H:i:s', $token['refresh_expires']) . "\n";
    }

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
