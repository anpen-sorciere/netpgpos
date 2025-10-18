<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/base_practical_auto_manager.php';

echo '<h1>認証状況直接確認</h1>';

try {
    $practical_manager = new BasePracticalAutoManager();
    $auth_status = $practical_manager->getAuthStatus();
    
    echo '<h2>認証状況</h2>';
    echo '<pre>' . htmlspecialchars(json_encode($auth_status, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . '</pre>';
    
    echo '<h2>個別確認</h2>';
    echo '<p>orders_only: ' . (isset($auth_status['orders_only']['authenticated']) ? ($auth_status['orders_only']['authenticated'] ? '認証済み' : '未認証') : 'データなし') . '</p>';
    echo '<p>items_only: ' . (isset($auth_status['items_only']['authenticated']) ? ($auth_status['items_only']['authenticated'] ? '認証済み' : '未認証') : 'データなし') . '</p>';
    
    echo '<h2>データベース確認</h2>';
    global $host, $user, $password, $dbname;
    $pdo = new PDO(
        "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
        $user,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    $sql = "SELECT scope_key, access_expires, refresh_expires FROM base_api_tokens";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $tokens = $stmt->fetchAll();
    
    echo '<table border="1">';
    echo '<tr><th>scope_key</th><th>access_expires</th><th>refresh_expires</th></tr>';
    foreach ($tokens as $token) {
        echo '<tr>';
        echo '<td>' . htmlspecialchars($token['scope_key']) . '</td>';
        echo '<td>' . htmlspecialchars($token['access_expires']) . '</td>';
        echo '<td>' . htmlspecialchars($token['refresh_expires']) . '</td>';
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
