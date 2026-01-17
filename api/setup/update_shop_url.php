<?php
/**
 * shop_mst の base_redirect_uri を一括更新するツール
 */
require_once __DIR__ . '/../../common/config.php';

// 更新するURL (新ツールのパス)
// 現在のスクリプトの場所から shop_auth.php のURLを推測または固定指定
// ユーザーの環境に合わせて絶対パスを構築
$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? "https" : "http";
$host_part = $_SERVER['HTTP_HOST'];
$dir = dirname($_SERVER['SCRIPT_NAME']); // /netpgpos/api/setup
$new_uri = $protocol . "://" . $host_part . $dir . "/shop_auth.php";

// プロキシ等で正しく取れない場合のためのハードコード（今回はユーザー提示のURLを使用）
// $new_uri = "https://purplelion51.sakura.ne.jp/netpgpos/api/setup/shop_auth.php";

echo "<h2>Redirect URI Update Tool</h2>";
echo "<p>New Redirect URI: <strong>" . htmlspecialchars($new_uri) . "</strong></p>";

try {
    $pdo = new PDO(
        "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
        $user,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // 更新実行 (shop_id 1と2、あるいは全店舗)
    // BASE連携が有効になっている、またはClient IDが入っている店舗を対象にする
    $sql = "UPDATE shop_mst 
            SET base_redirect_uri = :uri 
            WHERE base_client_id IS NOT NULL AND base_client_id != ''";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':uri' => $new_uri]);
    $count = $stmt->rowCount();

    echo "<p style='color:green;'>Success! Updated {$count} shops.</p>";
    
    // 結果確認
    echo "<h3>Current Settings:</h3>";
    $stmt = $pdo->query("SELECT shop_id, shop_name, base_redirect_uri FROM shop_mst");
    echo "<table border='1' cellpadding='5'><tr><th>ID</th><th>Name</th><th>Redirect URI</th></tr>";
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "<tr>";
        echo "<td>{$row['shop_id']}</td>";
        echo "<td>" . htmlspecialchars($row['shop_name']) . "</td>";
        echo "<td>" . htmlspecialchars($row['base_redirect_uri']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";

} catch (PDOException $e) {
    echo "<p style='color:red;'>Database Error: " . $e->getMessage() . "</p>";
}
?>
<p><a href="shop_auth.php">店舗別認証ツールへ移動</a></p>
