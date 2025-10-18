<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/base_api_client.php';

header('Content-Type: text/html; charset=UTF-8');

if (!isset($_SESSION['base_access_token'])) {
    echo '<tr><td colspan="6" style="text-align: center; padding: 20px; color: #dc3545;">BASE API認証が必要です。<br><a href="scope_switcher.php" style="background-color: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px;">BASE API認証を実行</a></td></tr>';
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
        echo '<tr><td colspan="6" style="text-align: center; padding: 20px;">注文データがありません</td></tr>';
        exit;
    }
    
    foreach ($orders as $order) {
        echo '<tr>';
        
        // 注文ID
        echo '<td class="order-id">#' . htmlspecialchars($order['unique_key'] ?? 'N/A') . '</td>';
        
        // 注文日時
        echo '<td class="order-date">';
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
        echo htmlspecialchars($date_value);
        
        // 配送日時
        if (isset($order['delivery_date']) && $order['delivery_date'] !== null) {
            echo '<br><small style="color: #6c757d;">配送: ';
            if (is_array($order['delivery_date'])) {
                echo htmlspecialchars(json_encode($order['delivery_date'], JSON_UNESCAPED_UNICODE));
            } else {
                echo htmlspecialchars($order['delivery_date']);
            }
            if (isset($order['delivery_time_zone']) && $order['delivery_time_zone'] !== null) {
                $time_zone = $order['delivery_time_zone'];
                $time_zones = [
                    '0812' => '午前中', '1214' => '12時~14時', '1416' => '14時~16時',
                    '1618' => '16時~18時', '1820' => '18時~20時', '2021' => '20時~21時'
                ];
                $time_display = $time_zones[$time_zone] ?? $time_zone;
                echo ' ' . htmlspecialchars($time_display);
            }
            echo '</small>';
        }
        
        // キャンセル日時
        if (isset($order['cancelled']) && $order['cancelled'] !== null) {
            echo '<br><small style="color: #dc3545;">キャンセル: ';
            $cancelled_value = $order['cancelled'];
            if (is_array($cancelled_value)) {
                echo htmlspecialchars(json_encode($cancelled_value, JSON_UNESCAPED_UNICODE));
            } else {
                if (is_numeric($cancelled_value)) {
                    echo htmlspecialchars(date('Y/m/d H:i', $cancelled_value));
                } else {
                    $timestamp = strtotime($cancelled_value);
                    if ($timestamp !== false) {
                        echo htmlspecialchars(date('Y/m/d H:i', $timestamp));
                    } else {
                        echo htmlspecialchars($cancelled_value);
                    }
                }
            }
            echo '</small>';
        }
        
        // 発送日時
        if (isset($order['dispatched']) && $order['dispatched'] !== null) {
            echo '<br><small style="color: #28a745;">発送: ';
            $dispatched_value = $order['dispatched'];
            if (is_array($dispatched_value)) {
                echo htmlspecialchars(json_encode($dispatched_value, JSON_UNESCAPED_UNICODE));
            } else {
                if (is_numeric($dispatched_value)) {
                    echo htmlspecialchars(date('Y/m/d H:i', $dispatched_value));
                } else {
                    $timestamp = strtotime($dispatched_value);
                    if ($timestamp !== false) {
                        echo htmlspecialchars(date('Y/m/d H:i', $timestamp));
                    } else {
                        echo htmlspecialchars($dispatched_value);
                    }
                }
            }
            echo '</small>';
        }
        
        // 更新日時（注文日時と異なる場合のみ）
        if (isset($order['modified']) && $order['modified'] !== null) {
            $modified_value = $order['modified'];
            $modified_timestamp = null;
            
            if (is_array($modified_value)) {
                // Skip if array
            } else {
                if (is_numeric($modified_value)) {
                    $modified_timestamp = $modified_value;
                } else {
                    $modified_timestamp = strtotime($modified_value);
                }
            }
            
            $ordered_timestamp = null;
            $ordered_value = $order['ordered'] ?? null;
            if ($ordered_value !== null) {
                if (is_numeric($ordered_value)) {
                    $ordered_timestamp = $ordered_value;
                } else {
                    $ordered_timestamp = strtotime($ordered_value);
                }
            }
            
            if ($modified_timestamp !== null && $ordered_timestamp !== null && 
                $modified_timestamp !== $ordered_timestamp) {
                echo '<br><small style="color: #6c757d;">更新: ';
                echo htmlspecialchars(date('Y/m/d H:i', $modified_timestamp));
                echo '</small>';
            }
        }
        echo '</td>';
        
        // お客様名
        echo '<td>' . htmlspecialchars(trim(($order['last_name'] ?? '') . ' ' . ($order['first_name'] ?? '')) ?: 'N/A') . '</td>';
        
        // ステータス
        echo '<td>';
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
        echo '<span class="order-status ' . $status_class . '">' . htmlspecialchars($status) . '</span>';
        echo '</td>';
        
        // 合計金額と決済方法
        echo '<td>¥' . number_format($order['total'] ?? 0);
        if (isset($order['payment']) && $order['payment'] !== null) {
            echo '<br><small style="color: #6c757d;">';
            $payment_value = $order['payment'];
            if (is_array($payment_value)) {
                $payment_value = $payment_value[0] ?? json_encode($payment_value, JSON_UNESCAPED_UNICODE);
            }
            $payment_methods = [
                'creditcard' => 'クレジットカード', 'cod' => '代金引換', 'cvs' => 'コンビニ決済',
                'base_bt' => '銀行振込(BASE口座)', 'atobarai' => '後払い決済', 'carrier_01' => 'キャリア決済(ドコモ)',
                'carrier_02' => 'キャリア決済(au)', 'carrier_03' => 'キャリア決済(ソフトバンク)', 'paypal' => 'PayPal決済',
                'coin' => 'コイン決済', 'amazon_pay' => 'Amazon Pay', 'bnpl' => 'Pay ID あと払い',
                'bnpl_installment' => 'Pay ID 3回あと払い'
            ];
            echo htmlspecialchars($payment_methods[$payment_value] ?? $payment_value);
            echo '</small>';
        }
        echo '</td>';
        
        // 詳細ボタン
        echo '<td>';
        echo '<button class="btn btn-sm btn-secondary" id="toggle-' . htmlspecialchars($order['unique_key'] ?? 'N/A') . '" onclick="toggleOrderDetail(\'' . htmlspecialchars($order['unique_key'] ?? 'N/A') . '\')">';
        echo '<i class="fas fa-chevron-down"></i> 詳細を見る';
        echo '</button>';
        echo '</td>';
        echo '</tr>';
        
        // 注文詳細行
        echo '<!-- 注文詳細行 -->';
        echo '<tr id="detail-' . htmlspecialchars($order['unique_key'] ?? 'N/A') . '" style="display: none;">';
        echo '<td colspan="6" style="padding: 0;">';
        echo '<!-- 注文詳細内容がここに表示されます -->';
        echo '</td>';
        echo '</tr>';
    }
    
} catch (Exception $e) {
    echo '<tr><td colspan="6" style="text-align: center; padding: 20px; color: #dc3545;">エラー: ' . htmlspecialchars($e->getMessage()) . '</td></tr>';
}
?>
