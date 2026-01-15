<?php
// 特定注文IDの詳細調査
require_once __DIR__ . '/../../../common/config.php';

session_start();
$logged_in_cast_id = $_SESSION['cast_id'] ?? null;

try {
    $pdo = new PDO(
        "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
        $user,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    echo '<h2>注文ID調査</h2>';
    echo '<pre>';
    
    $target_order_id = '1DAAC85F63A55F7E';
    
    echo "調査対象: {$target_order_id}\n";
    echo "ログイン中のキャストID: " . ($logged_in_cast_id ?? '未ログイン') . "\n\n";
    
    // 1. base_ordersの情報
    echo "=== base_orders ===\n";
    $stmt = $pdo->prepare("SELECT * FROM base_orders WHERE base_order_id = ?");
    $stmt->execute([$target_order_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($order) {
        echo "注文日: {$order['order_date']}\n";
        echo "顧客名: {$order['customer_name']}\n";
        echo "合計金額: {$order['total_amount']}\n";
        echo "ステータス: {$order['status']}\n";
        echo "is_surprise: {$order['is_surprise']}\n";
        echo "surprise_date: " . ($order['surprise_date'] ?? 'NULL') . "\n";
    } else {
        echo "❌ base_ordersに該当データなし\n";
    }
    
    // 2. base_order_itemsの情報
    echo "\n=== base_order_items ===\n";
    $stmt = $pdo->prepare("SELECT * FROM base_order_items WHERE base_order_id = ?");
    $stmt->execute([$target_order_id]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "件数: " . count($items) . "件\n\n";
    
    if (count($items) > 0) {
        foreach ($items as $idx => $item) {
            echo "--- 商品 " . ($idx + 1) . " ---\n";
            echo "ID: {$item['id']}\n";
            echo "product_id: {$item['product_id']}\n";
            echo "product_name: {$item['product_name']}\n";
            echo "cast_id: " . ($item['cast_id'] ?? 'NULL') . " ← ";
            
            if ($item['cast_id'] === null) {
                echo "❌ cast_idがNULL（表示されない原因）\n";
            } elseif ($logged_in_cast_id && $item['cast_id'] == $logged_in_cast_id) {
                echo "✅ ログイン中のキャストと一致\n";
            } elseif ($logged_in_cast_id) {
                echo "⚠️ 別のキャスト（ID:{$item['cast_id']}）に紐付いている\n";
            } else {
                echo "cast_id設定済み\n";
            }
            
            echo "customer_name_from_option: " . ($item['customer_name_from_option'] ?? 'NULL') . "\n";
            echo "item_surprise_date: " . ($item['item_surprise_date'] ?? 'NULL');
            
            // サプライズ日付チェック
            if ($item['item_surprise_date']) {
                $today = date('Y-m-d');
                if ($item['item_surprise_date'] > $today) {
                    echo " ← ⚠️ 未来の日付（表示されない原因）\n";
                } else {
                    echo " ← ✅ 過去または今日\n";
                }
            } else {
                echo "\n";
            }
            echo "\n";
        }
    } else {
        echo "❌ base_order_itemsに該当データなし（表示されない原因）\n";
    }
    
    // 3. ダッシュボードのSQLで取得できるかテスト
    if ($logged_in_cast_id) {
        echo "\n=== ダッシュボードSQLテスト ===\n";
        $sql = "
            SELECT 
                o.base_order_id,
                o.order_date,
                o.customer_name,
                o.total_amount,
                o.status,
                COUNT(oi.id) as item_count,
                MIN(oi.item_surprise_date) as earliest_surprise_date
            FROM base_orders o
            INNER JOIN base_order_items oi ON o.base_order_id = oi.base_order_id
            WHERE oi.cast_id = :cast_id
            AND o.base_order_id = :order_id
            GROUP BY o.base_order_id
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':cast_id' => $logged_in_cast_id,
            ':order_id' => $target_order_id
        ]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            echo "✅ ダッシュボードSQLで取得可能\n";
            echo "earliest_surprise_date: " . ($result['earliest_surprise_date'] ?? 'NULL') . "\n";
            
            // サプライズフィルターチェック
            if ($result['earliest_surprise_date']) {
                $today = date('Y-m-d');
                if ($result['earliest_surprise_date'] > $today) {
                    echo "❌ サプライズ日付フィルターで除外される（{$result['earliest_surprise_date']} > {$today}）\n";
                } else {
                    echo "✅ サプライズ日付フィルター通過\n";
                }
            } else {
                echo "✅ サプライズ日付なし（フィルター対象外）\n";
            }
        } else {
            echo "❌ ダッシュボードSQLで取得できない\n";
            echo "理由: cast_id不一致またはJOIN失敗\n";
        }
    }
    
    echo "\n=== 結論 ===\n";
    echo "この注文がダッシュボードに表示されない原因:\n";
    
    if (count($items) === 0) {
        echo "❌ base_order_itemsにデータがない\n";
    } else {
        $has_cast_id = false;
        $has_future_surprise = false;
        
        foreach ($items as $item) {
            if ($item['cast_id'] !== null) {
                $has_cast_id = true;
            }
            if ($item['item_surprise_date'] && $item['item_surprise_date'] > date('Y-m-d')) {
                $has_future_surprise = true;
            }
        }
        
        if (!$has_cast_id) {
            echo "❌ cast_idが紐付いていない\n";
            echo "   → バックフィルバッチで過去データを紐付ける必要があります\n";
        } elseif ($logged_in_cast_id && $items[0]['cast_id'] != $logged_in_cast_id) {
            echo "⚠️ 別のキャストに紐付いている\n";
        } elseif ($has_future_surprise) {
            echo "⚠️ 未来のサプライズ日付でフィルターされている\n";
        } else {
            echo "❓ その他の原因（要調査）\n";
        }
    }
    
    echo '</pre>';
    
} catch (PDOException $e) {
    echo '<pre>❌ エラー: ' . htmlspecialchars($e->getMessage()) . '</pre>';
}
?>
