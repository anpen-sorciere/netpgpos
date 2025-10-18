<?php
// リアルタイム注文監視システム（第1段階）
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../config.php';
require_once '../functions.php';
require_once 'base_api_client.php';

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
        
        // デバッグ：実際のデータ構造を確認
        echo "<div style='background: #f8f9fa; padding: 15px; margin: 20px 0; border-radius: 8px;'>";
        echo "<h3>デバッグ情報：BASE APIレスポンス</h3>";
        echo "<pre>" . htmlspecialchars(print_r($orders_data, true)) . "</pre>";
        echo "</div>";
        
        $orders = $orders_data['orders'] ?? [];
        
        // データ構造を確認してからソート
        if (!empty($orders)) {
            $first_order = $orders[0];
            echo "<div style='background: #e9ecef; padding: 15px; margin: 20px 0; border-radius: 8px;'>";
            echo "<h3>デバッグ情報：最初の注文データ構造</h3>";
            echo "<pre>" . htmlspecialchars(print_r($first_order, true)) . "</pre>";
            echo "</div>";
            
            // 利用可能なキーを確認
            $available_keys = array_keys($first_order);
            echo "<div style='background: #d4edda; padding: 15px; margin: 20px 0; border-radius: 8px;'>";
            echo "<h3>利用可能なキー</h3>";
            echo "<p>" . implode(', ', $available_keys) . "</p>";
            echo "</div>";
        }
        
        // 最新の注文が上に来るようにソート（利用可能なキーでソート）
        if (!empty($orders)) {
            $sort_key = 'order_id';
            if (!isset($orders[0]['order_id'])) {
                // order_idが存在しない場合、他のキーを試す
                $possible_keys = ['id', 'order_no', 'order_number', 'created_at', 'date'];
                foreach ($possible_keys as $key) {
                    if (isset($orders[0][$key])) {
                        $sort_key = $key;
                        break;
                    }
                }
            }
            
            usort($orders, function($a, $b) use ($sort_key) {
                if (is_numeric($a[$sort_key]) && is_numeric($b[$sort_key])) {
                    return $b[$sort_key] - $a[$sort_key];
                } else {
                    return strcmp($b[$sort_key], $a[$sort_key]);
                }
            });
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
            background-color: #d4edda;
            color: #155724;
        }
        
        .status-shipped {
            background-color: #d1ecf1;
            color: #0c5460;
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
                                <th>金額</th>
                                <th>詳細</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($orders as $order): ?>
                                <tr>
                                    <td class="order-id">
                                        #<?= htmlspecialchars($order['order_id'] ?? $order['id'] ?? $order['order_no'] ?? 'N/A') ?>
                                    </td>
                                    <td class="order-date">
                                        <?php
                                        $date_key = 'order_date';
                                        if (!isset($order[$date_key])) {
                                            $possible_date_keys = ['created_at', 'date', 'order_created_at'];
                                            foreach ($possible_date_keys as $key) {
                                                if (isset($order[$key])) {
                                                    $date_key = $key;
                                                    break;
                                                }
                                            }
                                        }
                                        echo isset($order[$date_key]) ? date('Y/m/d H:i', strtotime($order[$date_key])) : 'N/A';
                                        ?>
                                    </td>
                                    <td><?= htmlspecialchars($order['customer_name'] ?? $order['customer'] ?? $order['buyer_name'] ?? '未設定') ?></td>
                                    <td>
                                        <?php
                                        $status = $order['status'] ?? $order['order_status'] ?? 'unknown';
                                        ?>
                                        <span class="order-status status-<?= htmlspecialchars($status) ?>">
                                            <?= htmlspecialchars($status) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php
                                        $total_key = 'total';
                                        if (!isset($order[$total_key])) {
                                            $possible_total_keys = ['amount', 'price', 'order_total', 'total_amount'];
                                            foreach ($possible_total_keys as $key) {
                                                if (isset($order[$key])) {
                                                    $total_key = $key;
                                                    break;
                                                }
                                            }
                                        }
                                        echo isset($order[$total_key]) ? '¥' . number_format($order[$total_key]) : 'N/A';
                                        ?>
                                    </td>
                                    <td>
                                        <?php
                                        $order_id = $order['order_id'] ?? $order['id'] ?? $order['order_no'] ?? 'N/A';
                                        ?>
                                        <button class="btn btn-sm btn-secondary" onclick="showOrderDetail('<?= htmlspecialchars($order_id) ?>')">
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

    <script>
        // 5秒間隔で自動更新
        setInterval(function() {
            location.reload();
        }, 5000);
        
        // 注文詳細表示（第2段階で実装予定）
        function showOrderDetail(orderId) {
            alert('注文詳細機能は第2段階で実装予定です。\n注文ID: ' + orderId);
        }
    </script>
</body>
</html>
