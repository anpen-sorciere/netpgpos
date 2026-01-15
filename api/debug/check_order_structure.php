<?php
// BASE API /orders レスポンス構造確認スクリプト
session_start();
require_once __DIR__ . '/../../../common/config.php';
require_once __DIR__ . '/../classes/base_practical_auto_manager.php';

echo '<pre>';
echo "=== BASE API /orders レスポンス構造確認 ===\n\n";

try {
    $manager = new BasePracticalAutoManager();
    
    // 最新1件のみ取得
    $response = $manager->getDataWithAutoAuth('read_orders', '/orders', ['limit' => 1, 'offset' => 0]);
    
    if (isset($response['orders']) && count($response['orders']) > 0) {
        $order = $response['orders'][0];
        
        echo "✅ 注文取得成功\n\n";
        echo "注文ID: " . ($order['unique_key'] ?? 'なし') . "\n";
        echo "注文日時: " . (isset($order['ordered']) ? date('Y-m-d H:i:s', $order['ordered']) : 'なし') . "\n\n";
        
        echo "=== order_items の確認 ===\n";
        if (isset($order['order_items'])) {
            echo "✅ order_items は含まれています！\n";
            echo "商品数: " . count($order['order_items']) . "件\n\n";
            
            if (count($order['order_items']) > 0) {
                $first_item = $order['order_items'][0];
                echo "=== 最初の商品情報 ===\n";
                echo "商品名: " . ($first_item['title'] ?? 'なし') . "\n";
                echo "数量: " . ($first_item['amount'] ?? 'なし') . "\n";
                echo "価格: " . ($first_item['price'] ?? 'なし') . "\n";
                
                echo "\n=== options の確認 ===\n";
                if (isset($first_item['options']) && is_array($first_item['options'])) {
                    echo "✅ options は含まれています！\n";
                    echo "オプション数: " . count($first_item['options']) . "件\n\n";
                    
                    foreach ($first_item['options'] as $idx => $option) {
                        $opt_name = $option['option_name'] ?? 'なし';
                        $opt_value = $option['option_value'] ?? 'なし';
                        echo "オプション" . ($idx + 1) . ": {$opt_name} => {$opt_value}\n";
                    }
                } else {
                    echo "❌ options は含まれていません\n";
                }
            }
        } else {
            echo "❌ order_items は含まれていません！\n";
            echo "→ 詳細API (/orders/detail/:unique_key) でのみ取得可能です\n";
        }
        
        echo "\n\n=== 完全なレスポンス（最初の注文のみ）===\n";
        print_r($order);
        
    } else {
        echo "❌ 注文データが取得できませんでした\n";
    }
    
} catch (Exception $e) {
    echo "❌ エラー: " . $e->getMessage() . "\n";
}

echo '</pre>';
?>
