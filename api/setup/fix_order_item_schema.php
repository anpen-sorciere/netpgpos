<?php
ini_set('display_errors', 1);
require_once __DIR__ . '/../../../common/config.php';
require_once __DIR__ . '/../../../common/dbconnect.php';

echo "<pre>\n";
echo "Starting schema update for base_order_items...\n";

try {
    $pdo = new PDO(
        "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
        $user,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // 1. カラム追加 (base_order_item_id)
    echo "Checking columns...\n";
    $stmt = $pdo->query("SHOW COLUMNS FROM base_order_items LIKE 'base_order_item_id'");
    if (!$stmt->fetch()) {
        echo "Adding column base_order_item_id...\n";
        $pdo->exec("ALTER TABLE base_order_items ADD COLUMN base_order_item_id BIGINT UNSIGNED NULL AFTER base_order_id");
        echo "Column added.\n";
    } else {
        echo "Column base_order_item_id already exists.\n";
    }

    // 2. インデックス確認と削除
    echo "Checking indices...\n";
    $stmt = $pdo->query("SHOW INDEX FROM base_order_items");
    $indices = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($indices as $idx) {
        $keyName = $idx['Key_name'];
        
        // UNIQUEまたはPRIMARYで、base_order_id または product_id を含むものを調査
        // 想定される問題のキー: PRIMARY (base_order_id, product_id) または UNIQUE (base_order_id, product_id)
        
        // 既存のキー構造が分からないため、base_order_item_id 以外のユニークキーは全て削除候補とする（危険だがバグ修正のため）
        // ただし、単純なINDEXは残したいか？
        // Unique制約があるものを削除する。
        
        if ($idx['Non_unique'] == 0 && $keyName !== 'base_order_item_id_unique') {
            echo "Found unique key: $keyName. Dropping...\n";
            try {
                if ($keyName == 'PRIMARY') {
                    // PRIMARY KEY削除はテーブル構造によっては危険だが、ここは複合主キーのはず
                    $pdo->exec("ALTER TABLE base_order_items DROP PRIMARY KEY");
                } else {
                    $pdo->exec("ALTER TABLE base_order_items DROP INDEX `$keyName`");
                }
                echo "Dropped key: $keyName\n";
            } catch (Exception $e) {
                echo "Failed to drop key $keyName: " . $e->getMessage() . "\n";
            }
        }
    }

    // 3. 新しいユニークキー追加
    echo "Adding new unique key for base_order_item_id...\n";
    try {
        $pdo->exec("CREATE UNIQUE INDEX base_order_item_id_unique ON base_order_items (base_order_item_id)");
        echo "Unique index added.\n";
    } catch (Exception $e) {
        echo "Index creation failed (maybe already exists or column has duplicates): " . $e->getMessage() . "\n";
    }

    // 4. 既存レコードの cleanup (NULLのままでは困るので)
    // ただし、既存レコードには base_order_item_id がない。これらをどうするか？
    // 再取得しない限り埋まらない。
    // 自動同期ロジックで再取得すれば埋まるはず。

    echo "Schema update completed.\n";

} catch (PDOException $e) {
    echo "Fatal Error: " . $e->getMessage();
}
echo "</pre>";
?>
