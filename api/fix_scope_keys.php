<?php
session_start();
require_once __DIR__ . '/../config.php';

echo '<h1>データベーススコープキー更新</h1>';

try {
    global $host, $user, $password, $dbname;
    $pdo = new PDO(
        "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
        $user,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    echo '<h2>現在のデータベース状況</h2>';
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
    
    echo '<h2>スコープキー更新実行</h2>';
    
    // orders_only を read_orders に更新
    $sql = "UPDATE base_api_tokens SET scope_key = 'read_orders' WHERE scope_key = 'orders_only'";
    $stmt = $pdo->prepare($sql);
    $result1 = $stmt->execute();
    echo '<p>orders_only → read_orders: ' . ($result1 ? '成功' : '失敗') . '</p>';
    
    // items_only を read_items に更新
    $sql = "UPDATE base_api_tokens SET scope_key = 'read_items' WHERE scope_key = 'items_only'";
    $stmt = $pdo->prepare($sql);
    $result2 = $stmt->execute();
    echo '<p>items_only → read_items: ' . ($result2 ? '成功' : '失敗') . '</p>';
    
    echo '<h2>更新後のデータベース状況</h2>';
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
    
    echo '<h2>完了</h2>';
    echo '<p>データベースのスコープキーをBASE APIの実際のスコープに更新しました。</p>';
    echo '<p><a href="order_monitor.php">注文監視システムに戻る</a></p>';
    
} catch (Exception $e) {
    echo '<h2>エラー</h2>';
    echo '<p>エラー: ' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '<p>ファイル: ' . htmlspecialchars($e->getFile()) . '</p>';
    echo '<p>行: ' . htmlspecialchars($e->getLine()) . '</p>';
}
?>
