<?php
/**
 * 重複データ一括削除スクリプト
 * 
 * 警告: このスクリプトはデータを削除します！
 * 必ず実行前にバックアップを取ってください。
 */

set_time_limit(300);
require_once __DIR__ . '/../../../common/config.php';

$dry_run = !isset($_GET['execute']) || $_GET['execute'] !== 'true';

try {
    $pdo = new PDO(
        "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
        $user,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    echo '<h2>重複データ削除スクリプト</h2>';
    echo '<pre>';
    echo "モード: " . ($dry_run ? "ドライラン（削除しない）" : "本番実行（削除する）") . "\n";
    echo "開始時刻: " . date('Y-m-d H:i:s') . "\n\n";
    
    // STEP 1: 削除前の重複チェック
    echo "=== STEP 1: 削除前の重複チェック ===\n";
    $stmt = $pdo->query("
        SELECT 
            base_order_id, 
            product_id,
            COUNT(*) as count,
            GROUP_CONCAT(id ORDER BY id) as ids
        FROM base_order_items
        GROUP BY base_order_id, product_id
        HAVING count > 1
        ORDER BY count DESC
    ");
    $duplicates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "重複データ: " . count($duplicates) . "組\n\n";
    
    if (count($duplicates) === 0) {
        echo "重複データがありません。\n";
        echo '</pre>';
        exit;
    }
    
    foreach ($duplicates as $dup) {
        echo "注文ID: {$dup['base_order_id']}, 商品ID: {$dup['product_id']}, 件数: {$dup['count']}, IDs: {$dup['ids']}\n";
    }
    
    // STEP 2: 重複データの削除
    echo "\n=== STEP 2: 重複データの削除 ===\n";
    
    if ($dry_run) {
        echo "⚠️ ドライランモードです。実際には削除しません。\n\n";
        
        // 削除されるレコードの確認
        $stmt = $pdo->query("
            SELECT COUNT(*) as delete_count
            FROM base_order_items t1
            INNER JOIN base_order_items t2 
            ON t1.base_order_id = t2.base_order_id 
                AND t1.product_id = t2.product_id 
                AND t1.id > t2.id
        ");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "削除されるレコード数: {$result['delete_count']}件\n";
        
        echo "\n本番実行するには以下のURLを開いてください:\n";
        echo "https://purplelion51.sakura.ne.jp/netpgpos/api/setup/cleanup_duplicate_items.php?execute=true\n";
        
    } else {
        // 実際に削除
        $pdo->beginTransaction();
        
        try {
            $stmt = $pdo->exec("
                DELETE t1 FROM base_order_items t1
                INNER JOIN base_order_items t2 
                WHERE 
                    t1.base_order_id = t2.base_order_id 
                    AND t1.product_id = t2.product_id 
                    AND t1.id > t2.id
            ");
            
            echo "✅ 削除完了: {$stmt}件のレコードを削除しました。\n";
            
            $pdo->commit();
            
        } catch (Exception $e) {
            $pdo->rollBack();
            echo "❌ エラー: " . $e->getMessage() . "\n";
            throw $e;
        }
    }
    
    // STEP 3: 削除後の確認
    echo "\n=== STEP 3: 削除後の確認 ===\n";
    $stmt = $pdo->query("
        SELECT 
            base_order_id, 
            product_id,
            COUNT(*) as count
        FROM base_order_items
        GROUP BY base_order_id, product_id
        HAVING count > 1
    ");
    $remaining = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($remaining) > 0) {
        echo "❌ まだ重複データが残っています: " . count($remaining) . "組\n";
    } else {
        echo "✅ 重複データはありません。\n";
    }
    
    // STEP 4: ユニーク制約の追加
    if (!$dry_run) {
        echo "\n=== STEP 4: ユニーク制約の追加 ===\n";
        
        try {
            $pdo->exec("
                ALTER TABLE base_order_items 
                ADD UNIQUE KEY unique_order_product (base_order_id, product_id)
            ");
            echo "✅ ユニーク制約を追加しました。\n";
            echo "   今後、同じ注文IDと商品IDの組み合わせは重複登録されません。\n";
            
        } catch (PDOException $e) {
            if (strpos($e->getMessage(), 'Duplicate key name') !== false) {
                echo "⚠️ ユニーク制約は既に存在します。\n";
            } else {
                echo "❌ ユニーク制約追加エラー: " . $e->getMessage() . "\n";
            }
        }
    }
    
    echo "\n終了時刻: " . date('Y-m-d H:i:s') . "\n";
    echo '</pre>';
    
} catch (Exception $e) {
    echo '<pre>❌ 致命的エラー: ' . htmlspecialchars($e->getMessage()) . '</pre>';
}
?>
