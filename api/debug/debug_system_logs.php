<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/base_practical_auto_manager.php';

echo '<h1>システムログ確認</h1>';

try {
    global $host, $user, $password, $dbname;
    $pdo = new PDO(
        "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
        $user,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    echo '<h2>最新のシステムログ（20件）</h2>';
    $sql = "SELECT * FROM system_logs ORDER BY created_at DESC LIMIT 20";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $logs = $stmt->fetchAll();
    
    echo '<table border="1" style="border-collapse: collapse; width: 100%;">';
    echo '<tr><th>日時</th><th>イベントタイプ</th><th>メッセージ</th><th>詳細</th></tr>';
    foreach ($logs as $log) {
        echo '<tr>';
        echo '<td>' . htmlspecialchars($log['created_at']) . '</td>';
        echo '<td>' . htmlspecialchars($log['event_type']) . '</td>';
        echo '<td>' . htmlspecialchars($log['message']) . '</td>';
        echo '<td>' . htmlspecialchars($log['details'] ?? '') . '</td>';
        echo '</tr>';
    }
    echo '</table>';
    
    echo '<h2>データベースのトークン状況</h2>';
    $sql = "SELECT scope_key, access_expires, refresh_expires FROM base_api_tokens";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $tokens = $stmt->fetchAll();
    
    echo '<table border="1" style="border-collapse: collapse;">';
    echo '<tr><th>scope_key</th><th>access_expires</th><th>refresh_expires</th><th>現在時刻</th></tr>';
    $current_time = time();
    foreach ($tokens as $token) {
        echo '<tr>';
        echo '<td>' . htmlspecialchars($token['scope_key']) . '</td>';
        echo '<td>' . htmlspecialchars($token['access_expires']) . '</td>';
        echo '<td>' . htmlspecialchars($token['refresh_expires']) . '</td>';
        echo '<td>' . date('Y-m-d H:i:s', $current_time) . '</td>';
        echo '</tr>';
    }
    echo '</table>';
    
} catch (Exception $e) {
    echo '<h2>エラー</h2>';
    echo '<p>エラー: ' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '<p>ファイル: ' . htmlspecialchars($e->getFile()) . '</p>';
    echo '<p>行: ' . htmlspecialchars($e->getLine()) . '</p>';
}
?>
