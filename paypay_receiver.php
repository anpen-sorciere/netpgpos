<?php
// paypay_receiver.php
// GmailからGAS経由でPayPay売上データを受信し、DBに登録する

// 共通のDB接続を使用
require_once __DIR__ . '/../common/dbconnect.php';

// JSONデータを受信
$json = file_get_contents('php://input');
$data = json_decode($json, true);

// 認証キー（GAS側と一致させる）
$auth_key = 'paypay_auth_token_2026';

// 認証チェック
if (!$data || !isset($data['key']) || $data['key'] !== $auth_key) {
    header('HTTP/1.1 403 Forbidden');
    exit('Unauthorized');
}

// 必須項目チェック
if (!isset($data['transaction_id']) || !isset($data['settled_at']) || !isset($data['shop_id'])) {
    header('HTTP/1.1 400 Bad Request');
    exit('Missing required fields: transaction_id, settled_at, shop_id');
}

// データベースへの登録
try {
    $pdo = connect();
    if ($pdo === null) {
        throw new Exception('データベース接続に失敗しました');
    }
    
    // PayPay売上テーブルへINSERT（重複は無視）
    $sql = "INSERT IGNORE INTO paypay_sales (transaction_id, settled_at, shop_id, amount) VALUES (?, ?, ?, ?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $data['transaction_id'],
        $data['settled_at'],
        $data['shop_id'],
        $data['amount'] ?? null
    ]);
    
    if ($stmt->rowCount() > 0) {
        echo "OK: Inserted";
    } else {
        echo "OK: Duplicate (skipped)";
    }
} catch (Exception $e) {
    // エラーログを記録
    $log_file = __DIR__ . '/logs/paypay_error.log';
    $log_dir = dirname($log_file);
    if (!file_exists($log_dir)) {
        @mkdir($log_dir, 0777, true);
    }
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[{$timestamp}] " . $e->getMessage() . "\n", FILE_APPEND);
    
    header('HTTP/1.1 500 Internal Server Error');
    echo "Error: " . $e->getMessage();
}
