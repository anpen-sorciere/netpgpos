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
        $error_message = 'BASE API認証が必要です。注文管理権限で認証してください。';
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
                        return 0;
                    }
                    
                    $time_a = $a[$sort_key];
                    $time_b = $b[$sort_key];
                    
                    // 数値の場合はそのまま比較
                    if (is_numeric($time_a) && is_numeric($time_b)) {
                        return $time_b - $time_a; // 降順
                    }
                    
                    // 文字列の場合はタイムスタンプに変換して比較
                    $timestamp_a = strtotime($time_a);
                    $timestamp_b = strtotime($time_b);
                    
                    if ($timestamp_a === false || $timestamp_b === false) {
                        return 0;
                    }
                    
                    return $timestamp_b - $timestamp_a; // 降順
                });
            } catch (Exception $e) {
                // ソートエラーが発生した場合はそのまま
                error_log('ソートエラー: ' . $e->getMessage());
            }
        }
    }
} catch (Exception $e) {
    $error_message = 'BASE API接続エラー: ' . $e->getMessage();
    $orders = [];
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>リアルタイム注文監視システム - <?= htmlspecialchars($shop_name) ?></title>
    <link href="https://use.fontawesome.com/releases/v5.6.1/css/all.css" rel="stylesheet">
    <link href="https://unpkg.com/sanitize.css" rel="stylesheet"/>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
            color: #333;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            text-align: center;
        }
        
        .header h1 {
            margin: 0;
            font-size: 2em;
            font-weight: 300;
        }
        
        .header .shop-name {
            font-size: 1.2em;
            opacity: 0.9;
            margin-top: 5px;
        }
        
        .controls {
            padding: 20px;
            background: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .refresh-info {
            display: flex;
            align-items: center;
            gap: 15px;
            font-size: 0.9em;
            color: #6c757d;
        }
        
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 0.9em;
            transition: all 0.2s;
        }
        
        .btn-primary {
            background-color: #007bff;
            color: white;
        }
        
        .btn-primary:hover {
            background-color: #0056b3;
        }
        
        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background-color: #545b62;
        }
        
        .btn-xs {
            padding: 4px 8px;
            font-size: 0.75em;
        }
        
        .btn-info {
            background-color: #17a2b8;
            color: white;
        }
        
        .btn-warning {
            background-color: #ffc107;
            color: #212529;
        }
        
        .btn-success {
            background-color: #28a745;
            color: white;
        }
        
        .order-table {
            width: 100%;
            border-collapse: collapse;
            margin: 0;
        }
        
        .order-table th {
            background-color: #343a40;
            color: white;
            padding: 15px;
            text-align: left;
            font-weight: 600;
            border: none;
        }
        
        .order-table td {
            padding: 15px;
            border-bottom: 1px solid #dee2e6;
            vertical-align: top;
        }
        
        .order-table tr:hover {
            background-color: #f8f9fa;
        }
        
        .order-header {
            min-width: 300px;
        }
        
        .order-header-info {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .order-id {
            font-weight: bold;
            font-size: 1.1em;
            color: #007bff;
        }
        
        .order-date {
            font-size: 0.9em;
            color: #6c757d;
        }
        
        .order-status {
            font-weight: bold;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.85em;
            display: inline-block;
            width: fit-content;
        }
        
        .status-unpaid {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .status-paid {
            background-color: #d4edda;
            color: #155724;
        }
        
        .status-shipped {
            background-color: #d1ecf1;
            color: #0c5460;
        }
        
        .status-cancelled {
            background-color: #f5c6cb;
            color: #721c24;
        }
        
        .customer-name {
            font-weight: 500;
        }
        
        .nickname {
            font-weight: bold;
            color: #e74c3c;
            font-size: 0.9em;
            background-color: #fdf2f2;
            padding: 2px 6px;
            border-radius: 3px;
            border-left: 3px solid #e74c3c;
        }
        
        .total-amount {
            font-weight: bold;
            color: #28a745;
            font-size: 1.1em;
        }
        
        .popup-buttons {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
            margin-top: 10px;
        }
        
        .order-items {
            min-width: 400px;
        }
        
        .item-detail {
            padding: 10px;
            background-color: #f8f9fa;
            border-radius: 4px;
            margin-bottom: 10px;
        }
        
        .item-name {
            font-weight: bold;
            color: #495057;
            margin-bottom: 5px;
        }
        
        .item-variation {
            font-size: 0.85em;
            color: #6c757d;
            margin-bottom: 3px;
        }
        
        .item-quantity, .item-price, .item-total, .item-status {
            font-size: 0.9em;
            margin-bottom: 2px;
        }
        
        .item-options {
            margin-top: 8px;
            padding-top: 8px;
            border-top: 1px solid #dee2e6;
        }
        
        .option-item {
            font-size: 0.8em;
            color: #6c757d;
            margin-bottom: 2px;
        }
        
        .item-separator {
            margin: 10px 0;
            border: none;
            border-top: 1px solid #dee2e6;
        }
        
        .no-items {
            color: #6c757d;
            font-style: italic;
            text-align: center;
            padding: 20px;
        }
        
        .no-orders {
            text-align: center;
            padding: 60px 20px;
            color: #6c757d;
            font-size: 1.2em;
        }
        
        .no-orders i {
            font-size: 3em;
            margin-bottom: 20px;
            opacity: 0.5;
        }
        
        .error-message {
            background-color: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 4px;
            margin: 20px;
            text-align: center;
        }
        
        .popup-modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        
        .popup-content {
            background-color: white;
            margin: 5% auto;
            padding: 20px;
            border-radius: 8px;
            width: 80%;
            max-width: 600px;
            max-height: 80vh;
            overflow-y: auto;
            position: relative;
        }
        
        .popup-close {
            position: absolute;
            right: 15px;
            top: 15px;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            color: #aaa;
        }
        
        .popup-close:hover {
            color: #000;
        }
        
        .popup-detail-content {
            margin-top: 20px;
        }
        
        .popup-detail-content h4 {
            color: #495057;
            border-bottom: 2px solid #dee2e6;
            padding-bottom: 5px;
            margin-top: 20px;
        }
        
        .popup-detail-content p {
            margin-bottom: 10px;
            line-height: 1.5;
        }
        
        .popup-detail-content strong {
            color: #343a40;
        }
        
        @media (max-width: 768px) {
            .container {
                margin: 0;
                border-radius: 0;
            }
            
            .controls {
                flex-direction: column;
                align-items: stretch;
            }
            
            .refresh-info {
                justify-content: center;
            }
            
            .order-table {
                font-size: 0.9em;
            }
            
            .order-table th,
            .order-table td {
                padding: 10px 8px;
            }
            
            .popup-content {
                width: 95%;
                margin: 10% auto;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-chart-line"></i> リアルタイム注文監視システム</h1>
            <div class="shop-name"><?= htmlspecialchars($shop_name) ?></div>
        </div>
        
        <div class="controls">
            <div class="refresh-info">
                <span><i class="fas fa-sync-alt"></i> 自動更新: 30秒間隔</span>
                <span><i class="fas fa-clock"></i> 最終更新: <span id="last-update">-</span></span>
                <span><i class="fas fa-list"></i> 表示件数: <span id="order-count">-</span>件</span>
            </div>
            <div>
                <button class="btn btn-primary" onclick="refreshOrderData()">
                    <i class="fas fa-sync-alt"></i> 手動更新
                </button>
                <a href="scope_switcher.php" class="btn btn-secondary">
                    <i class="fas fa-cog"></i> API設定
                </a>
            </div>
        </div>
        
        <div id="orders-container">
            <?php if (isset($error_message)): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-triangle"></i><br>
                    <?= htmlspecialchars($error_message) ?><br>
                    <a href="scope_switcher.php" style="background-color: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; margin-top: 10px; display: inline-block;">BASE API認証を実行</a>
                </div>
            <?php elseif (empty($orders)): ?>
                <div class="no-orders">
                    <i class="fas fa-inbox"></i><br>
                    注文データがありません
                </div>
            <?php else: ?>
                <div id="orders-table-container">
                <table class="order-table">
                    <thead>
                        <tr>
                            <th>注文ヘッダー</th>
                            <th>商品明細</th>
                            <th>詳細</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $order): ?>
                            <?php
                            // 注文ヘッダー情報
                            $order_id = htmlspecialchars($order['unique_key'] ?? 'N/A');
                            $customer_name = htmlspecialchars(trim(($order['last_name'] ?? '') . ' ' . ($order['first_name'] ?? '')) ?: 'N/A');
                            
                            // ニックネームを抽出（オプション情報から）
                            $nicknames = [];
                            if (isset($order['order_items']) && is_array($order['order_items'])) {
                                foreach ($order['order_items'] as $item) {
                                    if (isset($item['options']) && is_array($item['options'])) {
                                        foreach ($item['options'] as $option) {
                                            $option_name = $option['option_name'] ?? '';
                                            $option_value = $option['option_value'] ?? '';
                                            
                                            // ニックネーム関連のオプションを検索（より柔軟に）
                                            if (stripos($option_name, 'ニックネーム') !== false || 
                                                stripos($option_name, 'nickname') !== false ||
                                                stripos($option_name, 'お名前') !== false ||
                                                stripos($option_name, '名前') !== false ||
                                                stripos($option_name, 'name') !== false ||
                                                stripos($option_name, '呼び名') !== false ||
                                                stripos($option_name, '愛称') !== false) {
                                                if (!empty($option_value) && !in_array($option_value, $nicknames)) {
                                                    $nicknames[] = htmlspecialchars($option_value);
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                            $nickname_display = !empty($nicknames) ? implode(', ', $nicknames) : '';
                            
                            // デバッグ情報（開発時のみ）
                            if (isset($_GET['debug']) && $_GET['debug'] === 'nickname') {
                                echo '<div style="background: #f0f0f0; padding: 10px; margin: 5px 0; border: 1px solid #ccc;">';
                                echo '<strong>デバッグ情報 (注文ID: ' . $order_id . '):</strong><br>';
                                echo '抽出されたニックネーム: ' . ($nickname_display ?: 'なし') . '<br>';
                                echo '検索キーワード: ニックネーム, nickname, お名前, 名前, name, 呼び名, 愛称<br>';
                                if (isset($order['order_items']) && is_array($order['order_items'])) {
                                    foreach ($order['order_items'] as $itemIndex => $item) {
                                        echo '商品' . ($itemIndex + 1) . ' (' . htmlspecialchars($item['title'] ?? 'N/A') . ') のオプション: ';
                                        if (isset($item['options']) && is_array($item['options'])) {
                                            foreach ($item['options'] as $option) {
                                                $option_name = $option['option_name'] ?? '';
                                                $option_value = $option['option_value'] ?? '';
                                                echo '[' . htmlspecialchars($option_name) . '=' . htmlspecialchars($option_value) . '] ';
                                                
                                                // 各キーワードでのマッチングテスト
                                                $matches = [];
                                                if (stripos($option_name, 'ニックネーム') !== false) $matches[] = 'ニックネーム';
                                                if (stripos($option_name, 'nickname') !== false) $matches[] = 'nickname';
                                                if (stripos($option_name, 'お名前') !== false) $matches[] = 'お名前';
                                                if (stripos($option_name, '名前') !== false) $matches[] = '名前';
                                                if (stripos($option_name, 'name') !== false) $matches[] = 'name';
                                                if (stripos($option_name, '呼び名') !== false) $matches[] = '呼び名';
                                                if (stripos($option_name, '愛称') !== false) $matches[] = '愛称';
                                                
                                                if (!empty($matches)) {
                                                    echo '<span style="color: #28a745; font-weight: bold;"> ✓ マッチ: ' . implode(', ', $matches) . '</span>';
                                                }
                                            }
                                        } else {
                                            echo 'なし';
                                        }
                                        echo '<br>';
                                    }
                                }
                                echo '</div>';
                            }
                            
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
                            ?>
                            <tr>
                                <!-- 注文ヘッダー列 -->
                                <td class="order-header">
                                    <div class="order-header-info">
                                        <div class="order-id">#<?= $order_id ?></div>
                                        <div class="order-date"><?= htmlspecialchars($date_value) ?></div>
                                        <div class="order-status <?= $status_class ?>"><?= htmlspecialchars($status) ?></div>
                                        <div class="customer-name"><?= $customer_name ?></div>
                                        <?php if (!empty($nickname_display)): ?>
                                            <div class="nickname">ニックネーム: <?= $nickname_display ?></div>
                                        <?php endif; ?>
                                        <div class="total-amount"><?= $total_amount ?></div>
                                        
                                        <!-- ポップアップボタン群 -->
                                        <div class="popup-buttons">
                                            <button class="btn btn-xs btn-info" onclick="showPaymentInfo('<?= $order_id ?>')">
                                                <i class="fas fa-credit-card"></i> 決済
                                            </button>
                                            <button class="btn btn-xs btn-warning" onclick="showCustomerInfo('<?= $order_id ?>')">
                                                <i class="fas fa-user"></i> お客様
                                            </button>
                                            <button class="btn btn-xs btn-success" onclick="showShippingInfo('<?= $order_id ?>')">
                                                <i class="fas fa-truck"></i> 配送
                                            </button>
                                            <button class="btn btn-xs btn-secondary" onclick="showOtherInfo('<?= $order_id ?>')">
                                                <i class="fas fa-info"></i> その他
                                            </button>
                                        </div>
                                    </div>
                                </td>
                                
                                <!-- 商品明細列（商品情報のみ） -->
                                <td class="order-items">
                                    <?php if (isset($order['order_items']) && is_array($order['order_items'])): ?>
                                        <?php foreach ($order['order_items'] as $index => $item): ?>
                                            <div class="item-detail">
                                                <div class="item-name"><?= htmlspecialchars($item['title'] ?? 'N/A') ?></div>
                                                
                                                <?php if (!empty($item['variation'])): ?>
                                                    <div class="item-variation">バリエーション: <?= htmlspecialchars($item['variation']) ?></div>
                                                <?php endif; ?>
                                                
                                                <div class="item-quantity">数量: <?= htmlspecialchars($item['amount'] ?? 'N/A') ?></div>
                                                <div class="item-price">単価: ¥<?= number_format($item['price'] ?? 0) ?></div>
                                                <div class="item-total">小計: ¥<?= number_format($item['total'] ?? 0) ?></div>
                                                <div class="item-status">ステータス: <?= htmlspecialchars($item['status'] ?? 'N/A') ?></div>
                                                
                                                <!-- オプション情報 -->
                                                <?php if (isset($item['options']) && is_array($item['options']) && !empty($item['options'])): ?>
                                                    <div class="item-options">
                                                        <?php foreach ($item['options'] as $option): ?>
                                                            <div class="option-item">
                                                                <?= htmlspecialchars($option['option_name'] ?? 'N/A') ?>: <?= htmlspecialchars($option['option_value'] ?? 'N/A') ?>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <?php if ($index < count($order['order_items']) - 1): ?>
                                                <hr class="item-separator">
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    <?php else: ?>
                                        <div class="no-items">商品情報なし</div>
                                    <?php endif; ?>
                                </td>
                                
                                <!-- 詳細ボタン列 -->
                                <td>
                                    <button class="btn btn-sm btn-secondary" id="toggle-<?= $order_id ?>" onclick="toggleOrderDetail('<?= $order_id ?>')">
                                        <i class="fas fa-chevron-down"></i> 全詳細
                                    </button>
                                </td>
                            </tr>
                            
                            <!-- 注文詳細行（全情報表示用） -->
                            <tr id="detail-<?= $order_id ?>" style="display: none;">
                                <td colspan="3" style="padding: 0;">
                                    <!-- 全詳細内容がここに表示されます -->
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // 現在のデータを保存する変数
        var currentOrderData = null;
        
        // AJAXでデータのみを更新（データ変更検知付き）
        function refreshOrderData() {
            showUpdateIndicator(); // 更新開始を表示
            
            fetch('order_data_ajax.php')
                .then(response => {
                    if (!response.ok) {
                        throw new Error('ネットワークエラー: ' + response.status);
                    }
                    return response.text();
                })
                .then(data => {
                    // 認証エラーのチェック
                    if (data.includes('アクセストークンが無効') || data.includes('再認証が必要') || data.includes('BASE API認証が必要')) {
                        console.warn('認証エラーが発生しました。再認証が必要です。');
                        showAuthError();
                        hideUpdateIndicator();
                        return;
                    }
                    
                    // API制限エラーのチェック
                    if (data.includes('hour_api_limit') || data.includes('APIの利用上限')) {
                        console.warn('BASE API利用上限に達しました。更新を一時停止します。');
                        showApiLimitMessage();
                        hideUpdateIndicator();
                        return;
                    }
                    
                    // データ変更の検知（1回だけ実行）
                    var dataChanged = hasDataChanged(data);
                    
                    if (dataChanged) {
                        console.log('データに変更を検知しました。更新を実行します。');
                        
                        // 現在展開されている詳細の状態を保存
                        var expandedOrders = [];
                        var detailRows = document.querySelectorAll('[id^="detail-"]');
                        detailRows.forEach(function(row) {
                            if (row.style.display !== 'none') {
                                var orderId = row.id.replace('detail-', '');
                                expandedOrders.push(orderId);
                            }
                        });
                        
                        // スムーズな更新処理
                        smoothUpdateTable(data, expandedOrders);
                        
                        // 現在のデータを更新
                        currentOrderData = data;
                        
                        // 更新完了後にインジケーターを非表示
                        setTimeout(hideUpdateIndicator, 500);
                    } else {
                        console.log('データに変更はありません。更新をスキップします。');
                        hideUpdateIndicator(); // 更新インジケーターを非表示
                        showNoChangeIndicator(); // 変更なしインジケーターを表示
                        setTimeout(hideUpdateIndicator, 1500); // 1.5秒後に非表示
                    }
                })
                .catch(error => {
                    console.error('データ更新エラー:', error);
                    hideUpdateIndicator();
                    // エラー時は静かに失敗（画面を壊さない）
                });
        }
        
        // より詳細なデータ比較機能
        function hasDataChanged(newData) {
            if (!currentOrderData) {
                // 初回は必ず更新
                console.log('初回データ読み込みのため更新を実行します。');
                return true;
            }
            
            // JSONデータとして比較（より確実）
            try {
                var currentOrders = extractOrderData(currentOrderData);
                var newOrders = extractOrderData(newData);
                
                console.log('現在の注文数:', currentOrders.length, '新しい注文数:', newOrders.length);
                
                // 注文数が変わった場合
                if (currentOrders.length !== newOrders.length) {
                    console.log('注文数が変更されました。');
                    return true;
                }
                
                // 各注文の詳細を比較
                for (var i = 0; i < currentOrders.length; i++) {
                    var currentOrder = currentOrders[i];
                    var newOrder = newOrders[i];
                    
                    if (currentOrder.id !== newOrder.id) {
                        console.log('注文IDが変更されました:', currentOrder.id, '→', newOrder.id);
                        return true;
                    }
                    
                    if (currentOrder.status !== newOrder.status) {
                        console.log('ステータスが変更されました:', currentOrder.status, '→', newOrder.status);
                        return true;
                    }
                    
                    if (currentOrder.total !== newOrder.total) {
                        console.log('合計金額が変更されました:', currentOrder.total, '→', newOrder.total);
                        return true;
                    }
                }
                
                console.log('データに変更はありません。');
                return false;
            } catch (error) {
                console.warn('データ比較エラー:', error);
                // エラー時は安全のため更新を実行
                return true;
            }
        }
        
        // テーブルデータから注文情報を抽出
        function extractOrderData(htmlData) {
            var orders = [];
            var tempDiv = document.createElement('div');
            tempDiv.innerHTML = htmlData;
            
            var rows = tempDiv.querySelectorAll('tbody tr');
            rows.forEach(function(row) {
                // 詳細行はスキップ
                if (row.id && row.id.startsWith('detail-')) {
                    return;
                }
                
                var orderIdCell = row.querySelector('.order-id');
                var statusCell = row.querySelector('.order-status');
                var totalCell = row.querySelector('.total-amount'); // 変更: total-amount クラスを使用
                
                if (orderIdCell && statusCell && totalCell) {
                    orders.push({
                        id: orderIdCell.textContent.trim(),
                        status: statusCell.textContent.trim(),
                        total: totalCell.textContent.trim()
                    });
                }
            });
            
            return orders;
        }
        
        // 更新インジケーターの管理
        var updateIndicator = null;
        
        function showUpdateIndicator() {
            if (updateIndicator) return; // 既に表示されている場合は何もしない
            
            updateIndicator = document.createElement('div');
            updateIndicator.id = 'update-indicator';
            updateIndicator.style.cssText = 'position: fixed; top: 10px; right: 10px; background-color: #007bff; color: white; padding: 8px 12px; border-radius: 20px; font-size: 0.8em; z-index: 1000; box-shadow: 0 2px 8px rgba(0,0,0,0.2);';
            updateIndicator.innerHTML = '<i class="fas fa-sync-alt fa-spin"></i> 更新中...';
            
            document.body.appendChild(updateIndicator);
        }
        
        function showNoChangeIndicator() {
            if (updateIndicator) return; // 既に表示されている場合は何もしない
            
            updateIndicator = document.createElement('div');
            updateIndicator.id = 'update-indicator';
            updateIndicator.style.cssText = 'position: fixed; top: 10px; right: 10px; background-color: #28a745; color: white; padding: 8px 12px; border-radius: 20px; font-size: 0.8em; z-index: 1000; box-shadow: 0 2px 8px rgba(0,0,0,0.2);';
            updateIndicator.innerHTML = '<i class="fas fa-check"></i> データに変更なし';
            
            document.body.appendChild(updateIndicator);
        }
        
        function hideUpdateIndicator() {
            if (updateIndicator && updateIndicator.parentNode) {
                updateIndicator.parentNode.removeChild(updateIndicator);
                updateIndicator = null;
            }
        }
        
        // スムーズなテーブル更新（フェードアウト/イン効果付き）
        function smoothUpdateTable(newData, expandedOrders) {
            var container = document.getElementById('orders-table-container');
            if (!container) return;
            
            // フェードアウト
            container.style.opacity = '0.3';
            container.style.transition = 'opacity 0.3s ease';
            
            setTimeout(function() {
                // テーブル内容を更新
                container.innerHTML = newData;
                
                // 展開状態を復元
                expandedOrders.forEach(function(orderId) {
                    var detailRow = document.getElementById('detail-' + orderId);
                    if (detailRow) {
                        detailRow.style.display = '';
                        var toggleButton = document.getElementById('toggle-' + orderId);
                        if (toggleButton) {
                            toggleButton.innerHTML = '<i class="fas fa-chevron-up"></i> 全詳細';
                        }
                    }
                });
                
                // フェードイン
                container.style.opacity = '1';
                
                // 最終更新時刻を更新
                updateLastUpdateTime();
                updateOrderCount();
                
            }, 300);
        }
        
        // 最終更新時刻を更新
        function updateLastUpdateTime() {
            var now = new Date();
            var timeString = now.getHours().toString().padStart(2, '0') + ':' + 
                           now.getMinutes().toString().padStart(2, '0') + ':' + 
                           now.getSeconds().toString().padStart(2, '0');
            document.getElementById('last-update').textContent = timeString;
        }
        
        // 注文数を更新
        function updateOrderCount() {
            var rows = document.querySelectorAll('.order-table tbody tr');
            var orderRows = 0;
            rows.forEach(function(row) {
                if (!row.id || !row.id.startsWith('detail-')) {
                    orderRows++;
                }
            });
            document.getElementById('order-count').textContent = orderRows;
        }
        
        // 注文詳細の表示/非表示切り替え
        function toggleOrderDetail(orderId) {
            var detailRow = document.getElementById('detail-' + orderId);
            var toggleButton = document.getElementById('toggle-' + orderId);
            
            if (!detailRow || !toggleButton) return;
            
            if (detailRow.style.display === 'none' || detailRow.style.display === '') {
                // 詳細を表示
                detailRow.style.display = '';
                toggleButton.innerHTML = '<i class="fas fa-chevron-up"></i> 全詳細';
                
                // AJAXで詳細データを取得
                fetch('order_detail_ajax.php?order_id=' + encodeURIComponent(orderId))
                    .then(response => response.text())
                    .then(data => {
                        detailRow.innerHTML = '<td colspan="3" style="padding: 15px;">' + data + '</td>';
                    })
                    .catch(error => {
                        detailRow.innerHTML = '<td colspan="3" style="padding: 15px; color: #dc3545;">エラー: ' + error.message + '</td>';
                    });
            } else {
                // 詳細を非表示
                detailRow.style.display = 'none';
                toggleButton.innerHTML = '<i class="fas fa-chevron-down"></i> 全詳細';
            }
        }
        
        // ポップアップ表示関数
        function showPaymentInfo(orderId) {
            showPopup(orderId, 'payment', '決済・配送情報');
        }
        
        function showCustomerInfo(orderId) {
            showPopup(orderId, 'customer', 'お客様・配送先情報');
        }
        
        function showShippingInfo(orderId) {
            showPopup(orderId, 'shipping', '配送情報');
        }
        
        function showOtherInfo(orderId) {
            showPopup(orderId, 'other', 'その他の情報');
        }
        
        function showPopup(orderId, type, title) {
            // モーダル要素を作成
            var modal = document.createElement('div');
            modal.className = 'popup-modal';
            modal.id = 'popup-modal-' + orderId + '-' + type;
            
            modal.innerHTML = '<div class="popup-content">' +
                '<span class="popup-close" onclick="closePopup(\'' + orderId + '\', \'' + type + '\')">&times;</span>' +
                '<div id="popup-content-' + orderId + '-' + type + '">' +
                '<i class="fas fa-spinner fa-spin"></i> 読み込み中...' +
                '</div>' +
                '</div>';
            
            document.body.appendChild(modal);
            modal.style.display = 'block';
            
            // AJAXでデータを取得
            fetch('popup_info_ajax.php?order_id=' + encodeURIComponent(orderId) + '&type=' + encodeURIComponent(type))
                .then(response => response.text())
                .then(data => {
                    document.getElementById('popup-content-' + orderId + '-' + type).innerHTML = data;
                })
                .catch(error => {
                    document.getElementById('popup-content-' + orderId + '-' + type).innerHTML = 
                        '<div style="color: #dc3545;">エラー: ' + error.message + '</div>';
                });
        }
        
        function closePopup(orderId, type) {
            var modal = document.getElementById('popup-modal-' + orderId + '-' + type);
            if (modal) {
                modal.style.display = 'none';
                document.body.removeChild(modal);
            }
        }
        
        // モーダル外クリックで閉じる
        window.onclick = function(event) {
            if (event.target.classList.contains('popup-modal')) {
                event.target.style.display = 'none';
                document.body.removeChild(event.target);
            }
        };
        
        // 認証エラー表示
        function showAuthError() {
            var errorDiv = document.createElement('div');
            errorDiv.className = 'error-message';
            errorDiv.innerHTML = '<i class="fas fa-exclamation-triangle"></i><br>BASE API認証が必要です。<br><a href="scope_switcher.php" style="background-color: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; margin-top: 10px; display: inline-block;">BASE API認証を実行</a>';
            
            var container = document.getElementById('orders-container');
            if (container) {
                container.innerHTML = '';
                container.appendChild(errorDiv);
            }
        }
        
        // API制限メッセージ表示
        function showApiLimitMessage() {
            var limitDiv = document.createElement('div');
            limitDiv.className = 'error-message';
            limitDiv.innerHTML = '<i class="fas fa-exclamation-triangle"></i><br>BASE API利用上限に達しました。<br>しばらく時間をおいてから再度お試しください。';
            
            var container = document.getElementById('orders-container');
            if (container) {
                container.innerHTML = '';
                container.appendChild(limitDiv);
            }
        }
        
        // ページ読み込み時の初期化
        window.onload = function() {
            // 初期データを保存
            var initialData = document.getElementById('orders-table-container');
            if (initialData) {
                currentOrderData = initialData.innerHTML;
            }
            
            // 最終更新時刻と注文数を設定
            updateLastUpdateTime();
            updateOrderCount();
            
            // 30秒間隔で自動更新
            setInterval(refreshOrderData, 30000);
        };
    </script>
</body>
</html>