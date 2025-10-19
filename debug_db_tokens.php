<?php
require_once 'config.php';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    echo "<h2>BASE API トークンテーブル確認</h2>";
    
    // 全レコードを表示
    $stmt = $pdo->prepare("SELECT * FROM base_api_tokens ORDER BY created_at DESC");
    $stmt->execute();
    $tokens = $stmt->fetchAll();
    
    echo "<h3>全トークンレコード:</h3>";
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>ID</th><th>Scope</th><th>Access Token</th><th>Refresh Token</th><th>Expires At</th><th>Created At</th></tr>";
    
    foreach ($tokens as $token) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($token['id']) . "</td>";
        echo "<td>" . htmlspecialchars($token['scope']) . "</td>";
        echo "<td>" . htmlspecialchars(substr($token['access_token'], 0, 20)) . "...</td>";
        echo "<td>" . htmlspecialchars(substr($token['refresh_token'], 0, 20)) . "...</td>";
        echo "<td>" . htmlspecialchars($token['expires_at']) . "</td>";
        echo "<td>" . htmlspecialchars($token['created_at']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // read_itemsスコープのレコードを特定
    echo "<h3>read_itemsスコープのレコード:</h3>";
    $stmt = $pdo->prepare("SELECT * FROM base_api_tokens WHERE scope LIKE '%read_items%'");
    $stmt->execute();
    $read_items_tokens = $stmt->fetchAll();
    
    if (empty($read_items_tokens)) {
        echo "<p style='color: red;'>read_itemsスコープのレコードが見つかりません。</p>";
    } else {
        foreach ($read_items_tokens as $token) {
            echo "<div style='border: 1px solid #ccc; padding: 10px; margin: 10px 0;'>";
            echo "<strong>ID:</strong> " . htmlspecialchars($token['id']) . "<br>";
            echo "<strong>Scope:</strong> " . htmlspecialchars($token['scope']) . "<br>";
            echo "<strong>Access Token:</strong> " . htmlspecialchars($token['access_token']) . "<br>";
            echo "<strong>Refresh Token:</strong> " . htmlspecialchars($token['refresh_token']) . "<br>";
            echo "<strong>Expires At:</strong> " . htmlspecialchars($token['expires_at']) . "<br>";
            echo "<strong>Created At:</strong> " . htmlspecialchars($token['created_at']) . "<br>";
            echo "</div>";
        }
    }
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>データベースエラー: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>
