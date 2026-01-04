<?php
// APIデータ構造デバッグ用スクリプト
header('Content-Type: text/html; charset=utf-8');

// 必要な設定とDB接続を読み込む
require_once __DIR__ . '/../../common/config.php';
require_once __DIR__ . '/../../common/dbconnect.php';
require_once __DIR__ . '/../../common/functions.php';

require_once __DIR__ . '/base_practical_auto_manager.php';

echo '<pre>';
echo "データ構造解析開始...\n";

try {
    $manager = new BasePracticalAutoManager();
    // 最新50件取得
    $orders = $manager->getDataWithAutoAuth('read_orders', '/orders', ['limit' => 50]);
    
    $found = false;
    
    if (isset($orders['orders'])) {
        foreach ($orders['orders'] as $order) {
            $json = json_encode($order, JSON_UNESCAPED_UNICODE);
            if (strpos($json, 'サプライズ') !== false) {
                echo "★「サプライズ」を含む注文が見つかりました (Order ID: " . $order['unique_key'] . ")\n";
                
                // 商品情報のダンプ
                if (isset($order['order_items'])) {
                    echo "--- 商品情報構造 ---\n";
                    print_r($order['order_items']);
                }
                
                $found = true;
                break; // 1件見つかればOK
            }
        }
    }
    
    if (!$found) {
        echo "最新50件の中に「サプライズ」という文字を含む注文は見つかりませんでした。\n";
        echo "検索範囲を広げて再試行します(100件)...\n";
        
        // 追加取得
        $orders2 = $manager->getDataWithAutoAuth('read_orders', '/orders', ['limit' => 50, 'offset' => 50]);
        if (isset($orders2['orders'])) {
            foreach ($orders2['orders'] as $order) {
                $json = json_encode($order, JSON_UNESCAPED_UNICODE);
                if (strpos($json, 'サプライズ') !== false) {
                    echo "★「サプライズ」を含む注文が見つかりました (Order ID: " . $order['unique_key'] . ")\n";
                    echo "--- 商品情報構造 ---\n";
                    print_r($order['order_items']);
                    $found = true;
                    break;
                }
            }
        }
    }
    
    if (!$found) {
        echo "合計100件検索しましたが、「サプライズ」は見つかりませんでした。\n";
    }

} catch (Exception $e) {
    echo "エラー: " . $e->getMessage();
}
echo '</pre>';
