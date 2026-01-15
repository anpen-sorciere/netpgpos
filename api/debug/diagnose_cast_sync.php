<?php
// キャストID紐付け診断スクリプト
require_once __DIR__ . '/../../../common/config.php';

try {
    $pdo = new PDO(
        "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
        $user,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    echo '<h2>キャストID紐付け診断</h2>';
    echo '<pre>';
    
    // 1. テーブル構造確認
    echo "=== 1. base_order_items テーブル構造 ===\n";
    $stmt = $pdo->query("SHOW COLUMNS FROM base_order_items");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $has_cast_id = false;
    foreach ($columns as $col) {
        if ($col['Field'] === 'cast_id') {
            $has_cast_id = true;
            echo "✅ cast_id カラムが存在します: {$col['Type']} {$col['Null']} {$col['Key']}\n";
        }
    }
    if (!$has_cast_id) {
        echo "❌ cast_id カラムが存在しません！\n";
        echo "→ テーブル作成時にcast_idカラムが追加されていない可能性があります\n";
    }
    
    // 2. cast_mst の状況
    echo "\n=== 2. cast_mst テーブルの状況 ===\n";
    $stmt = $pdo->query("SELECT COUNT(*) FROM cast_mst WHERE drop_flg = 0");
    $cast_count = $stmt->fetchColumn();
    echo "登録キャスト数: {$cast_count}名\n";
    
    if ($cast_count > 0) {
        $stmt = $pdo->query("SELECT cast_id, cast_name FROM cast_mst WHERE drop_flg = 0 LIMIT 10");
        $casts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "\nキャスト一覧:\n";
        foreach ($casts as $cast) {
            echo "  ID:{$cast['cast_id']} - {$cast['cast_name']}\n";
        }
    } else {
        echo "❌ 登録キャストが0名です！\n";
        echo "→ cast_mstテーブルにキャストを登録する必要があります\n";
    }
    
    // 3. base_order_items のサンプルデータ確認
    echo "\n=== 3. base_order_items サンプルデータ ===\n";
    $stmt = $pdo->query("
        SELECT 
            base_order_id, 
            product_name, 
            cast_id, 
            customer_name_from_option, 
            item_surprise_date 
        FROM base_order_items 
        LIMIT 5
    ");
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($items) > 0) {
        foreach ($items as $item) {
            echo "注文ID: {$item['base_order_id']}\n";
            echo "  商品名: {$item['product_name']}\n";
            echo "  cast_id: " . ($item['cast_id'] ?? 'NULL') . "\n";
            echo "  お客様名: " . ($item['customer_name_from_option'] ?? 'NULL') . "\n";
            echo "  サプライズ日: " . ($item['item_surprise_date'] ?? 'NULL') . "\n";
            echo "  ---\n";
        }
    } else {
        echo "データがありません\n";
    }
    
    // 4. 最新の注文詳細をBASE APIで確認（API制限後に実行）
    echo "\n=== 4. API制限リセット後の確認方法 ===\n";
    echo "08:00以降に以下のURLを開いてください:\n";
    echo "http://localhost/netpgpos/api/debug/check_order_structure.php\n";
    echo "\n";
    echo "これでBASE APIの/ordersレスポンスに実際にorder_itemsとoptionsが\n";
    echo "含まれているかを確認できます。\n";
    
    // 5. 推奨される対応
    echo "\n=== 5. 対応方法 ===\n";
    
    if (!$has_cast_id) {
        echo "❌ STEP 1: cast_idカラムを追加\n";
        echo "   ALTER TABLE base_order_items ADD COLUMN cast_id INT NULL;\n\n";
    } else {
        echo "✅ STEP 1: cast_idカラムは存在します\n\n";
    }
    
    if ($cast_count === 0) {
        echo "❌ STEP 2: cast_mstにキャストを登録\n";
        echo "   キャスト管理画面からキャストを追加してください\n\n";
    } else {
        echo "✅ STEP 2: キャストは登録されています ({$cast_count}名)\n\n";
    }
    
    echo "⏰ STEP 3: API制限リセット後（08:00以降）\n";
    echo "   order_monitor.phpを開いて差分同期を実行\n";
    echo "   → 未保存の注文の詳細を取得してキャストID紐付け\n\n";
    
    // 6. 既存データの再同期が必要かチェック
    echo "=== 6. 既存データの状況 ===\n";
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN cast_id IS NOT NULL THEN 1 ELSE 0 END) as with_cast,
            SUM(CASE WHEN customer_name_from_option IS NOT NULL THEN 1 ELSE 0 END) as with_customer,
            SUM(CASE WHEN item_surprise_date IS NOT NULL THEN 1 ELSE 0 END) as with_surprise
        FROM base_order_items
    ");
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo "全商品: {$stats['total']}件\n";
    echo "cast_id付き: {$stats['with_cast']}件\n";
    echo "customer_name_from_option付き: {$stats['with_customer']}件\n";
    echo "item_surprise_date付き: {$stats['with_surprise']}件\n\n";
    
    if ($stats['with_customer'] === 0 && $stats['with_surprise'] === 0) {
        echo "❌ 既存データには詳細情報（options）が含まれていません\n";
        echo "→ 過去データは/ordersのヘッダー情報のみで保存されています\n";
        echo "→ 08:00以降に全データを再同期する必要があります\n";
    } else {
        echo "✅ 一部の商品には詳細情報があります\n";
    }
    
    echo '</pre>';
    
} catch (PDOException $e) {
    echo '<pre>❌ エラー: ' . htmlspecialchars($e->getMessage()) . '</pre>';
}
?>
