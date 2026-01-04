<?php
session_start();
require_once __DIR__ . '/../../common/config.php';
require_once __DIR__ . '/../../common/dbconnect.php';
require_once __DIR__ . '/../../common/functions.php';
require_once __DIR__ . '/base_practical_auto_manager.php';


// 認証チェック（新しいシステム）
try {
    $auth_status = (new BasePracticalAutoManager())->getAuthStatus();
    $orders_ok = isset($auth_status['read_orders']['authenticated']) && $auth_status['read_orders']['authenticated'];
    $items_ok = isset($auth_status['read_items']['authenticated']) && $auth_status['read_items']['authenticated'];
    
    
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
    
    // ユーザー要望の「過去3ヶ月以内」の注文を取得するため、ループ処理で取得
    
    $all_orders = [];
    $offset_fetch = 0;
    $limit_fetch = 100; // APIリクエスト上限
    $max_fetch_count = 2000; // スキャン上限（安全のため）
    $three_months_ago = strtotime('-3 months');
    
    $fetch_count = 0;
    
    // データ取得ループ
    while ($fetch_count < $max_fetch_count) {
        $response = $practical_manager->getDataWithAutoAuth(
            'read_orders', 
            '/orders', 
            ['limit' => $limit_fetch, 'offset' => $offset_fetch]
        );
        
        $batch_orders = $response['orders'] ?? [];
        $batch_count = count($batch_orders);
        
        if ($batch_count === 0) {
            break; // データなし、終了
        }
        
        $date_limit_reached = false;
        
        foreach ($batch_orders as $order) {
            $order_time = is_numeric($order['ordered']) ? $order['ordered'] : strtotime($order['ordered']);
            
            // 期間外（3ヶ月以上前）のデータに到達したらフラグ
            if ($order_time < $three_months_ago) {
                $date_limit_reached = true;
                // ここではcontinue（同じバッチ内に古いのが混ざっている可能性考慮）
                continue; 
            }
            
            $all_orders[] = $order;
        }
        
        $fetch_count += $batch_count;
        $offset_fetch += $batch_count; // 重要: 実際に取得できた件数分だけオフセットを進める
        
        // 期間外のデータが含まれていたなら、それより古いデータは不要なので終了
        if ($date_limit_reached) {
            break;
        }
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

// ページURL生成ヘルパー関数 (JSで処理するためhrefはjavascript:void(0)にしてonclickを使うなどの工夫が必要だが、
// ここでは order_monitor.php の既存実装に合わせてリンクまたはボタンを生成)
// ただしAJAX遷移なので、href="?page=X" ではなく onclick="loadPage(X)" 的な挙動が望ましいが、
// order_monitor.phpのrefreshOrderDataはURLパラメータを見ていないかもしれない。
// getCurrentPage()は window.location.search を見ている。
// よって、ページングは href="?page=X" で画面遷移させる形式（order_monitor.phpの仕様）に従う。
// ※画面遷移なしでやりたいが、既存が ?page=X 前提のコード。

function buildPageUrl($p) {
    $params = $_GET;
    $params['page'] = $p;
    return '?' . http_build_query($params);
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
        
        // サプライズ判定
        $is_surprise = false;
        $surprise_date = '';
        if (isset($order['order_items']) && is_array($order['order_items'])) {
            foreach ($order['order_items'] as $item) {
                if (isset($item['options']) && is_array($item['options'])) {
                    foreach ($item['options'] as $option) {
                        $opt_name = $option['option_name'] ?? '';
                        if (mb_strpos($opt_name, 'サプライズ') !== false) {
                            $is_surprise = true;
                            $surprise_date = $option['option_value'] ?? '';
                            break 2; // アイテムループも抜ける
                        }
                    }
                }
            }
        }
        
        $row_class = $is_surprise ? 'surprise-row' : '';
        
        echo '<tr class="' . $row_class . '">';
        
        // 注文ヘッダー列
        echo '<td class="order-header">';
        echo '<div class="order-header-info">';
        echo '<div class="order-id">#' . $order_id . '</div>';
        echo '<div class="order-date">' . htmlspecialchars($date_value) . '</div>';
        echo '<div class="order-status ' . $status_class . '">' . htmlspecialchars($status) . '</div>';
        
        if ($is_surprise) {
            echo '<div class="surprise-badge"><i class="fas fa-gift"></i> サプライズ設定あり (' . htmlspecialchars($surprise_date) . ')</div>';
        }
        
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
                        $opt_name = $option['option_name'] ?? 'N/A';
                        $opt_value = $option['option_value'] ?? 'N/A';
                        
                        $is_surprise_opt = (mb_strpos($opt_name, 'サプライズ') !== false);
                        // 視認性向上のため黄色系＋枠線に変更
                        $opt_style = $is_surprise_opt ? 'style="background-color: #ffc107; color: #000; font-weight: bold; border: 2px solid #dc3545; padding: 4px 8px; border-radius: 4px; display:inline-block; font-size: 1.1em;"' : '';
                        $icon = $is_surprise_opt ? '<i class="fas fa-gift"></i> ' : '';
                        
                        echo '<div class="option-item" ' . $opt_style . '>';
                        echo $icon . htmlspecialchars($opt_name) . ': ' . htmlspecialchars($opt_value);
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

// ページネーション出力
echo '<div class="pagination-info">';
echo '<div class="pagination-stats">';
echo '条件一致 ' . $total_orders . ' 件中 ' . ($offset + 1) . '-' . min($offset + $limit, $total_orders) . ' 件を表示 (' . $page . '/' . $total_pages . ' ページ)';
echo '</div>';
echo '<div class="pagination-nav">';

if ($page > 1) {
    echo '<a href="' . buildPageUrl(1) . '" class="btn btn-sm btn-outline-primary">最初</a>';
    echo '<a href="' . buildPageUrl($page - 1) . '" class="btn btn-sm btn-outline-primary">前へ</a>';
}

$start_page = max(1, $page - 2);
$end_page = min($total_pages, $page + 2);

for ($i = $start_page; $i <= $end_page; $i++) {
    if ($i == $page) {
        echo '<span class="btn btn-sm btn-primary">' . $i . '</span>';
    } else {
        echo '<a href="' . buildPageUrl($i) . '" class="btn btn-sm btn-outline-primary">' . $i . '</a>';
    }
}

if ($page < $total_pages) {
    echo '<a href="' . buildPageUrl($page + 1) . '" class="btn btn-sm btn-outline-primary">次へ</a>';
    echo '<a href="' . buildPageUrl($total_pages) . '" class="btn btn-sm btn-outline-primary">最後</a>';
}

echo '</div>';
echo '</div>';
?>