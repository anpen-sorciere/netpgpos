<?php
/**
 * 特定注文IDの手動再同期
 */
set_time_limit(60);
require_once __DIR__ . '/../../../common/config.php';
require_once __DIR__ . '/../classes/base_practical_auto_manager.php';
require_once __DIR__ . '/../ajax/order_data_ajax.php'; // syncOrdersToDb関数を使用

$target_order_id = '1DAAC85F63A55F7E';
$execute = isset($_GET['execute']) && $_GET['execute'] === 'true';

try {
    $pdo = new PDO(
        "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
        $user,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    $manager = new BasePracticalAutoManager();
    
    echo '<h2>注文ID手動再同期</h2>';
    echo '<pre>';
    echo "対象注文ID: {$target_order_id}\n";
    echo "モード: " . ($execute ? "本番実行" : "確認のみ") . "\n\n";
    
    // STEP 1: 現在の状態確認
    echo "=== STEP 1: 現在の状態 ===\n";
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM base_order_items WHERE base_order_id = ?");
    $stmt->execute([$target_order_id]);
    $current_count = $stmt->fetchColumn();
    echo "base_order_items の件数: {$current_count}件\n\n";
    
    // STEP 2: BASE APIから詳細取得
    echo "=== STEP 2: BASE API詳細取得 ===\n";
    try {
        $detail_response = $manager->getDataWithAutoAuth('read_orders', "/orders/detail/{$target_order_id}");
        
        if (isset($detail_response['order'])) {
            $order = $detail_response['order'];
            echo "✅ 詳細取得成功\n";
            
            // 商品数確認
            $item_count = isset($order['order_items']) ? count($order['order_items']) : 0;
            echo "order_items 件数: {$item_count}件\n";
            
            if ($item_count > 0) {
                echo "\n商品一覧:\n";
                foreach ($order['order_items'] as $idx => $item) {
                    $num = $idx + 1;
                    echo "  {$num}. {$item['title']} x{$item['amount']} - ¥{$item['price']}\n";
                    
                    // オプション確認
                    if (isset($item['options']) && count($item['options']) > 0) {
                        foreach ($item['options'] as $opt) {
                            $opt_name = $opt['option_name'] ?? '';
                            $opt_value = $opt['option_value'] ?? '';
                            if (mb_strpos($opt_name, 'キャスト名') !== false) {
                                echo "     キャスト: {$opt_value}\n";
                            }
                        }
                    }
                }
            } else {
                echo "⚠️ order_itemsが空です\n";
            }
            
            // STEP 3: DB保存
            if ($execute) {
                echo "\n=== STEP 3: DB保存 ===\n";
                
                if ($item_count > 0) {
                    syncOrdersToDb($pdo, [$order], null);
                    echo "✅ DB保存実行\n";
                    
                    // 保存後の確認
                    $stmt = $pdo->prepare("SELECT COUNT(*) FROM base_order_items WHERE base_order_id = ?");
                    $stmt->execute([$target_order_id]);
                    $new_count = $stmt->fetchColumn();
                    echo "保存後の件数: {$new_count}件\n";
                    
                    // cast_id紐付け確認
                    $stmt = $pdo->prepare("
                        SELECT product_name, cast_id, customer_name_from_option 
                        FROM base_order_items 
                        WHERE base_order_id = ?
                    ");
                    $stmt->execute([$target_order_id]);
                    $saved_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    echo "\n保存された商品:\n";
                    foreach ($saved_items as $idx => $item) {
                        $num = $idx + 1;
                        echo "  {$num}. {$item['product_name']}\n";
                        echo "      cast_id: " . ($item['cast_id'] ?? 'NULL') . "\n";
                        echo "      顧客名: " . ($item['customer_name_from_option'] ?? 'NULL') . "\n";
                    }
                    
                } else {
                    echo "❌ order_itemsが空のため保存不可\n";
                }
                
            } else {
                echo "\n=== STEP 3: DB保存（スキップ） ===\n";
                echo "実行するには以下のURLを開いてください:\n";
                $url = "https://purplelion51.sakura.ne.jp/netpgpos/api/debug/resync_order.php?execute=true";
                echo "{$url}\n";
            }
            
        } else {
            echo "❌ 詳細取得失敗: orderキーがありません\n";
        }
        
    } catch (Exception $e) {
        echo "❌ API取得エラー: " . $e->getMessage() . "\n";
    }
    
    echo '</pre>';
    
} catch (Exception $e) {
    echo '<pre>❌ エラー: ' . htmlspecialchars($e->getMessage()) . '</pre>';
}
?>
