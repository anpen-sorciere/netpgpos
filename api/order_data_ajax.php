<?php
session_start();
require_once __DIR__ . '/../../common/config.php';
require_once __DIR__ . '/../../common/dbconnect.php';
require_once __DIR__ . '/../../common/functions.php';
require_once __DIR__ . '/base_practical_auto_manager.php';

// デバッグ: スクリプト開始を表示
echo '<div style="background-color: #fff3cd; padding: 10px; margin: 10px 0; border-radius: 4px; font-size: 0.8em;">';
echo '<strong>DEBUG:</strong> order_data_ajax.php が実行されました | ';
echo '時刻: ' . date('H:i:s') . ' | ';
echo 'セッションID: ' . session_id();
echo '</div>';

// 認証チェック（新しいシステム）
try {
    $auth_status = (new BasePracticalAutoManager())->getAuthStatus();
    $orders_ok = isset($auth_status['read_orders']['authenticated']) && $auth_status['read_orders']['authenticated'];
    $items_ok = isset($auth_status['read_items']['authenticated']) && $auth_status['read_items']['authenticated'];
    
    // デバッグ: 認証状況を表示
    echo '<div style="background-color: #e7f3ff; padding: 10px; margin: 10px 0; border-radius: 4px; font-size: 0.8em;">';
    echo '<strong>認証状況:</strong> read_orders: ' . ($orders_ok ? 'OK' : 'NG') . ' | ';
    echo 'read_items: ' . ($items_ok ? 'OK' : 'NG');
    echo '</div>';
    
    
    // 認証が必要な場合の表示
    if (!$orders_ok || !$items_ok) {
        echo '<div class="no-orders" style="text-align: center; padding: 20px; color: #dc3545;">';
        echo '<h3>BASE API認証が必要です</h3>';
        echo '<p>注文データを取得するには認証が必要です。</p>';
        echo '<p>ページを再読み込みして自動認証を実行してください。</p>';
        echo '</div>';
        exit;
    }
} catch (Exception $e) {
    echo '<div class="no-orders" style="text-align: center; padding: 20px; color: #dc3545;">';
    echo '<h3>認証チェックエラー</h3>';
    echo '<p>エラー: ' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '<p>ファイル: ' . htmlspecialchars($e->getFile()) . '</p>';
    echo '<p>行: ' . htmlspecialchars($e->getLine()) . '</p>';
    echo '</div>';
    exit;
}

try {
    $practical_manager = new BasePracticalAutoManager();
    
    // ページング設定
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $limit = 50;
    $offset = ($page - 1) * $limit;
    
    $combined_data = $practical_manager->getCombinedOrderData(1000); // 全件取得してページング処理
    $orders_data = $combined_data['merged_orders'] ?? [];
    
    // データ構造を確認して適切に注文データを取得
    if (isset($orders_data['orders'])) {
        // 従来の構造: merged_orders.orders
        $all_orders = $orders_data['orders'];
    } else {
        // 新しい構造: merged_orders自体が注文配列
        $all_orders = $orders_data;
    }

    // ユーザー要望によるフィルター: 3ヶ月以内 かつ [未対応, 対応中, 入金待ち] のみ表示
    $filtered_orders = [];
    $three_months_ago = strtotime('-3 months'); // 3ヶ月前のタイムスタンプ
    
    // 表示対象のステータス
    $target_statuses = [
        'ordered',    // 未対応
        'shipping',   // 対応中
        'unpaid'      // 入金待ち
    ];

    foreach ($all_orders as $order) {
        $order_time = is_numeric($order['ordered']) ? $order['ordered'] : strtotime($order['ordered']);
        
        // 1. 期間チェック (3ヶ月以内)
        if ($order_time < $three_months_ago) {
            continue;
        }

        // 2. ステータスチェック
        $status = $order['dispatch_status'] ?? '';
        
        // dispatch_statusがない場合のフォールバック（cancelledなどは除外）
        if (empty($status)) {
            if (isset($order['cancelled'])) continue; // キャンセルは除外
            if (isset($order['dispatched'])) continue; // 対応済は除外
            // ここに来るのは未対応か入金待ち
            if (isset($order['payment']) && $order['payment'] !== 'paid') {
                $status = 'unpaid';
            } else {
                $status = 'ordered';
            }
        }

        if (in_array($status, $target_statuses)) {
            $filtered_orders[] = $order;
        }
    }
    
    // フィルタリング結果を新しい対象とする
    $all_orders = $filtered_orders;
    
    // 注文日時で並び替え（新しいものが先頭）
    usort($all_orders, function($a, $b) {
        $date_a = $a['ordered'] ?? 0;
        $date_b = $b['ordered'] ?? 0;
        return $date_b - $date_a; // 降順（新しいものが先頭）
    });
    
    // ページング処理
    $total_orders = count($all_orders);
    $total_pages = ceil($total_orders / $limit);
    $orders = array_slice($all_orders, $offset, $limit);
    
    // デバッグ: 最初の3件の注文のdispatch_statusを確認
    if (count($orders) > 0) {
        echo '<div style="background-color: #f0f8ff; padding: 10px; margin: 10px 0; border-radius: 4px; font-size: 0.8em;">';
        echo '<strong>AJAX ステータスデバッグ（最初の3件）:</strong><br>';
        for ($i = 0; $i < min(3, count($orders)); $i++) {
            $order = $orders[$i];
            $order_id = $order['unique_key'] ?? 'N/A';
            $dispatch_status = $order['dispatch_status'] ?? 'N/A';
            $ordered = $order['ordered'] ?? 'N/A';
            echo '注文' . ($i + 1) . ': ' . $order_id . ' | dispatch_status: ' . $dispatch_status . ' | ordered: ' . $ordered . '<br>';
        }
        echo '</div>';
    }
    
    // デバッグ: データ取得状況を確認
    echo '<div style="background-color: #e7f3ff; padding: 10px; margin: 10px 0; border-radius: 4px; font-size: 0.8em;">';
    echo '<strong>AJAX デバッグ:</strong> 全注文数: ' . $total_orders . ' | ';
    echo '現在ページ: ' . $page . '/' . $total_pages . ' | ';
    echo '表示件数: ' . count($orders) . ' | ';
    echo 'オフセット: ' . $offset;
    echo '</div>';
    
} catch (Exception $e) {
    echo '<div class="no-orders" style="text-align: center; padding: 20px; color: #dc3545;">データ取得エラー: ' . htmlspecialchars($e->getMessage()) . '</div>';
    exit;
}

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
        
        // 商品ごとの情報はAJAXで動的に取得するため、ここでは何も表示しない
        
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
                case 'ordered': $status = '未対応'; $status_class = 'status-ordered'; break;
                case 'unshippable': $status = '対応開始前'; $status_class = 'status-unshippable'; break;
                case 'shipping': $status = '配送中'; $status_class = 'status-shipping'; break;
                case 'dispatched': $status = '対応済'; $status_class = 'status-dispatched'; break;
                case 'cancelled': $status = 'キャンセル'; $status_class = 'status-cancelled'; break;
                default: $status = '未対応'; $status_class = 'status-ordered'; break;
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
        
        // 商品ごとの情報（AJAXで動的に追加）
        echo '<div class="item-details" data-order-id="' . $order_id . '">';
        echo '<span class="item-details-placeholder">商品情報読み込み中...</span>';
        echo '</div>';
        
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
?>