<?php
require_once __DIR__ . '/../config.php';
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
        
        // デバッグ：APIレスポンスの確認
        echo '<div style="background-color: #fff3cd; padding: 15px; border-radius: 8px; margin-bottom: 20px;">';
        echo '<h4>APIレスポンス確認</h4>';
        echo '<p><strong>注文ID:</strong> ' . htmlspecialchars($order_id) . '</p>';
        echo '<p><strong>レスポンスタイプ:</strong> ' . gettype($order_detail) . '</p>';
        echo '<p><strong>レスポンスサイズ:</strong> ' . (is_array($order_detail) ? count($order_detail) : 'N/A') . '</p>';
        echo '</div>';
        
    } catch (Exception $e) {
        echo '<div style="color: #dc3545; padding: 20px;">注文詳細取得エラー: ' . htmlspecialchars($e->getMessage()) . '</div>';
        exit;
    }
    
    // デバッグ用：詳細データ構造を表示（一時的に有効化）
    echo '<div style="background-color: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px;">';
    echo '<h4>デバッグ: 注文詳細データ構造</h4>';
    echo '<pre style="font-size: 12px; overflow-x: auto;">';
    echo htmlspecialchars(print_r($order_detail, true));
    echo '</pre>';
    echo '</div>';
    
    // キッチン用の詳細表示コンテナ
    echo '<div class="order-detail-content">';
    
    // 注文基本情報
    echo '<div class="order-detail-section">';
    echo '<h3><i class="fas fa-info-circle"></i> 注文基本情報</h3>';
    echo '<table class="order-detail-table">';
    echo '<tr><td>注文ID</td><td>' . htmlspecialchars($order_detail['unique_key'] ?? 'N/A') . '</td></tr>';
    echo '<tr><td>注文日時</td><td>' . htmlspecialchars($order_detail['ordered'] ?? 'N/A') . '</td></tr>';
    echo '<tr><td>ステータス</td><td>' . htmlspecialchars($order_detail['dispatch_status'] ?? 'N/A') . '</td></tr>';
    echo '<tr><td>合計金額</td><td>¥' . number_format($order_detail['total'] ?? 0) . '</td></tr>';
    echo '</table>';
    echo '</div>';
    
    // お客様情報（BASE APIの直接フィールドを使用）
    echo '<div class="order-detail-section">';
    echo '<h3><i class="fas fa-user"></i> お客様情報</h3>';
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
    echo '</div>';
    
    // 配送先情報（order_receiver）
    if (isset($order_detail['order_receiver']) && is_array($order_detail['order_receiver'])) {
        echo '<div class="order-detail-section">';
        echo '<h3><i class="fas fa-truck"></i> 配送先情報</h3>';
        echo '<table class="order-detail-table">';
        $receiver = $order_detail['order_receiver'];
        echo '<tr><td>配送先お名前</td><td>' . htmlspecialchars(($receiver['last_name'] ?? '') . ' ' . ($receiver['first_name'] ?? '')) . '</td></tr>';
        echo '<tr><td>配送先電話番号</td><td>' . htmlspecialchars($receiver['tel'] ?? 'N/A') . '</td></tr>';
        echo '<tr><td>配送先郵便番号</td><td>' . htmlspecialchars($receiver['zip_code'] ?? 'N/A') . '</td></tr>';
        echo '<tr><td>配送先都道府県</td><td>' . htmlspecialchars($receiver['prefecture'] ?? 'N/A') . '</td></tr>';
        echo '<tr><td>配送先住所1</td><td>' . htmlspecialchars($receiver['address'] ?? 'N/A') . '</td></tr>';
        echo '<tr><td>配送先住所2</td><td>' . htmlspecialchars($receiver['address2'] ?? 'N/A') . '</td></tr>';
        echo '<tr><td>配送先国</td><td>' . htmlspecialchars($receiver['country'] ?? 'N/A') . '</td></tr>';
        echo '</table>';
        echo '</div>';
    }
    
    // 商品情報（BASE APIのorder_itemsキーを使用）
    if (isset($order_detail['order_items']) && is_array($order_detail['order_items'])) {
        echo '<div class="order-detail-section">';
        echo '<h3><i class="fas fa-box"></i> 商品情報</h3>';
        echo '<table class="items-table">';
        echo '<thead><tr><th>商品名</th><th>商品コード</th><th>バリエーション</th><th>数量</th><th>単価</th><th>小計</th><th>ステータス</th></tr></thead>';
        echo '<tbody>';
        
        foreach ($order_detail['order_items'] as $item) {
            echo '<tr>';
            echo '<td>' . htmlspecialchars($item['title'] ?? 'N/A') . '</td>';
            echo '<td>' . htmlspecialchars($item['item_identifier'] ?? 'N/A') . '</td>';
            echo '<td>' . htmlspecialchars($item['variation'] ?? 'N/A') . '</td>';
            echo '<td>' . htmlspecialchars($item['amount'] ?? 'N/A') . '</td>';
            echo '<td>¥' . number_format($item['price'] ?? 0) . '</td>';
            echo '<td>¥' . number_format($item['total'] ?? 0) . '</td>';
            echo '<td>' . htmlspecialchars($item['status'] ?? 'N/A') . '</td>';
            echo '</tr>';
        }
        
        echo '</tbody>';
        echo '</table>';
        echo '</div>';
    }
    
    // 配送情報
    if (isset($order_detail['shipping']) && is_array($order_detail['shipping'])) {
        echo '<div class="order-detail-section">';
        echo '<h3><i class="fas fa-truck"></i> 配送情報</h3>';
        echo '<table class="order-detail-table">';
        foreach ($order_detail['shipping'] as $key => $value) {
            if (is_array($value)) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE);
            }
            echo '<tr><td>' . htmlspecialchars($key) . '</td><td>' . htmlspecialchars($value) . '</td></tr>';
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
