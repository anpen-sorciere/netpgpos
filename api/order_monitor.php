<?php
// リアルタイム注文監視システム（第1段階）
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';
require_once __DIR__ . '/base_api_client.php';

session_start();

$utype = $_SESSION['utype'] ?? 1024;
$shop_name = '';
if ($utype == 1024) {
    $shop_name = 'ソルシエール';
} elseif ($utype == 2) {
    $shop_name = 'レーヴェス';
} elseif ($utype == 3) {
    $shop_name = 'コレクト';
}

// BASE API認証チェック
try {
    $baseApi = new BaseApiClient();
    
    if ($baseApi->needsAuth()) {
        $error_message = 'BASE API認証が必要です。';
        $orders = [];
    } else {
        // 注文データを取得
        $orders_data = $baseApi->getOrders(50, 0); // 最新50件
        $orders = $orders_data['orders'] ?? [];
        
        // 最新の注文が上に来るようにソート（注文日時順）
        if (!empty($orders)) {
            $sort_key = 'ordered'; // 注文日時でソート
            if (!isset($orders[0]['ordered'])) {
                // orderedが存在しない場合、他の日時キーを試す
                $possible_keys = ['modified', 'created_at', 'date', 'order_date'];
                foreach ($possible_keys as $key) {
                    if (isset($orders[0][$key])) {
                        $sort_key = $key;
                        break;
                    }
                }
            }
            
            try {
                usort($orders, function($a, $b) use ($sort_key) {
                    // キーの存在確認を追加
                    if (!isset($a[$sort_key]) || !isset($b[$sort_key])) {
                        return 0; // キーが存在しない場合は順序を変更しない
                    }
                    
                    $time_a = $a[$sort_key];
                    $time_b = $b[$sort_key];
                    
                    // タイムスタンプに変換
                    if (is_numeric($time_a)) {
                        $timestamp_a = $time_a;
                    } else {
                        $timestamp_a = strtotime($time_a);
                    }
                    
                    if (is_numeric($time_b)) {
                        $timestamp_b = $time_b;
                    } else {
                        $timestamp_b = strtotime($time_b);
                    }
                    
                    // 降順ソート（新しい日時が上に来る）
                    return $timestamp_b - $timestamp_a;
                });
            } catch (Exception $e) {
                // ソートエラーは無視
            }
        }
    }
} catch (Exception $e) {
    $error_message = 'エラー: ' . $e->getMessage();
    $orders = [];
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>リアルタイム注文監視 - <?= htmlspecialchars($shop_name) ?></title>
    <link href="https://use.fontawesome.com/releases/v5.6.1/css/all.css" rel="stylesheet">
    <link href="https://unpkg.com/sanitize.css" rel="stylesheet"/>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .order-monitor {
            background-color: #f8f9fa;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
        }
        
        .order-table {
            width: 100%;
            border-collapse: collapse;
            background-color: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        
        .order-table th {
            background-color: #3498db;
            color: white;
            padding: 15px;
            text-align: left;
            font-weight: bold;
        }
        
        .order-table td {
            padding: 12px 15px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .order-table tr:hover {
            background-color: #f8f9fa;
        }
        
        .order-table tr:last-child td {
            border-bottom: none;
        }
        
        .order-id {
            font-weight: bold;
            color: #2c3e50;
        }
        
        .order-date {
            color: #6c757d;
            font-size: 0.9em;
        }
        
        .order-status {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8em;
            font-weight: bold;
        }
        
        .status-unpaid {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .status-paid {
            background-color: #d1ecf1;
            color: #0c5460;
        }
        
        .status-shipped {
            background-color: #d4edda;
            color: #155724;
        }
        
        .status-cancelled {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .auto-refresh {
            background-color: #28a745;
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: inline-block;
        }
        
        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .no-orders {
            text-align: center;
            padding: 40px;
            color: #6c757d;
            font-style: italic;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1><i class="fas fa-shopping-cart"></i> リアルタイム注文監視</h1>
        <p class="shop-name"><?= htmlspecialchars($shop_name) ?></p>
        
        <?php if (isset($error_message)): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error_message) ?>
                <br><br>
                <a href="base_callback_debug.php" class="btn btn-primary">
                    <i class="fas fa-sign-in-alt"></i> BASE API認証を実行
                </a>
            </div>
        <?php else: ?>
            <div class="auto-refresh">
                <i class="fas fa-sync-alt"></i> 5秒間隔で自動更新中...
            </div>
            
            <div class="order-monitor">
                <h2><i class="fas fa-list"></i> 注文一覧（最新順）</h2>
                
                <?php if (empty($orders)): ?>
                    <div class="no-orders">
                        <i class="fas fa-inbox"></i><br>
                        注文データがありません
                    </div>
                <?php else: ?>
                    <table class="order-table">
                        <thead>
                            <tr>
                                <th>注文ID</th>
                                <th>注文日時</th>
                                <th>お客様名</th>
                                <th>ステータス</th>
                                <th>金額・決済</th>
                                <th>詳細</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $order): ?>
                                <tr>
                                    <td class="order-id">
                                        #<?= htmlspecialchars($order['unique_key'] ?? 'N/A') ?>
                                    </td>
                                    <td class="order-date">
                                        <?php
                                        $date_value = $order['ordered'] ?? 'N/A';
                                        
                                        if ($date_value !== 'N/A') {
                                            // タイムスタンプの形式を確認
                                            if (is_numeric($date_value)) {
                                                // Unix timestampの場合
                                                $date_value = date('Y/m/d H:i', $date_value);
                                            } else {
                                                // 文字列の場合
                                                $timestamp = strtotime($date_value);
                                                if ($timestamp !== false) {
                                                    $date_value = date('Y/m/d H:i', $timestamp);
                                                } else {
                                                    $date_value = '日時エラー';
                                                }
                                            }
                                        }
                                        echo htmlspecialchars($date_value);
                                        
                                        // 配送日時の表示
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
                                                    '0812' => '午前中',
                                                    '1214' => '12時~14時',
                                                    '1416' => '14時~16時',
                                                    '1618' => '16時~18時',
                                                    '1820' => '18時~20時',
                                                    '2021' => '20時~21時'
                                                ];
                                                $time_display = $time_zones[$time_zone] ?? $time_zone;
                                                echo ' ' . htmlspecialchars($time_display);
                                            }
                                            echo '</small>';
                                        }
                                        
                                        // キャンセル日時の表示
                                        if (isset($order['cancelled']) && $order['cancelled'] !== null) {
                                            echo '<br><small style="color: #dc3545;">キャンセル: ';
                                            $cancelled_value = $order['cancelled'];
                                            if (is_array($cancelled_value)) {
                                                echo htmlspecialchars(json_encode($cancelled_value, JSON_UNESCAPED_UNICODE));
                                            } else {
                                                // Unix timestampかどうかチェック
                                                if (is_numeric($cancelled_value)) {
                                                    // Unix timestampの場合
                                                    echo htmlspecialchars(date('Y/m/d H:i', $cancelled_value));
                                                } else {
                                                    // 文字列の場合
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
                                        
                                        // 発送日時の表示
                                        if (isset($order['dispatched']) && $order['dispatched'] !== null) {
                                            echo '<br><small style="color: #28a745;">発送: ';
                                            $dispatched_value = $order['dispatched'];
                                            if (is_array($dispatched_value)) {
                                                echo htmlspecialchars(json_encode($dispatched_value, JSON_UNESCAPED_UNICODE));
                                            } else {
                                                // Unix timestampかどうかチェック
                                                if (is_numeric($dispatched_value)) {
                                                    // Unix timestampの場合
                                                    echo htmlspecialchars(date('Y/m/d H:i', $dispatched_value));
                                                } else {
                                                    // 文字列の場合
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
                                        
                                        // 更新日時の表示（注文日時と異なる場合のみ）
                                        if (isset($order['modified']) && $order['modified'] !== null) {
                                            $modified_value = $order['modified'];
                                            $modified_timestamp = null;
                                            
                                            // modifiedの日時を取得
                                            if (is_array($modified_value)) {
                                                // 配列の場合はスキップ
                                            } else {
                                                if (is_numeric($modified_value)) {
                                                    $modified_timestamp = $modified_value;
                                                } else {
                                                    $modified_timestamp = strtotime($modified_value);
                                                }
                                            }
                                            
                                            // orderedの日時を取得
                                            $ordered_timestamp = null;
                                            $ordered_value = $order['ordered'] ?? null;
                                            if ($ordered_value !== null) {
                                                if (is_numeric($ordered_value)) {
                                                    $ordered_timestamp = $ordered_value;
                                                } else {
                                                    $ordered_timestamp = strtotime($ordered_value);
                                                }
                                            }
                                            
                                            // 更新日時が注文日時と異なる場合のみ表示
                                            if ($modified_timestamp !== null && $ordered_timestamp !== null && 
                                                $modified_timestamp !== $ordered_timestamp) {
                                                echo '<br><small style="color: #6c757d;">更新: ';
                                                echo htmlspecialchars(date('Y/m/d H:i', $modified_timestamp));
                                                echo '</small>';
                                            }
                                        }
                                        ?>
                                    </td>
                                    <td><?= htmlspecialchars(trim(($order['last_name'] ?? '') . ' ' . ($order['first_name'] ?? '')) ?: 'N/A') ?></td>
                                    <td>
                                        <?php
                                        $status = 'N/A';
                                        $status_class = 'status-unpaid';
                                        
                                        // BASE APIの正確なdispatch_statusに基づく判定
                                        if (isset($order['dispatch_status'])) {
                                            switch ($order['dispatch_status']) {
                                                case 'unpaid':
                                                    $status = '入金待ち';
                                                    $status_class = 'status-unpaid';
                                                    break;
                                                case 'ordered':
                                                    $status = '未対応';
                                                    $status_class = 'status-unpaid';
                                                    break;
                                                case 'unshippable':
                                                    $status = '対応開始前';
                                                    $status_class = 'status-paid';
                                                    break;
                                                case 'shipping':
                                                    $status = '配送中';
                                                    $status_class = 'status-paid';
                                                    break;
                                                case 'dispatched':
                                                    $status = '対応済';
                                                    $status_class = 'status-shipped';
                                                    break;
                                                case 'cancelled':
                                                    $status = 'キャンセル';
                                                    $status_class = 'status-cancelled';
                                                    break;
                                                default:
                                                    $status = '未対応';
                                                    $status_class = 'status-unpaid';
                                                    break;
                                            }
                                        }
                                        // dispatch_statusがない場合のフォールバック
                                        else {
                                            // cancelledキーでキャンセルをチェック
                                            if (isset($order['cancelled']) && $order['cancelled'] !== null) {
                                                $status = 'キャンセル';
                                                $status_class = 'status-cancelled';
                                            }
                                            // dispatchedキーで発送済みをチェック
                                            elseif (isset($order['dispatched']) && $order['dispatched'] !== null) {
                                                $status = '対応済';
                                                $status_class = 'status-shipped';
                                            }
                                            // paymentキーで支払い状況をチェック
                                            elseif (isset($order['payment'])) {
                                                if ($order['payment'] === 'paid' || $order['payment'] === true) {
                                                    $status = '対応開始前';
                                                    $status_class = 'status-paid';
                                                } else {
                                                    $status = '入金待ち';
                                                    $status_class = 'status-unpaid';
                                                }
                                            }
                                            // デフォルト
                                            else {
                                                $status = '未対応';
                                                $status_class = 'status-unpaid';
                                            }
                                        }
                                        ?>
                                        <span class="order-status <?= $status_class ?>">
                                            <?= htmlspecialchars($status) ?>
                                        </span>
                                    </td>
                                    <td>
                                        ¥<?= number_format($order['total'] ?? 0) ?>
                                        <?php if (isset($order['payment']) && $order['payment'] !== null): ?>
                                            <br><small style="color: #6c757d;">
                                                <?php
                                                $payment_value = $order['payment'];
                                                if (is_array($payment_value)) {
                                                    // 配列の場合は最初の要素を表示
                                                    $payment_value = $payment_value[0] ?? json_encode($payment_value, JSON_UNESCAPED_UNICODE);
                                                }
                                                
                                                $payment_methods = [
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
                                                echo htmlspecialchars($payment_methods[$payment_value] ?? $payment_value);
                                                ?>
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-secondary" onclick="showOrderDetail('<?= htmlspecialchars($order['unique_key'] ?? 'N/A') ?>')">
                                            <i class="fas fa-eye"></i> 詳細
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <div class="control-buttons">
            <a href="../base_data_sync_top.php?utype=<?= htmlspecialchars($utype) ?>" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> BASEデータ同期に戻る
            </a>
            <a href="../index.php?utype=<?= htmlspecialchars($utype) ?>" class="btn btn-secondary">
                <i class="fas fa-home"></i> メニューに戻る
            </a>
        </div>
    </div>

    <!-- 注文詳細モーダル -->
    <div id="orderModal" class="modal" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5);">
        <div class="modal-content" style="background-color: #fefefe; margin: 5% auto; padding: 20px; border: 1px solid #888; width: 90%; max-width: 800px; border-radius: 8px; max-height: 80%; overflow-y: auto;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2><i class="fas fa-shopping-cart"></i> 注文詳細 <span id="modalOrderId"></span></h2>
                <span class="close" onclick="closeModal()" style="color: #aaa; font-size: 28px; font-weight: bold; cursor: pointer;">&times;</span>
            </div>
            <div id="modalContent">
                <!-- 注文詳細内容がここに表示されます -->
            </div>
        </div>
    </div>

    <script>
        // 5秒間隔で自動更新
        setInterval(function() {
            location.reload();
        }, 5000);
        
        // 注文詳細表示（第2段階で実装予定）
        function showOrderDetail(orderId) {
            // モーダルを表示
            document.getElementById('orderModal').style.display = 'block';
            document.getElementById('modalOrderId').textContent = orderId;
            
            // ローディング表示
            document.getElementById('modalContent').innerHTML = '<div style="text-align: center; padding: 20px;"><i class="fas fa-spinner fa-spin"></i> 注文詳細を読み込み中...</div>';
            
            // 注文詳細を取得
            fetch('order_detail_ajax.php?order_id=' + encodeURIComponent(orderId))
                .then(response => response.text())
                .then(data => {
                    document.getElementById('modalContent').innerHTML = data;
                })
                .catch(error => {
                    document.getElementById('modalContent').innerHTML = '<div style="color: #dc3545; padding: 20px;">エラー: ' + error.message + '</div>';
                });
        }
        
        // モーダルを閉じる
        function closeModal() {
            document.getElementById('orderModal').style.display = 'none';
        }
        
        // モーダル外クリックで閉じる
        window.onclick = function(event) {
            var modal = document.getElementById('orderModal');
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        }
    </script>
</body>
</html>
