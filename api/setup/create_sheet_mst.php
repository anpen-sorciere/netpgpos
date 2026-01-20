<?php
/**
 * sheet_mst (座席マスタ) 作成用スクリプト
 */
require_once __DIR__ . '/../../../common/config.php';
require_once __DIR__ . '/../../../common/dbconnect.php';

try {
    $pdo = connect();
    echo "Connected to database.<br>";

    // テーブル作成
    $sql = "CREATE TABLE IF NOT EXISTS sheet_mst (
        sheet_id INT AUTO_INCREMENT PRIMARY KEY,
        shop_id INT NOT NULL,
        sheet_name VARCHAR(50) NOT NULL,
        x_pos INT DEFAULT 0 COMMENT 'X座標(%)',
        y_pos INT DEFAULT 0 COMMENT 'Y座標(%)',
        width INT DEFAULT 10 COMMENT '幅(%)',
        height INT DEFAULT 10 COMMENT '高さ(%)',
        type VARCHAR(20) DEFAULT 'rect' COMMENT '形状(rect, circle)',
        display_order INT DEFAULT 0,
        is_active TINYINT DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_shop_id (shop_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

    $pdo->exec($sql);
    echo "Table 'sheet_mst' created or already exists.<br>";

    // 初期データ投入 (既にデータがある場合はスキップ)
    $stmt = $pdo->query("SELECT COUNT(*) FROM sheet_mst");
    $count = $stmt->fetchColumn();

    if ($count == 0) {
        echo "Inserting initial data...<br>";
        
        $shops = [1024, 2, 3]; // 店舗IDリスト
        
        $insertStmt = $pdo->prepare("INSERT INTO sheet_mst (shop_id, sheet_name, x_pos, y_pos, width, height, type, display_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");

        foreach ($shops as $shopId) {
            // カウンター席 (上部に横並び)
            for ($i = 1; $i <= 5; $i++) {
                $insertStmt->execute([
                    $shopId,
                    "C{$i}", // Counter
                    5 + ($i * 12), // x
                    10,            // y
                    10,            // w
                    10,            // h
                    'circle',      // type
                    $i             // order
                ]);
            }

            // テーブル席 (下部に配置)
            $tables = ['A', 'B', 'C'];
            foreach ($tables as $idx => $t) {
                $insertStmt->execute([
                    $shopId,
                    "Table {$t}",
                    10 + ($idx * 30), // x
                    40,               // y
                    25,               // w
                    20,               // h
                    'rect',           // type
                    10 + $idx         // order
                ]);
            }
        }
        echo "Initial data inserted.<br>";
    } else {
        echo "Data already exists. Skipping insertion.<br>";
    }

    echo "<strong>Setup completed successfully.</strong>";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage() . "<br>";
}
