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
    
    // 注文詳細を取得
    $order_detail = $api->getOrderDetail($order_id);
    
    if (empty($order_detail)) {
        echo '<div style="color: #dc3545; padding: 20px;">注文詳細を取得できませんでした。</div>';
        exit;
    }
    
    // デバッグ用：詳細データ構造を表示
    echo '<div style="background-color: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px;">';
    echo '<h4>デバッグ: 注文詳細データ構造</h4>';
    echo '<pre style="font-size: 12px; overflow-x: auto;">';
    echo htmlspecialchars(print_r($order_detail, true));
    echo '</pre>';
    echo '</div>';
    
    // 注文基本情報
    echo '<div style="background-color: #e9ecef; padding: 15px; border-radius: 8px; margin-bottom: 20px;">';
    echo '<h3><i class="fas fa-info-circle"></i> 注文基本情報</h3>';
    echo '<table style="width: 100%; border-collapse: collapse;">';
    echo '<tr><td style="padding: 8px; border-bottom: 1px solid #dee2e6; font-weight: bold;">注文ID</td><td style="padding: 8px; border-bottom: 1px solid #dee2e6;">' . htmlspecialchars($order_detail['unique_key'] ?? 'N/A') . '</td></tr>';
    echo '<tr><td style="padding: 8px; border-bottom: 1px solid #dee2e6; font-weight: bold;">注文日時</td><td style="padding: 8px; border-bottom: 1px solid #dee2e6;">' . htmlspecialchars($order_detail['ordered'] ?? 'N/A') . '</td></tr>';
    echo '<tr><td style="padding: 8px; border-bottom: 1px solid #dee2e6; font-weight: bold;">ステータス</td><td style="padding: 8px; border-bottom: 1px solid #dee2e6;">' . htmlspecialchars($order_detail['dispatch_status'] ?? 'N/A') . '</td></tr>';
    echo '<tr><td style="padding: 8px; border-bottom: 1px solid #dee2e6; font-weight: bold;">合計金額</td><td style="padding: 8px; border-bottom: 1px solid #dee2e6;">¥' . number_format($order_detail['total'] ?? 0) . '</td></tr>';
    echo '</table>';
    echo '</div>';
    
    // お客様情報
    if (isset($order_detail['customer']) && is_array($order_detail['customer'])) {
        echo '<div style="background-color: #d1ecf1; padding: 15px; border-radius: 8px; margin-bottom: 20px;">';
        echo '<h3><i class="fas fa-user"></i> お客様情報</h3>';
        echo '<table style="width: 100%; border-collapse: collapse;">';
        foreach ($order_detail['customer'] as $key => $value) {
            if (is_array($value)) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE);
            }
            echo '<tr><td style="padding: 8px; border-bottom: 1px solid #dee2e6; font-weight: bold;">' . htmlspecialchars($key) . '</td><td style="padding: 8px; border-bottom: 1px solid #dee2e6;">' . htmlspecialchars($value) . '</td></tr>';
        }
        echo '</table>';
        echo '</div>';
    }
    
    // 商品情報
    if (isset($order_detail['items']) && is_array($order_detail['items'])) {
        echo '<div style="background-color: #d4edda; padding: 15px; border-radius: 8px; margin-bottom: 20px;">';
        echo '<h3><i class="fas fa-box"></i> 商品情報</h3>';
        echo '<table style="width: 100%; border-collapse: collapse;">';
        echo '<thead><tr style="background-color: #c3e6cb;"><th style="padding: 8px; border: 1px solid #dee2e6;">商品名</th><th style="padding: 8px; border: 1px solid #dee2e6;">数量</th><th style="padding: 8px; border: 1px solid #dee2e6;">単価</th><th style="padding: 8px; border: 1px solid #dee2e6;">小計</th></tr></thead>';
        echo '<tbody>';
        
        foreach ($order_detail['items'] as $item) {
            echo '<tr>';
            echo '<td style="padding: 8px; border: 1px solid #dee2e6;">' . htmlspecialchars($item['name'] ?? 'N/A') . '</td>';
            echo '<td style="padding: 8px; border: 1px solid #dee2e6;">' . htmlspecialchars($item['quantity'] ?? 'N/A') . '</td>';
            echo '<td style="padding: 8px; border: 1px solid #dee2e6;">¥' . number_format($item['price'] ?? 0) . '</td>';
            echo '<td style="padding: 8px; border: 1px solid #dee2e6;">¥' . number_format(($item['quantity'] ?? 0) * ($item['price'] ?? 0)) . '</td>';
            echo '</tr>';
        }
        
        echo '</tbody>';
        echo '</table>';
        echo '</div>';
    }
    
    // 配送情報
    if (isset($order_detail['shipping']) && is_array($order_detail['shipping'])) {
        echo '<div style="background-color: #fff3cd; padding: 15px; border-radius: 8px; margin-bottom: 20px;">';
        echo '<h3><i class="fas fa-truck"></i> 配送情報</h3>';
        echo '<table style="width: 100%; border-collapse: collapse;">';
        foreach ($order_detail['shipping'] as $key => $value) {
            if (is_array($value)) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE);
            }
            echo '<tr><td style="padding: 8px; border-bottom: 1px solid #dee2e6; font-weight: bold;">' . htmlspecialchars($key) . '</td><td style="padding: 8px; border-bottom: 1px solid #dee2e6;">' . htmlspecialchars($value) . '</td></tr>';
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
        echo '<div style="background-color: #f8d7da; padding: 15px; border-radius: 8px;">';
        echo '<h3><i class="fas fa-info"></i> その他の情報</h3>';
        echo '<table style="width: 100%; border-collapse: collapse;">';
        foreach ($other_fields as $field) {
            if (isset($order_detail[$field]) && $order_detail[$field] !== null) {
                $value = $order_detail[$field];
                if (is_array($value)) {
                    $value = json_encode($value, JSON_UNESCAPED_UNICODE);
                }
                echo '<tr><td style="padding: 8px; border-bottom: 1px solid #dee2e6; font-weight: bold;">' . htmlspecialchars($field) . '</td><td style="padding: 8px; border-bottom: 1px solid #dee2e6;">' . htmlspecialchars($value) . '</td></tr>';
            }
        }
        echo '</table>';
        echo '</div>';
    }
    
} catch (Exception $e) {
    echo '<div style="color: #dc3545; padding: 20px;">エラー: ' . htmlspecialchars($e->getMessage()) . '</div>';
}
?>
