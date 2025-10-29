<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/base_api_client.php';

header('Content-Type: text/html; charset=UTF-8');

if (!isset($_SESSION['base_access_token'])) {
    echo '<div style="color: #dc3545; padding: 20px;">BASE API認証が必要です。</div>';
    exit;
}

$order_id = $_GET['order_id'] ?? '';
$info_type = $_GET['type'] ?? '';

if (empty($order_id) || empty($info_type)) {
    echo '<div style="color: #dc3545; padding: 20px;">パラメータが不正です。</div>';
    exit;
}

try {
    $api = new BaseApiClient($_SESSION['base_access_token']);
    $order_detail_response = $api->getOrderDetail($order_id);
    $order_detail = $order_detail_response['order'] ?? [];
    
    if (empty($order_detail)) {
        echo '<div style="color: #dc3545; padding: 20px;">注文詳細が見つかりませんでした。</div>';
        exit;
    }
    
    switch ($info_type) {
        case 'payment':
            echo '<div class="popup-content">';
            echo '<h3><i class="fas fa-credit-card"></i> 決済・配送情報</h3>';
            
            // 決済方法の表示
            $payment = $order_detail['payment'] ?? 'N/A';
            $payment_labels = [
                'creditcard' => 'クレジットカード',
                'cod' => '代金引換',
                'cvs' => 'コンビニ決済',
                'base_bt' => '銀行振込(BASE口座)',
                'atobarai' => '後払い決済',
                'carrier_01' => 'キャリア決済(ドコモ)',
                'carrier_02' => 'キャリア決済(au)',
                'carrier_03' => 'キャリア決済(ソフトバンク)',
                'paypal' => 'PayPal決済',
                'coin' => 'コイン決済',
                'amazon_pay' => 'Amazon Pay',
                'bnpl' => 'Pay ID あと払い',
                'bnpl_installment' => 'Pay ID 3回あと払い'
            ];
            $payment_display = $payment_labels[$payment] ?? $payment;
            echo '<p><strong>決済方法:</strong> ' . htmlspecialchars($payment_display) . '</p>';
            
            // 送料・手数料の表示
            if (isset($order_detail['shipping_fee']) && $order_detail['shipping_fee'] > 0) {
                echo '<p><strong>送料:</strong> ¥' . number_format($order_detail['shipping_fee']) . '</p>';
            }
            if (isset($order_detail['cod_fee']) && $order_detail['cod_fee'] > 0) {
                echo '<p><strong>代引き手数料:</strong> ¥' . number_format($order_detail['cod_fee']) . '</p>';
            }
            
            echo '</div>';
            break;
            
        case 'customer':
            echo '<div class="popup-content">';
            echo '<h3><i class="fas fa-user"></i> お客様・配送先情報</h3>';
            
            echo '<h4>お客様情報</h4>';
            echo '<p><strong>お名前:</strong> ' . htmlspecialchars(($order_detail['last_name'] ?? '') . ' ' . ($order_detail['first_name'] ?? '')) . '</p>';
            echo '<p><strong>メールアドレス:</strong> ' . htmlspecialchars($order_detail['mail_address'] ?? 'N/A') . '</p>';
            echo '<p><strong>電話番号:</strong> ' . htmlspecialchars($order_detail['tel'] ?? 'N/A') . '</p>';
            echo '<p><strong>住所:</strong> ' . htmlspecialchars($order_detail['prefecture'] ?? '') . htmlspecialchars($order_detail['address'] ?? '') . htmlspecialchars($order_detail['address2'] ?? '') . '</p>';
            echo '<p><strong>郵便番号:</strong> ' . htmlspecialchars($order_detail['zip_code'] ?? 'N/A') . '</p>';
            echo '<p><strong>備考:</strong> ' . htmlspecialchars($order_detail['remark'] ?? 'N/A') . '</p>';
            
            // 配送先情報が異なる場合のみ表示
            if (isset($order_detail['order_receiver']) && is_array($order_detail['order_receiver'])) {
                $receiver = $order_detail['order_receiver'];
                
                // お客様情報と配送先情報を比較
                $customer_info = [
                    'name' => ($order_detail['last_name'] ?? '') . ' ' . ($order_detail['first_name'] ?? ''),
                    'tel' => $order_detail['tel'] ?? '',
                    'zip_code' => $order_detail['zip_code'] ?? '',
                    'prefecture' => $order_detail['prefecture'] ?? '',
                    'address' => $order_detail['address'] ?? '',
                    'address2' => $order_detail['address2'] ?? '',
                    'country' => $order_detail['country'] ?? ''
                ];
                
                $receiver_info = [
                    'name' => ($receiver['last_name'] ?? '') . ' ' . ($receiver['first_name'] ?? ''),
                    'tel' => $receiver['tel'] ?? '',
                    'zip_code' => $receiver['zip_code'] ?? '',
                    'prefecture' => $receiver['prefecture'] ?? '',
                    'address' => $receiver['address'] ?? '',
                    'address2' => $receiver['address2'] ?? '',
                    'country' => $receiver['country'] ?? ''
                ];
                
                // 情報が異なる場合のみ配送先情報を表示
                if ($customer_info !== $receiver_info) {
                    echo '<h4>配送先情報（お客様情報と異なる場合）</h4>';
                    echo '<p><strong>配送先お名前:</strong> ' . htmlspecialchars($receiver_info['name']) . '</p>';
                    echo '<p><strong>配送先電話番号:</strong> ' . htmlspecialchars($receiver_info['tel']) . '</p>';
                    echo '<p><strong>配送先住所:</strong> ' . htmlspecialchars($receiver_info['prefecture']) . htmlspecialchars($receiver_info['address']) . htmlspecialchars($receiver_info['address2']) . '</p>';
                    echo '<p><strong>配送先郵便番号:</strong> ' . htmlspecialchars($receiver_info['zip_code']) . '</p>';
                } else {
                    echo '<p style="color: #28a745;"><i class="fas fa-check-circle"></i> 配送先情報はお客様情報と同じです</p>';
                }
            }
            
            echo '</div>';
            break;
            
        case 'shipping':
            echo '<div class="popup-content">';
            echo '<h3><i class="fas fa-truck"></i> 配送情報</h3>';
            
            // 配送情報（shipping_linesを使用）
            if (isset($order_detail['shipping_lines']) && is_array($order_detail['shipping_lines'])) {
                foreach ($order_detail['shipping_lines'] as $index => $shipping) {
                    echo '<h4>配送ライン ' . ($index + 1) . '</h4>';
                    echo '<p><strong>配送方法:</strong> ' . htmlspecialchars($shipping['shipping_method'] ?? 'N/A') . '</p>';
                    echo '<p><strong>送料:</strong> ¥' . number_format($shipping['shipping_fee'] ?? 0) . '</p>';
                    if (isset($shipping['order_item_ids']) && is_array($shipping['order_item_ids'])) {
                        echo '<p><strong>対象商品ID:</strong> ' . implode(', ', $shipping['order_item_ids']) . '</p>';
                    }
                }
            }
            
            // 配送日時の表示
            if (isset($order_detail['delivery_date']) && !empty($order_detail['delivery_date'])) {
                echo '<h4>配送日時</h4>';
                echo '<p><strong>配送希望日:</strong> ' . htmlspecialchars($order_detail['delivery_date']) . '</p>';
                if (isset($order_detail['delivery_time_zone']) && !empty($order_detail['delivery_time_zone'])) {
                    $time_zones = [
                        '0812' => '午前中',
                        '1214' => '12時~14時',
                        '1416' => '14時~16時',
                        '1618' => '16時~18時',
                        '1820' => '18時~20時',
                        '2021' => '20時~21時'
                    ];
                    $time_display = $time_zones[$order_detail['delivery_time_zone']] ?? $order_detail['delivery_time_zone'];
                    echo '<p><strong>配送希望時間帯:</strong> ' . htmlspecialchars($time_display) . '</p>';
                }
            }
            
            echo '</div>';
            break;
            
        case 'other':
            echo '<div class="popup-content">';
            echo '<h3><i class="fas fa-info"></i> その他の情報</h3>';
            
            // その他の情報
            $other_fields = ['cancelled', 'dispatched', 'modified'];
            foreach ($other_fields as $field) {
                if (isset($order_detail[$field]) && $order_detail[$field] !== null) {
                    $value = $order_detail[$field];
                    if (is_array($value)) {
                        $value = json_encode($value, JSON_UNESCAPED_UNICODE);
                    } else {
                        if (is_numeric($value)) {
                            $value = date('Y/m/d H:i', $value);
                        } else {
                            $timestamp = strtotime($value);
                            if ($timestamp !== false) {
                                $value = date('Y/m/d H:i', $timestamp);
                            }
                        }
                    }
                    echo '<p><strong>' . htmlspecialchars($field) . ':</strong> ' . htmlspecialchars($value) . '</p>';
                }
            }
            
            echo '</div>';
            break;
            
        default:
            echo '<div style="color: #dc3545; padding: 20px;">不正な情報タイプです。</div>';
            break;
    }
    
} catch (Exception $e) {
    echo '<div style="color: #dc3545; padding: 20px;">エラー: ' . htmlspecialchars($e->getMessage()) . '</div>';
}
?>
