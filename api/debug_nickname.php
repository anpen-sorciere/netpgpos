<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/base_api_client.php';

header('Content-Type: text/html; charset=UTF-8');

try {
    $api = new BaseApiClient();
    
    if ($api->needsAuth()) {
        echo '<div style="color: #dc3545; padding: 10px;">BASE API認証が必要です。<br><a href="scope_switcher.php" style="background-color: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px;">BASE API認証を実行</a></div>';
        exit;
    }
    
    // 注文データを取得
    $orders_data = $api->getOrders(5, 0); // 最新5件のみ
    $orders = $orders_data['orders'] ?? [];
    
    if (empty($orders)) {
        echo '<div style="color: #dc3545; padding: 10px;">注文データがありません</div>';
        exit;
    }
    
    echo '<h2>注文データ構造デバッグ</h2>';
    
    foreach ($orders as $index => $order) {
        echo '<h3>注文 ' . ($index + 1) . ' (ID: ' . htmlspecialchars($order['unique_key'] ?? 'N/A') . ')</h3>';
        
        // 基本情報
        echo '<h4>基本情報:</h4>';
        echo '<pre>' . htmlspecialchars(print_r([
            'unique_key' => $order['unique_key'] ?? 'N/A',
            'first_name' => $order['first_name'] ?? 'N/A',
            'last_name' => $order['last_name'] ?? 'N/A',
            'total' => $order['total'] ?? 'N/A'
        ], true)) . '</pre>';
        
        // 商品情報
        if (isset($order['order_items']) && is_array($order['order_items'])) {
            echo '<h4>商品情報:</h4>';
            foreach ($order['order_items'] as $itemIndex => $item) {
                echo '<h5>商品 ' . ($itemIndex + 1) . ': ' . htmlspecialchars($item['title'] ?? 'N/A') . '</h5>';
                
                // オプション情報を詳細に表示
                if (isset($item['options']) && is_array($item['options'])) {
                    echo '<h6>オプション情報:</h6>';
                    echo '<pre>' . htmlspecialchars(print_r($item['options'], true)) . '</pre>';
                    
                    // ニックネーム検索のテスト
                    echo '<h6>ニックネーム検索結果:</h6>';
                    $nicknames = [];
                    foreach ($item['options'] as $option) {
                        $option_name = $option['option_name'] ?? '';
                        $option_value = $option['option_value'] ?? '';
                        
                        echo '<p>オプション名: "' . htmlspecialchars($option_name) . '" → 値: "' . htmlspecialchars($option_value) . '"</p>';
                        
                        // ニックネーム関連のオプションを検索
                        if (stripos($option_name, 'ニックネーム') !== false || 
                            stripos($option_name, 'nickname') !== false ||
                            stripos($option_name, 'お名前') !== false ||
                            stripos($option_name, '名前') !== false) {
                            if (!empty($option_value) && !in_array($option_value, $nicknames)) {
                                $nicknames[] = htmlspecialchars($option_value);
                                echo '<p style="color: #28a745; font-weight: bold;">✓ ニックネーム発見: ' . htmlspecialchars($option_value) . '</p>';
                            }
                        }
                    }
                    
                    if (empty($nicknames)) {
                        echo '<p style="color: #dc3545;">ニックネームが見つかりませんでした</p>';
                    } else {
                        echo '<p style="color: #28a745; font-weight: bold;">最終ニックネーム: ' . implode(', ', $nicknames) . '</p>';
                    }
                } else {
                    echo '<p>オプション情報がありません</p>';
                }
            }
        } else {
            echo '<p>商品情報がありません</p>';
        }
        
        echo '<hr>';
    }
    
} catch (Exception $e) {
    echo '<div style="color: #dc3545; padding: 10px;">エラー: ' . htmlspecialchars($e->getMessage()) . '</div>';
}
?>
