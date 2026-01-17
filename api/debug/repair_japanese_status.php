<?php
// 日本語ステータス修復ツール
// 誤って保存された「対応中」「対応済」などの日本語ステータスを、正しいBASE APIコードに変換します。

require_once __DIR__ . '/../../common/config.php';
require_once __DIR__ . '/../../common/dbconnect.php';

echo "<h1>Repair Japanese Status Tool</h1>";

try {
    $pdo = new PDO(
        "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
        $user,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // 現状の確認（日本語ステータスの件数）
    $sqlCheck = "
        SELECT status, COUNT(*) as cnt 
        FROM base_orders 
        WHERE status IN ('対応中', '対応済', '注文済み') 
        GROUP BY status
    ";
    $stmt = $pdo->query($sqlCheck);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<h3>現在の不正データ件数</h3>";
    if (empty($rows)) {
        echo "<p>日本語ステータスのデータは見つかりませんでした。正常です。</p>";
    } else {
        echo "<ul>";
        foreach ($rows as $row) {
            echo "<li>{$row['status']}: <strong>{$row['cnt']}</strong> 件</li>";
        }
        echo "</ul>";
        
        // 修復実行ボタン
        if (!isset($_POST['execute'])) {
            echo '<form method="post"><button type="submit" name="execute" style="padding:10px 20px; background:red; color:white;">修復を実行する</button></form>';
        }
    }

    // 実行処理
    if (isset($_POST['execute'])) {
        echo "<h3>実行ログ</h3>";
        
        // 1. 対応中 -> ordered
        $sql1 = "UPDATE base_orders SET status = 'ordered' WHERE status = '対応中'";
        $stmt1 = $pdo->prepare($sql1);
        $stmt1->execute();
        echo "UPDATE '対応中' -> 'ordered': <strong>" . $stmt1->rowCount() . "</strong> 件<br>";

        // 2. 注文済み -> ordered
        $sql2 = "UPDATE base_orders SET status = '注文済み' WHERE status = '注文済み'"; // WHERE句修正
        $sql2 = "UPDATE base_orders SET status = 'ordered' WHERE status = '注文済み'";
        $stmt2 = $pdo->prepare($sql2);
        $stmt2->execute();
        echo "UPDATE '注文済み' -> 'ordered': <strong>" . $stmt2->rowCount() . "</strong> 件<br>";

        // 3. 対応済 -> dispatched
        $sql3 = "UPDATE base_orders SET status = 'dispatched' WHERE status = '対応済'";
        $stmt3 = $pdo->prepare($sql3);
        $stmt3->execute();
        echo "UPDATE '対応済' -> 'dispatched': <strong>" . $stmt3->rowCount() . "</strong> 件<br>";
        
        echo "<h3 style='color:green'>修復完了しました。</h3>";
        echo "<p>ダッシュボードを確認してください。</p>";
    }

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
