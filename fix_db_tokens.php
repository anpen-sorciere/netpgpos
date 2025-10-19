<?php
require_once 'config.php';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    echo "<h2>BASE API トークン修正</h2>";
    
    // read_itemsスコープの壊れたレコードを削除
    echo "<h3>1. 壊れたread_itemsレコードを削除</h3>";
    $stmt = $pdo->prepare("DELETE FROM base_api_tokens WHERE scope LIKE '%read_items%' AND scope != 'read_items'");
    $stmt->execute();
    $deleted_count = $stmt->rowCount();
    echo "<p>削除されたレコード数: " . $deleted_count . "</p>";
    
    // 正しいread_itemsレコードが存在するかチェック
    echo "<h3>2. 正しいread_itemsレコードをチェック</h3>";
    $stmt = $pdo->prepare("SELECT * FROM base_api_tokens WHERE scope = 'read_items'");
    $stmt->execute();
    $correct_tokens = $stmt->fetchAll();
    
    if (empty($correct_tokens)) {
        echo "<p style='color: red;'>正しいread_itemsレコードが存在しません。</p>";
        echo "<p>手動で認証を実行してください。</p>";
    } else {
        echo "<p style='color: green;'>正しいread_itemsレコードが存在します。</p>";
        foreach ($correct_tokens as $token) {
            echo "<div style='border: 1px solid #ccc; padding: 10px; margin: 10px 0;'>";
            echo "<strong>ID:</strong> " . htmlspecialchars($token['id']) . "<br>";
            echo "<strong>Scope:</strong> " . htmlspecialchars($token['scope']) . "<br>";
            echo "<strong>Access Token:</strong> " . htmlspecialchars(substr($token['access_token'], 0, 20)) . "...<br>";
            echo "<strong>Expires At:</strong> " . htmlspecialchars($token['expires_at']) . "<br>";
            echo "</div>";
        }
    }
    
    // 全レコードを再表示
    echo "<h3>3. 修正後の全レコード</h3>";
    $stmt = $pdo->prepare("SELECT id, scope, expires_at, created_at FROM base_api_tokens ORDER BY created_at DESC");
    $stmt->execute();
    $all_tokens = $stmt->fetchAll();
    
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>ID</th><th>Scope</th><th>Expires At</th><th>Created At</th></tr>";
    
    foreach ($all_tokens as $token) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($token['id']) . "</td>";
        echo "<td>" . htmlspecialchars($token['scope']) . "</td>";
        echo "<td>" . htmlspecialchars($token['expires_at']) . "</td>";
        echo "<td>" . htmlspecialchars($token['created_at']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<h3>4. 次のステップ</h3>";
    echo "<p><a href='api/order_monitor.php' style='background-color: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px;'>注文監視に戻る</a></p>";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>データベースエラー: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>
