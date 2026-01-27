<?php
// paypay_sales テーブル作成スクリプト
// ブラウザからアクセスして実行してください

require_once __DIR__ . '/../common/dbconnect.php';

echo "<h2>PayPay Sales テーブル作成</h2>";

try {
    $pdo = connect();
    if ($pdo === null) {
        throw new Exception('データベース接続に失敗しました');
    }
    
    // テーブル作成SQL
    $sql = "
    CREATE TABLE IF NOT EXISTS paypay_sales (
        id INT AUTO_INCREMENT PRIMARY KEY COMMENT 'ID',
        transaction_id VARCHAR(20) NOT NULL UNIQUE COMMENT '取引番号(20桁)',
        settled_at DATETIME NOT NULL COMMENT '決済日時',
        shop_id INT NOT NULL COMMENT '決済店舗(shop_mst.shop_id)',
        cast_id INT DEFAULT NULL COMMENT '売上キャストID(cast_mst.cast_id)',
        handled_flg TINYINT DEFAULT NULL COMMENT '対応済フラグ',
        amount INT DEFAULT NULL COMMENT '金額',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '登録日時',
        INDEX idx_shop_id (shop_id),
        INDEX idx_cast_id (cast_id),
        INDEX idx_settled_at (settled_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='PayPay売上データ'
    ";
    
    $pdo->exec($sql);
    
    echo "<p style='color: green; font-weight: bold;'>✅ paypay_sales テーブルを作成しました（または既に存在します）</p>";
    
    // テーブル構造を確認
    echo "<h3>テーブル構造:</h3>";
    echo "<table border='1' cellpadding='5' cellspacing='0'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    
    $stmt = $pdo->query("DESCRIBE paypay_sales");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['Field']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Key']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Default'] ?? 'NULL') . "</td>";
        echo "<td>" . htmlspecialchars($row['Extra']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<p><strong>注意:</strong> このファイルは実行後に削除してください。</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ エラー: " . htmlspecialchars($e->getMessage()) . "</p>";
}
