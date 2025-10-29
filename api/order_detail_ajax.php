<?php
require_once __DIR__ . '/../../common/config.php';
require_once __DIR__ . '/../../common/dbconnect.php';
require_once __DIR__ . '/../../common/functions.php';
require_once __DIR__ . '/base_api_client.php';

// セッション開始
session_start();

// BASE API認証チェック
if (!isset($_SESSION['base_access_token'])) {
    echo '<div style="color: #dc3545; padding: 20px;">BASE API認証が必要です。</div>';
    exit;
}

// 注文IDを取得
$order_id = $_GET['order_id'] ?? '';
if (empty($order_id)) {
    echo '<div style="color: #dc3545; padding: 20px;">注文IDが指定されていません。</div>';
    exit;
}

try {
    $api = new BaseApiClient($_SESSION['base_access_token']);
    
    // 注文詳細を取得（BASE APIの注文詳細エンドポイントを使用）
    try {
        $order_detail_response = $api->getOrderDetail($order_id);
        
        // BASE APIのレスポンス構造: {"order": {...}}
        $order_detail = $order_detail_response['order'] ?? null;
        
        if (empty($order_detail)) {
            echo '<div style="color: #dc3545; padding: 20px;">注文ID「' . htmlspecialchars($order_id) . '」の詳細が見つかりませんでした。</div>';
            echo '<div style="background-color: #f8f9fa; padding: 15px; border-radius: 8px; margin: 20px 0;">';
            echo '<h4>APIレスポンス:</h4>';
            echo '<pre>' . htmlspecialchars(print_r($order_detail_response, true)) . '</pre>';
            echo '</div>';
            exit;
        }
        
        // デバッグ：APIレスポンスの確認（本番用は非表示）
        // echo '<div style="background-color: #fff3cd; padding: 15px; border-radius: 8px; margin-bottom: 20px;">';
        // echo '<h4>APIレスポンス確認</h4>';
        // echo '<p><strong>注文ID:</strong> ' . htmlspecialchars($order_id) . '</p>';
        // echo '<p><strong>レスポンスタイプ:</strong> ' . gettype($order_detail) . '</p>';
        // echo '<p><strong>レスポンスサイズ:</strong> ' . (is_array($order_detail) ? count($order_detail) : 'N/A') . '</p>';
        // echo '</div>';
        
    } catch (Exception $e) {
        echo '<div style="color: #dc3545; padding: 20px;">注文詳細取得エラー: ' . htmlspecialchars($e->getMessage()) . '</div>';
        exit;
    }
    
    // デバッグ用：詳細データ構造を表示（本番用は非表示）
    // echo '<div style="background-color: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px;">';
    // echo '<h4>デバッグ: 注文詳細データ構造</h4>';
    // echo '<pre style="font-size: 12px; overflow-x: auto;">';
    // echo htmlspecialchars(print_r($order_detail, true));
    // echo '</pre>';
    // echo '</div>';
    
    // キッチン用の詳細表示コンテナ
    echo '<div class="order-detail-content">';
    
       // 決済・配送情報（注文ヘッダーにない追加情報のみ）
       echo '<div class="order-detail-section">';
       echo '<h3><i class="fas fa-info-circle"></i> 決済・配送情報</h3>';
       echo '<table class="order-detail-table">';
       
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
       echo '<tr><td>決済方法</td><td>' . htmlspecialchars($payment_display) . '</td></tr>';
       
       // 送料・手数料の表示
       if (isset($order_detail['shipping_fee']) && $order_detail['shipping_fee'] > 0) {
           echo '<tr><td>送料</td><td>¥' . number_format($order_detail['shipping_fee']) . '</td></tr>';
       }
       if (isset($order_detail['cod_fee']) && $order_detail['cod_fee'] > 0) {
           echo '<tr><td>代引き手数料</td><td>¥' . number_format($order_detail['cod_fee']) . '</td></tr>';
       }
       
       echo '</table>';
       echo '</div>';
    
       // お客様情報と配送先情報の統合表示
       echo '<div class="order-detail-section">';
       echo '<h3><i class="fas fa-user"></i> お客様・配送先情報</h3>';
       echo '<table class="order-detail-table">';
       echo '<tr><td>お名前</td><td>' . htmlspecialchars(($order_detail['last_name'] ?? '') . ' ' . ($order_detail['first_name'] ?? '')) . '</td></tr>';
       echo '<tr><td>メールアドレス</td><td>' . htmlspecialchars($order_detail['mail_address'] ?? 'N/A') . '</td></tr>';
       echo '<tr><td>電話番号</td><td>' . htmlspecialchars($order_detail['tel'] ?? 'N/A') . '</td></tr>';
       echo '<tr><td>郵便番号</td><td>' . htmlspecialchars($order_detail['zip_code'] ?? 'N/A') . '</td></tr>';
       echo '<tr><td>都道府県</td><td>' . htmlspecialchars($order_detail['prefecture'] ?? 'N/A') . '</td></tr>';
       echo '<tr><td>住所1</td><td>' . htmlspecialchars($order_detail['address'] ?? 'N/A') . '</td></tr>';
       echo '<tr><td>住所2</td><td>' . htmlspecialchars($order_detail['address2'] ?? 'N/A') . '</td></tr>';
       echo '<tr><td>国</td><td>' . htmlspecialchars($order_detail['country'] ?? 'N/A') . '</td></tr>';
       echo '<tr><td>備考</td><td>' . htmlspecialchars($order_detail['remark'] ?? 'N/A') . '</td></tr>';
       echo '</table>';
       
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
               echo '<h4 style="margin-top: 20px; color: #007bff;">配送先情報（お客様情報と異なる場合）</h4>';
               echo '<table class="order-detail-table">';
               echo '<tr><td>配送先お名前</td><td>' . htmlspecialchars($receiver_info['name']) . '</td></tr>';
               echo '<tr><td>配送先電話番号</td><td>' . htmlspecialchars($receiver_info['tel']) . '</td></tr>';
               echo '<tr><td>配送先郵便番号</td><td>' . htmlspecialchars($receiver_info['zip_code']) . '</td></tr>';
               echo '<tr><td>配送先都道府県</td><td>' . htmlspecialchars($receiver_info['prefecture']) . '</td></tr>';
               echo '<tr><td>配送先住所1</td><td>' . htmlspecialchars($receiver_info['address']) . '</td></tr>';
               echo '<tr><td>配送先住所2</td><td>' . htmlspecialchars($receiver_info['address2']) . '</td></tr>';
               echo '<tr><td>配送先国</td><td>' . htmlspecialchars($receiver_info['country']) . '</td></tr>';
               echo '</table>';
           } else {
               echo '<p style="margin-top: 15px; color: #28a745; font-style: italic;"><i class="fas fa-check-circle"></i> 配送先情報はお客様情報と同じです</p>';
           }
       }
       echo '</div>';
    
    // 商品情報（BASE APIのorder_itemsキーを使用）
    if (isset($order_detail['order_items']) && is_array($order_detail['order_items'])) {
        echo '<div class="order-detail-section">';
        echo '<h3><i class="fas fa-box"></i> 商品情報</h3>';
        
        foreach ($order_detail['order_items'] as $index => $item) {
            echo '<div style="border: 1px solid #dee2e6; border-radius: 8px; padding: 15px; margin-bottom: 15px; background-color: #f8f9fa;">';
            echo '<h4 style="margin-top: 0; color: #2c3e50;">商品 ' . ($index + 1) . ': ' . htmlspecialchars($item['title'] ?? 'N/A') . '</h4>';
            
            echo '<table class="order-detail-table">';
            echo '<tr><td>商品ID</td><td>' . htmlspecialchars($item['item_id'] ?? 'N/A') . '</td></tr>';
            echo '<tr><td>商品コード</td><td>' . htmlspecialchars($item['item_identifier'] ?? 'N/A') . '</td></tr>';
            echo '<tr><td>バリエーション</td><td>' . htmlspecialchars($item['variation'] ?? 'N/A') . '</td></tr>';
            echo '<tr><td>数量</td><td>' . htmlspecialchars($item['amount'] ?? 'N/A') . '</td></tr>';
            echo '<tr><td>単価</td><td>¥' . number_format($item['price'] ?? 0) . '</td></tr>';
            echo '<tr><td>小計</td><td>¥' . number_format($item['total'] ?? 0) . '</td></tr>';
            echo '<tr><td>ステータス</td><td>' . htmlspecialchars($item['status'] ?? 'N/A') . '</td></tr>';
            echo '</table>';
            
            // オプション情報の表示
            if (isset($item['options']) && is_array($item['options']) && !empty($item['options'])) {
                echo '<h5 style="color: #495057; margin: 15px 0 10px 0;">オプション情報:</h5>';
                echo '<table class="order-detail-table">';
                foreach ($item['options'] as $option) {
                    echo '<tr><td>' . htmlspecialchars($option['option_name'] ?? 'N/A') . '</td><td>' . htmlspecialchars($option['option_value'] ?? 'N/A') . '</td></tr>';
                }
                echo '</table>';
            }
            
            echo '</div>';
        }
        
        echo '</div>';
    }
    
    // 配送情報（shipping_linesを使用）
    if (isset($order_detail['shipping_lines']) && is_array($order_detail['shipping_lines'])) {
        echo '<div class="order-detail-section">';
        echo '<h3><i class="fas fa-truck"></i> 配送情報</h3>';
        
        foreach ($order_detail['shipping_lines'] as $index => $shipping) {
            echo '<div style="border: 1px solid #dee2e6; border-radius: 8px; padding: 15px; margin-bottom: 15px; background-color: #f8f9fa;">';
            echo '<h4 style="margin-top: 0; color: #2c3e50;">配送ライン ' . ($index + 1) . '</h4>';
            
            echo '<table class="order-detail-table">';
            echo '<tr><td>配送方法</td><td>' . htmlspecialchars($shipping['shipping_method'] ?? 'N/A') . '</td></tr>';
            echo '<tr><td>送料</td><td>¥' . number_format($shipping['shipping_fee'] ?? 0) . '</td></tr>';
            if (isset($shipping['order_item_ids']) && is_array($shipping['order_item_ids'])) {
                echo '<tr><td>対象商品ID</td><td>' . implode(', ', $shipping['order_item_ids']) . '</td></tr>';
            }
            echo '</table>';
            echo '</div>';
        }
        
        echo '</div>';
    }
    
    // 配送日時の表示
    if (isset($order_detail['delivery_date']) && !empty($order_detail['delivery_date'])) {
        echo '<div class="order-detail-section">';
        echo '<h3><i class="fas fa-calendar"></i> 配送日時</h3>';
        echo '<table class="order-detail-table">';
        echo '<tr><td>配送希望日</td><td>' . htmlspecialchars($order_detail['delivery_date']) . '</td></tr>';
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
            echo '<tr><td>配送希望時間帯</td><td>' . htmlspecialchars($time_display) . '</td></tr>';
        }
        echo '</table>';
        echo '</div>';
    }
    
    // その他の情報
    $other_fields = ['payment', 'delivery_date', 'delivery_time_zone', 'cancelled', 'dispatched', 'modified'];
    $has_other_info = false;
    foreach ($other_fields as $field) {
        if (isset($order_detail[$field]) && $order_detail[$field] !== null) {
            $has_other_info = true;
            break;
        }
    }
    
    if ($has_other_info) {
        echo '<div class="order-detail-section">';
        echo '<h3><i class="fas fa-info"></i> その他の情報</h3>';
        echo '<table class="order-detail-table">';
        foreach ($other_fields as $field) {
            if (isset($order_detail[$field]) && $order_detail[$field] !== null) {
                $value = $order_detail[$field];
                if (is_array($value)) {
                    $value = json_encode($value, JSON_UNESCAPED_UNICODE);
                }
                echo '<tr><td>' . htmlspecialchars($field) . '</td><td>' . htmlspecialchars($value) . '</td></tr>';
            }
        }
        echo '</table>';
        echo '</div>';
    }
    
    echo '</div>'; // order-detail-content の終了
    
} catch (Exception $e) {
    echo '<div style="color: #dc3545; padding: 20px;">エラー: ' . htmlspecialchars($e->getMessage()) . '</div>';
}
?>
