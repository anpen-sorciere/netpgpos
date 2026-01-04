<?php
// base_api_tokensテーブルの中身を確認するスクリプト

// CLI実行時の環境変数エミュレーション
if (php_sapi_name() === 'cli' && !isset($_SERVER['HTTP_HOST'])) {
    $_SERVER['HTTP_HOST'] = 'localhost';
}

require_once __DIR__ . '/../../common/config.php';
require_once __DIR__ . '/../../common/dbconnect.php';

try {
    $pdo = new PDO(
        "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
        $user,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true
        ]
    );
    
    // テーブル存在チェック
    $stmt = $pdo->query("SHOW TABLES LIKE 'base_api_tokens'");
    if ($stmt->rowCount() == 0) {
        echo "テーブル 'base_api_tokens' が存在しません。\n";
        exit;
    }
    
    // データ取得
    $stmt = $pdo->query("SELECT id, scope_key, access_expires, refresh_expires, updated_at FROM base_api_tokens");
    $tokens = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "=== base_api_tokens テーブルの内容 ===\n";
    if (count($tokens) === 0) {
        echo "データがありません。\n";
    } else {
        foreach ($tokens as $token) {
            echo "ID: " . $token['id'] . "\n";
            echo "Scope: " . $token['scope_key'] . "\n";
            echo "Access Expires: " . date('Y-m-d H:i:s', $token['access_expires']) . " (残り " . ($token['access_expires'] - time()) . "秒)\n";
            echo "Refresh Expires: " . date('Y-m-d H:i:s', $token['refresh_expires']) . "\n";
            echo "Updated At: " . $token['updated_at'] . "\n";
            echo "----------------------------------------\n";
        }
    }
    
} catch (PDOException $e) {
    echo "エラー: " . $e->getMessage() . "\n";
}
?>
