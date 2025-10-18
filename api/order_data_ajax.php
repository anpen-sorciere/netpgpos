<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/base_api_client.php';

header('Content-Type: text/html; charset=UTF-8');

if (!isset($_SESSION['base_access_token'])) {
    echo '<div class="no-orders" style="text-align: center; padding: 20px; color: #dc3545;">BASE API認証が必要です。<br><a href="scope_switcher.php" style="background-color: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px;">BASE API認証を実行</a></div>';
    exit;
}

try {
    $api = new BaseApiClient($_SESSION['base_access_token']);
    
    // 注文データを取得
    $orders_data = $api->getOrders(50, 0);
    $orders = $orders_data['orders'] ?? [];
    
    // 注文を日時で降順ソート
    usort($orders, function($a, $b) {
        $time_a = $a['ordered'] ?? 0;
        $time_b = $b['ordered'] ?? 0;
        
        if (is_numeric($time_a) && is_numeric($time_b)) {
            return $time_b - $time_a;
        }
        
        $timestamp_a = is_numeric($time_a) ? $time_a : strtotime($time_a);
        $timestamp_b = is_numeric($time_b) ? $time_b : strtotime($time_b);
        
        return $timestamp_b - $timestamp_a;
    });
    
    if (empty($orders)) {
        echo '<div class="no-orders"><i class="fas fa-inbox"></i><br>注文データがありません</div>';
        exit;
    }
    
    // 簡潔なテーブル全体を返す
    echo '<table class="order-table">';
    echo '<thead>';
    echo '<tr>';
    echo '<th>注文ヘッダー</th>';
    echo '<th>商品明細</th>';
    echo '<th>詳細</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';
    
    foreach ($orders as $order) {
        // 注文ヘッダー情報
        $order_id = htmlspecialchars($order['unique_key'] ?? 'N/A');
        $customer_name = htmlspecialchars(trim(($order['last_name'] ?? '') . ' ' . ($order['first_name'] ?? '')) ?: 'N/A');
        
        // 注文日時
        $date_value = $order['ordered'] ?? 'N/A';
        if ($date_value !== 'N/A') {
            if (is_numeric($date_value)) {
                $date_value = date('Y/m/d H:i', $date_value);
            } else {
                $timestamp = strtotime($date_value);
                if ($timestamp !== false) {
                    $date_value = date('Y/m/d H:i', $timestamp);
                } else {
                    $date_value = '日時エラー';
                }
            }
        }
        
        // ステータス
        $status = 'N/A';
        $status_class = 'status-unpaid';
        if (isset($order['dispatch_status'])) {
            switch ($order['dispatch_status']) {
                case 'unpaid': $status = '入金待ち'; $status_class = 'status-unpaid'; break;
                case 'ordered': $status = '未対応'; $status_class = 'status-unpaid'; break;
                case 'unshippable': $status = '対応開始前'; $status_class = 'status-paid'; break;
                case 'shipping': $status = '配送中'; $status_class = 'status-paid'; break;
                case 'dispatched': $status = '対応済'; $status_class = 'status-shipped'; break;
                case 'cancelled': $status = 'キャンセル'; $status_class = 'status-cancelled'; break;
                default: $status = '未対応'; $status_class = 'status-unpaid'; break;
            }
        } else {
            if (isset($order['cancelled']) && $order['cancelled'] !== null) {
                $status = 'キャンセル'; $status_class = 'status-cancelled';
            } elseif (isset($order['dispatched']) && $order['dispatched'] !== null) {
                $status = '対応済'; $status_class = 'status-shipped';
            } elseif (isset($order['payment'])) {
                if ($order['payment'] === 'paid' || $order['payment'] === true) {
                    $status = '対応開始前'; $status_class = 'status-paid';
                } else {
                    $status = '入金待ち'; $status_class = 'status-unpaid';
                }
            } else {
                $status = '未対応'; $status_class = 'status-unpaid';
            }
        }
        
        // 合計金額
        $total_amount = '¥' . number_format($order['total'] ?? 0);
        
        echo '<tr>';
        
        // 注文ヘッダー列
        echo '<td class="order-header">';
        echo '<div class="order-header-info">';
        echo '<div class="order-id">#' . $order_id . '</div>';
        echo '<div class="order-date">' . htmlspecialchars($date_value) . '</div>';
        echo '<div class="order-status ' . $status_class . '">' . htmlspecialchars($status) . '</div>';
        echo '<div class="customer-name">' . $customer_name . '</div>';
        echo '<div class="total-amount">' . $total_amount . '</div>';
        
        // ポップアップボタン群
        echo '<div class="popup-buttons">';
        echo '<button class="btn btn-xs btn-info" onclick="showPaymentInfo(\'' . $order_id . '\')">';
        echo '<i class="fas fa-credit-card"></i> 決済';
        echo '</button>';
        echo '<button class="btn btn-xs btn-warning" onclick="showCustomerInfo(\'' . $order_id . '\')">';
        echo '<i class="fas fa-user"></i> お客様';
        echo '</button>';
        echo '<button class="btn btn-xs btn-success" onclick="showShippingInfo(\'' . $order_id . '\')">';
        echo '<i class="fas fa-truck"></i> 配送';
        echo '</button>';
        echo '<button class="btn btn-xs btn-secondary" onclick="showOtherInfo(\'' . $order_id . '\')">';
        echo '<i class="fas fa-info"></i> その他';
        echo '</button>';
        echo '</div>';
        
        echo '</div>';
        echo '</td>';
        
        // 商品明細列（商品情報のみ）
        echo '<td class="order-items">';
        if (isset($order['order_items']) && is_array($order['order_items'])) {
            foreach ($order['order_items'] as $index => $item) {
                echo '<div class="item-detail">';
                echo '<div class="item-name">' . htmlspecialchars($item['title'] ?? 'N/A') . '</div>';
                
                if (!empty($item['variation'])) {
                    echo '<div class="item-variation">バリエーション: ' . htmlspecialchars($item['variation']) . '</div>';
                }
                
                echo '<div class="item-quantity">数量: ' . htmlspecialchars($item['amount'] ?? 'N/A') . '</div>';
                echo '<div class="item-price">単価: ¥' . number_format($item['price'] ?? 0) . '</div>';
                echo '<div class="item-total">小計: ¥' . number_format($item['total'] ?? 0) . '</div>';
                echo '<div class="item-status">ステータス: ' . htmlspecialchars($item['status'] ?? 'N/A') . '</div>';
                
                // オプション情報
                if (isset($item['options']) && is_array($item['options']) && !empty($item['options'])) {
                    echo '<div class="item-options">';
                    foreach ($item['options'] as $option) {
                        echo '<div class="option-item">';
                        echo htmlspecialchars($option['option_name'] ?? 'N/A') . ': ' . htmlspecialchars($option['option_value'] ?? 'N/A');
                        echo '</div>';
                    }
                    echo '</div>';
                }
                
                echo '</div>';
                if ($index < count($order['order_items']) - 1) {
                    echo '<hr class="item-separator">';
                }
            }
        } else {
            echo '<div class="no-items">商品情報なし</div>';
        }
        echo '</td>';
        
        // 詳細ボタン列
        echo '<td>';
        echo '<button class="btn btn-sm btn-secondary" id="toggle-' . $order_id . '" onclick="toggleOrderDetail(\'' . $order_id . '\')">';
        echo '<i class="fas fa-chevron-down"></i> 全詳細';
        echo '</button>';
        echo '</td>';
        echo '</tr>';
        
        // 注文詳細行（全情報表示用）
        echo '<tr id="detail-' . $order_id . '" style="display: none;">';
        echo '<td colspan="3" style="padding: 0;">';
        echo '<!-- 全詳細内容がここに表示されます -->';
        echo '</td>';
        echo '</tr>';
    }
    
    echo '</tbody>';
    echo '</table>';
    
} catch (Exception $e) {
    echo '<div class="no-orders" style="text-align: center; padding: 20px; color: #dc3545;">エラー: ' . htmlspecialchars($e->getMessage()) . '</div>';
}
?>
