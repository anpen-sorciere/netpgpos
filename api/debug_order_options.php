<?php
// 注文ID 28FEEB6EDAA52A18 のオプション情報をデバッグ表示
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/base_practical_auto_manager.php';

try {
    $practical_manager = new BasePracticalAutoManager();
    $combined_data = $practical_manager->getCombinedOrderData(50);
    $orders = $combined_data['merged_orders'];
    
    // 注文ID 28FEEB6EDAA52A18 を検索
    $target_order = null;
    foreach ($orders as $order) {
        if (($order['unique_key'] ?? '') === '28FEEB6EDAA52A18') {
            $target_order = $order;
            break;
        }
    }
    
    if (!$target_order) {
        echo '<h2>注文ID 28FEEB6EDAA52A18 が見つかりません</h2>';
        echo '<p>利用可能な注文ID:</p>';
        echo '<ul>';
        foreach ($orders as $order) {
            echo '<li>' . htmlspecialchars($order['unique_key'] ?? 'N/A') . '</li>';
        }
        echo '</ul>';
        exit;
    }
    
    echo '<h2>注文ID: ' . htmlspecialchars($target_order['unique_key']) . '</h2>';
    echo '<h3>お客様名: ' . htmlspecialchars(($target_order['last_name'] ?? '') . ' ' . ($target_order['first_name'] ?? '')) . '</h3>';
    
    // 商品情報の確認
    if (isset($target_order['order_items']) && is_array($target_order['order_items'])) {
        echo '<h3>商品情報:</h3>';
        foreach ($target_order['order_items'] as $index => $item) {
            echo '<div style="border: 1px solid #ccc; padding: 10px; margin: 10px 0;">';
            echo '<h4>商品 ' . ($index + 1) . ': ' . htmlspecialchars($item['title'] ?? 'N/A') . '</h4>';
            
            // オプション情報の詳細表示
            if (isset($item['options']) && is_array($item['options'])) {
                echo '<h5>オプション情報:</h5>';
                echo '<table border="1" style="border-collapse: collapse; width: 100%;">';
                echo '<tr><th>オプション名</th><th>オプション値</th></tr>';
                foreach ($item['options'] as $option) {
                    $option_name = $option['option_name'] ?? '';
                    $option_value = $option['option_value'] ?? '';
                    echo '<tr>';
                    echo '<td style="padding: 5px;">' . htmlspecialchars($option_name) . '</td>';
                    echo '<td style="padding: 5px;">' . htmlspecialchars($option_value) . '</td>';
                    echo '</tr>';
                }
                echo '</table>';
            } else {
                echo '<p>オプション情報なし</p>';
            }
            echo '</div>';
        }
    } else {
        echo '<p>商品情報が見つかりません</p>';
    }
    
    // 全データ構造の確認
    echo '<h3>全データ構造:</h3>';
    echo '<pre>' . htmlspecialchars(json_encode($target_order, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . '</pre>';
    
} catch (Exception $e) {
    echo '<h2>エラー: ' . htmlspecialchars($e->getMessage()) . '</h2>';
}
?>
