<?php
// リアルタイム注文監視システム（第1段階）
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';
require_once __DIR__ . '/base_practical_auto_manager.php';

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
    $practical_manager = new BasePracticalAutoManager();
    
    // ページング設定
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $limit = 50;
    $offset = ($page - 1) * $limit;
    
    // 自動でデータを取得・合成
    try {
        $combined_data = $practical_manager->getCombinedOrderData(1000); // 全件取得してページング処理
        $orders_data = $combined_data['merged_orders'];
        
        // データ構造を確認して適切に注文データを取得
        if (isset($orders_data['orders'])) {
            // 従来の構造: merged_orders.orders
            $all_orders = $orders_data['orders'];
        } else {
            // 新しい構造: merged_orders自体が注文配列
            $all_orders = $orders_data;
        }
        
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
        
        // $error_message = ''; // 空文字列を設定しない
        
        // デバッグ: データ構造を確認
        echo '<div style="background-color: #fff3cd; padding: 10px; margin: 10px 0; border-radius: 4px; font-size: 0.8em;">';
        echo '<strong>DEBUG order_monitor.php:</strong> ';
        echo '全注文数: ' . $total_orders . ' | ';
        echo '現在ページ: ' . $page . '/' . $total_pages . ' | ';
        echo '表示件数: ' . count($orders) . ' | ';
        echo 'オフセット: ' . $offset;
        echo '</div>';
        
        // デバッグ: 最初の3件の注文のdispatch_statusを確認
        if (count($orders) > 0) {
            echo '<div style="background-color: #e8f4fd; padding: 10px; margin: 10px 0; border-radius: 4px; font-size: 0.8em;">';
            echo '<strong>ステータスデバッグ（最初の3件）:</strong><br>';
            for ($i = 0; $i < min(3, count($orders)); $i++) {
                $order = $orders[$i];
                $order_id = $order['unique_key'] ?? 'N/A';
                $dispatch_status = $order['dispatch_status'] ?? 'N/A';
                $ordered = $order['ordered'] ?? 'N/A';
                echo '注文' . ($i + 1) . ': ' . $order_id . ' | dispatch_status: ' . $dispatch_status . ' | ordered: ' . $ordered . '<br>';
            }
            echo '</div>';
        }
        
        // デバッグ: 認証状況を確認
        echo '<div style="background-color: #e8f4fd; padding: 10px; margin: 10px 0; border-radius: 4px; font-size: 0.8em;">';
        echo '<strong>認証デバッグ情報:</strong><br>';
        echo 'セッションアクセストークン存在: ' . (isset($_SESSION['base_access_token']) ? 'Yes' : 'No') . '<br>';
        if (isset($_SESSION['base_access_token'])) {
            echo 'トークン長: ' . strlen($_SESSION['base_access_token']) . '文字<br>';
            echo 'トークン先頭: ' . substr($_SESSION['base_access_token'], 0, 20) . '...<br>';
        }
        
        // BasePracticalAutoManagerの認証状況を確認
        $scopes = ['read_orders', 'read_items', 'write_orders'];
        foreach ($scopes as $scope) {
            $is_valid = $practical_manager->isTokenValid($scope);
            echo "スコープ {$scope}: " . ($is_valid ? '有効' : '無効') . '<br>';
        }
        echo '</div>';
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
        $orders = [];
        
        // デバッグ: エラー情報を表示
        echo '<div style="background-color: #f8d7da; padding: 10px; margin: 10px 0; border-radius: 4px; font-size: 0.8em;">';
        echo '<strong>ERROR:</strong> ' . htmlspecialchars($e->getMessage()) . ' | ';
        echo 'ファイル: ' . htmlspecialchars($e->getFile()) . ' | ';
        echo '行: ' . htmlspecialchars($e->getLine());
        echo '</div>';
    }
    
    // デバッグ: 処理継続確認
    echo '<div style="background-color: #d1ecf1; padding: 10px; margin: 10px 0; border-radius: 4px; font-size: 0.8em;">';
    echo '<strong>処理継続:</strong> データ取得完了 | ';
    echo 'orders数: ' . count($orders) . ' | ';
    echo 'error_message: ' . (isset($error_message) ? htmlspecialchars($error_message) : 'なし');
    echo '</div>';
    
    // 最新の注文が上に来るようにソート（注文日時順）
    if (!empty($orders)) {
        // デバッグ: ソート処理開始
        echo '<div style="background-color: #d4edda; padding: 10px; margin: 10px 0; border-radius: 4px; font-size: 0.8em;">';
        echo '<strong>ソート処理開始:</strong> orders数: ' . count($orders) . ' | ';
        echo '最初の注文キー: ' . (isset($orders[0]) ? htmlspecialchars(json_encode(array_keys($orders[0]), JSON_UNESCAPED_UNICODE)) : 'なし');
        echo '</div>';
        
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
            
            // デバッグ: ソート処理完了
            echo '<div style="background-color: #d4edda; padding: 10px; margin: 10px 0; border-radius: 4px; font-size: 0.8em;">';
            echo '<strong>ソート処理完了:</strong> orders数: ' . count($orders) . ' | ';
            echo 'ソートキー: ' . $sort_key . ' | ';
            echo '最初の注文日時: ' . (isset($orders[0][$sort_key]) ? $orders[0][$sort_key] : 'なし');
            echo '</div>';
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
        
        .item-details {
            margin-top: 10px;
        }
        
        .item-detail-header {
            display: flex;
            gap: 10px;
            margin-bottom: 5px;
            padding: 5px;
            background-color: #e9ecef;
            border-radius: 4px;
            font-size: 0.9em;
            font-weight: bold;
            color: #495057;
        }
        
        .item-detail-row {
            display: flex;
            gap: 10px;
            margin-bottom: 5px;
            padding: 5px;
            background-color: #f8f9fa;
            border-radius: 4px;
            font-size: 0.9em;
        }
        
        .item-title {
            font-weight: bold;
            color: #2c3e50;
            flex: 2;
        }
        
        .item-quantity {
            color: #6c757d;
            flex: 0.5;
        }
        
        .item-nickname {
            color: #e67e22;
            font-weight: bold;
            flex: 1;
        }
        
        .item-cast {
            color: #8e44ad;
            font-weight: bold;
            flex: 1;
        }
        
        .item-details-placeholder {
            font-style: italic;
            color: #6c757d;
            font-size: 0.8em;
        }
        
        .pagination-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin: 20px 0;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #dee2e6;
        }
        
        .pagination-stats {
            font-size: 0.9em;
            color: #6c757d;
            font-weight: 500;
        }
        
        .pagination-nav {
            display: flex;
            gap: 5px;
            align-items: center;
        }
        
        .pagination-nav a,
        .pagination-nav span {
            padding: 6px 12px;
            border-radius: 4px;
            text-decoration: none;
            font-size: 0.9em;
            transition: all 0.2s ease;
        }
        
        .pagination-nav a:hover {
            background-color: #e9ecef;
            transform: translateY(-1px);
        }
        
        .filter-section {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }
        
        .filter-title {
            font-size: 1.1em;
            font-weight: bold;
            color: #495057;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .filter-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            margin-bottom: 15px;
        }
        
        .filter-group {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .filter-label {
            font-weight: 500;
            color: #6c757d;
            margin-right: 5px;
        }
        
        .filter-btn {
            padding: 6px 12px;
            border: 1px solid #dee2e6;
            background-color: white;
            color: #495057;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.9em;
            transition: all 0.2s ease;
        }
        
        .filter-btn:hover {
            background-color: #e9ecef;
            border-color: #adb5bd;
        }
        
        .filter-btn.active {
            background-color: #007bff;
            color: white;
            border-color: #007bff;
        }
        
        .filter-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 10px;
            border-top: 1px solid #dee2e6;
            font-size: 0.9em;
            color: #6c757d;
        }
        
        .filter-actions {
            display: flex;
            gap: 8px;
            align-items: center;
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
                <button class="btn btn-secondary" onclick="autoAuth()">
                    <i class="fas fa-key"></i> 自動認証
                </button>
                <a href="scope_switcher.php" class="btn btn-outline-secondary">
                    <i class="fas fa-cog"></i> 手動設定
                </a>
            </div>
        </div>
        
        <div id="orders-container">
            <?php 
            // デバッグ: 表示条件を確認
            echo '<div style="background-color: #e7f3ff; padding: 10px; margin: 10px 0; border-radius: 4px; font-size: 0.8em;">';
            echo '<strong>表示条件デバッグ:</strong> ';
            echo 'error_message設定: ' . (isset($error_message) ? 'Yes (' . htmlspecialchars($error_message) . ')' : 'No') . ' | ';
            echo 'orders空チェック: ' . (empty($orders) ? 'Yes (空)' : 'No (' . count($orders) . '件)') . ' | ';
            echo '表示パターン: ' . (isset($error_message) ? 'エラー表示' : (empty($orders) ? 'データなし表示' : 'テーブル表示'));
            echo '</div>';
            ?>
            
            <?php if (isset($error_message)): ?>
                <div class="error-message">
                    <i class="fas fa-exclamation-triangle"></i><br>
                    <?= htmlspecialchars($error_message) ?><br>
                    <button onclick="autoAuth()" style="background-color: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; margin-top: 10px; display: inline-block;">
                        <i class="fas fa-key"></i> 自動認証を実行
                    </button>
                    <a href="scope_switcher.php" style="background-color: #6c757d; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; margin-top: 10px; margin-left: 10px; display: inline-block;">
                        <i class="fas fa-cog"></i> 手動設定
                    </a>
                </div>
            <?php elseif (empty($orders)): ?>
                <div class="no-orders">
                    <i class="fas fa-inbox"></i><br>
                    注文データがありません
                </div>
            <?php else: ?>
                <div class="filter-section">
                    <div class="filter-title">
                        <i class="fas fa-filter"></i> 表示フィルター
                    </div>
                    <div class="filter-buttons">
                        <div class="filter-group">
                            <span class="filter-label">ステータス:</span>
                            <button class="filter-btn active" data-status="all">全て</button>
                            <button class="filter-btn" data-status="unpaid">入金待ち</button>
                            <button class="filter-btn" data-status="unshippable">対応開始前</button>
                            <button class="filter-btn" data-status="ordered">未対応</button>
                            <button class="filter-btn" data-status="shipping">対応中</button>
                            <button class="filter-btn" data-status="dispatched">対応済</button>
                            <button class="filter-btn" data-status="cancelled">キャンセル</button>
                        </div>
                        <div class="filter-group">
                            <span class="filter-label">顧客情報:</span>
                            <button class="filter-btn" data-filter="customer" onclick="toggleCustomerFilter()">購入者名、電話番号など</button>
                        </div>
                    </div>
                    <div class="filter-info">
                        <span id="filter-status">全ての注文を表示中</span>
                        <div class="filter-actions">
                            <button class="btn btn-sm btn-primary" onclick="applyFilters()">
                                <i class="fas fa-search"></i> 検索
                            </button>
                            <button class="btn btn-sm btn-outline-secondary" onclick="clearAllFilters()">フィルタークリア</button>
                        </div>
                    </div>
                </div>
                
                <div class="pagination-info">
            <div class="pagination-stats">
                全 <?= $total_orders ?> 件中 <?= $offset + 1 ?>-<?= min($offset + $limit, $total_orders) ?> 件を表示 (<?= $page ?>/<?= $total_pages ?> ページ)
            </div>
            <div class="pagination-nav">
                <?php if ($page > 1): ?>
                    <a href="?page=1" class="btn btn-sm btn-outline-primary">最初</a>
                    <a href="?page=<?= $page - 1 ?>" class="btn btn-sm btn-outline-primary">前へ</a>
                <?php endif; ?>
                
                <?php
                // ページ番号の表示範囲を計算
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
                
                for ($i = $start_page; $i <= $end_page; $i++):
                ?>
                    <?php if ($i == $page): ?>
                        <span class="btn btn-sm btn-primary"><?= $i ?></span>
                    <?php else: ?>
                        <a href="?page=<?= $i ?>" class="btn btn-sm btn-outline-primary"><?= $i ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                
                <?php if ($page < $total_pages): ?>
                    <a href="?page=<?= $page + 1 ?>" class="btn btn-sm btn-outline-primary">次へ</a>
                    <a href="?page=<?= $total_pages ?>" class="btn btn-sm btn-outline-primary">最後</a>
                <?php endif; ?>
            </div>
        </div>
        
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
                            
                            // 商品ごとの情報はAJAXで動的に取得するため、ここでは何も表示しない
                            
                            // デバッグ情報（開発時のみ）
                            if (isset($_GET['debug']) && $_GET['debug'] === 'nickname') {
                                echo '<div style="background: #f0f0f0; padding: 10px; margin: 5px 0; border: 1px solid #ccc;">';
                                echo '<strong>デバッグ情報 (注文ID: ' . $order_id . '):</strong><br>';
                                echo '抽出されたニックネーム: ' . ($nickname_display ?: 'なし') . '<br>';
                                echo '検索キーワード: お客様名, ニックネーム, nickname, お名前, 名前, name, 呼び名, 愛称<br>';
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
                                                if (stripos($option_name, 'お客様名') !== false) $matches[] = 'お客様名';
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
                            ?>
                            <tr>
                                <!-- 注文ヘッダー列 -->
                                <td class="order-header">
                                    <div class="order-header-info">
                                        <div class="order-id">#<?= $order_id ?></div>
                                        <div class="order-date"><?= htmlspecialchars($date_value) ?></div>
                                        <div class="order-status <?= $status_class ?>"><?= htmlspecialchars($status) ?></div>
                                        <div class="customer-name"><?= $customer_name ?></div>
                                        <div class="total-amount"><?= $total_amount ?></div>
                                        
                                        <!-- 商品ごとの情報（AJAXで動的に追加） -->
                                        <div class="item-details" data-order-id="<?= $order_id ?>">
                                            <span class="item-details-placeholder">商品情報読み込み中...</span>
                                        </div>
                                        
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
                
                <!-- 下部ページングナビゲーション -->
                <div class="pagination-info">
                    <div class="pagination-stats">
                        全 <?= $total_orders ?> 件中 <?= $offset + 1 ?>-<?= min($offset + $limit, $total_orders) ?> 件を表示 (<?= $page ?>/<?= $total_pages ?> ページ)
                    </div>
                    <div class="pagination-nav">
                        <?php if ($page > 1): ?>
                            <a href="?page=1" class="btn btn-sm btn-outline-primary">最初</a>
                            <a href="?page=<?= $page - 1 ?>" class="btn btn-sm btn-outline-primary">前へ</a>
                        <?php endif; ?>
                        
                        <?php
                        // ページ番号の表示範囲を計算
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $page + 2);
                        
                        for ($i = $start_page; $i <= $end_page; $i++):
                        ?>
                            <?php if ($i == $page): ?>
                                <span class="btn btn-sm btn-primary"><?= $i ?></span>
                            <?php else: ?>
                                <a href="?page=<?= $i ?>" class="btn btn-sm btn-outline-primary"><?= $i ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>
                        
                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?= $page + 1 ?>" class="btn btn-sm btn-outline-primary">次へ</a>
                            <a href="?page=<?= $total_pages ?>" class="btn btn-sm btn-outline-primary">最後</a>
                        <?php endif; ?>
                    </div>
                </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // 現在のデータを保存する変数
        var currentOrderData = null;
        
        // フィルター状態を管理する変数
        var activeFilters = {
            status: ['all'],
            customer: false
        };
        
        // 自動認証機能
        function autoAuth() {
            // 認証中インジケーターを表示
            showAuthIndicator();
            
            // 必要なスコープを確認
            const returnUrl = encodeURIComponent(window.location.href);
            fetch('auto_auth.php?scopes=read_orders,read_items&return_url=' + returnUrl)
                .then(response => response.json())
                .then(data => {
                    hideAuthIndicator();
                    
                    if (data.success && data.needs_auth) {
                        // 認証が必要な場合、認証URLにリダイレクト
                        if (data.auth_url) {
                            // 認証完了後に戻るURLを設定
                            const returnUrl = encodeURIComponent(window.location.href);
                            const authUrl = data.auth_url + (data.auth_url.includes('?') ? '&' : '?') + 'return_url=' + returnUrl;
                            
                            // 認証ページに移動
                            window.location.href = authUrl;
                        } else {
                            alert('認証URLの生成に失敗しました。手動設定をお試しください。');
                        }
                    } else if (data.success && !data.needs_auth) {
                        // 認証が不要な場合
                        alert('認証は不要です。ページを更新してください。');
                        window.location.reload();
                    } else {
                        // エラーの場合
                        alert('認証チェックでエラーが発生しました: ' + (data.error || '不明なエラー'));
                    }
                })
                .catch(error => {
                    hideAuthIndicator();
                    console.error('認証チェックエラー:', error);
                    alert('認証チェックでエラーが発生しました。手動設定をお試しください。');
                });
        }
        
        // 認証中インジケーター
        var authIndicator = null;
        
        function showAuthIndicator() {
            if (authIndicator) return;
            
            authIndicator = document.createElement('div');
            authIndicator.id = 'auth-indicator';
            authIndicator.style.cssText = 'position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background-color: rgba(0,0,0,0.8); color: white; padding: 20px; border-radius: 8px; z-index: 10000; text-align: center;';
            authIndicator.innerHTML = '<i class="fas fa-spinner fa-spin"></i><br>認証を確認中...';
            
            document.body.appendChild(authIndicator);
        }
        
        function hideAuthIndicator() {
            if (authIndicator && authIndicator.parentNode) {
                authIndicator.parentNode.removeChild(authIndicator);
                authIndicator = null;
            }
        }
        
        // 現在のページ番号を取得する関数
        function getCurrentPage() {
            var urlParams = new URLSearchParams(window.location.search);
            var page = urlParams.get('page');
            return page ? parseInt(page) : 1;
        }
        
        // ステータスフィルターの切り替え
        function toggleFilter(status) {
            console.log('toggleFilter呼び出し: ' + status + ', 現在のactiveFilters: ' + JSON.stringify(activeFilters));
            var button = document.querySelector('[data-status="' + status + '"]');
            
            if (status === 'all') {
                // 「全て」が選択された場合、他のステータスを全て非アクティブに
                document.querySelectorAll('.filter-btn[data-status]').forEach(function(btn) {
                    btn.classList.remove('active');
                });
                button.classList.add('active');
                activeFilters.status = ['all'];
            } else {
                // 「全て」を非アクティブに
                document.querySelector('[data-status="all"]').classList.remove('active');
                // activeFiltersから「all」を削除
                activeFilters.status = activeFilters.status.filter(function(s) { return s !== 'all'; });
                
                if (button.classList.contains('active')) {
                    // 既にアクティブな場合は非アクティブに
                    button.classList.remove('active');
                    activeFilters.status = activeFilters.status.filter(function(s) { return s !== status; });
                    
                    // 全てのステータスが非アクティブになった場合は「全て」をアクティブに
                    if (activeFilters.status.length === 0) {
                        document.querySelector('[data-status="all"]').classList.add('active');
                        activeFilters.status = ['all'];
                    }
                } else {
                    // 非アクティブな場合はアクティブに
                    button.classList.add('active');
                    activeFilters.status.push(status);
                }
            }
            
            console.log('toggleFilter完了後のactiveFilters: ' + JSON.stringify(activeFilters));
            updateFilterStatus();
        }
        
        // 顧客情報フィルターの切り替え
        function toggleCustomerFilter() {
            var button = document.querySelector('[data-filter="customer"]');
            activeFilters.customer = !activeFilters.customer;
            
            if (activeFilters.customer) {
                button.classList.add('active');
            } else {
                button.classList.remove('active');
            }
            
            updateFilterStatus();
        }
        
        // 全てのフィルターをクリア
        function clearAllFilters() {
            // ステータスフィルターをリセット
            document.querySelectorAll('.filter-btn[data-status]').forEach(function(btn) {
                btn.classList.remove('active');
            });
            document.querySelector('[data-status="all"]').classList.add('active');
            activeFilters.status = ['all'];
            
            // 顧客情報フィルターをリセット
            document.querySelector('[data-filter="customer"]').classList.remove('active');
            activeFilters.customer = false;
            
            updateFilterStatus();
        }
        
        // フィルター状態の表示を更新
        function updateFilterStatus() {
            var statusText = '';
            
            if (activeFilters.status.includes('all')) {
                statusText = '全ての注文を表示中';
            } else {
                var statusNames = {
                    'unpaid': '入金待ち',
                    'unshippable': '対応開始前',
                    'ordered': '未対応',
                    'shipping': '対応中',
                    'dispatched': '対応済',
                    'cancelled': 'キャンセル'
                };
                
                var selectedStatuses = activeFilters.status.map(function(status) {
                    return statusNames[status] || status;
                });
                
                statusText = selectedStatuses.join('、') + 'の注文を表示中';
            }
            
            if (activeFilters.customer) {
                statusText += '（顧客情報フィルター適用中）';
            }
            
            document.getElementById('filter-status').textContent = statusText;
        }
        
        // フィルターを適用して表示を更新
        function applyFilters() {
            console.log('フィルター適用開始:', activeFilters);
            
            // 注文行のみを対象にする（詳細行は除外）
            var rows = document.querySelectorAll('.order-table tbody tr:not([id^="detail-"])');
            console.log('対象行数:', rows.length);
            var visibleCount = 0;
            
            rows.forEach(function(row, index) {
                var shouldShow = true;
                
                // ステータスフィルターの適用
                if (!activeFilters.status.includes('all')) {
                    var statusElement = row.querySelector('.order-status');
                    if (statusElement) {
                        var statusClass = statusElement.className;
                        var statusText = statusElement.textContent.trim();
                        var statusMatch = false;
                        
                        console.log('行' + index + ' ステータス:', statusText, 'クラス:', statusClass);
                        
                        activeFilters.status.forEach(function(filterStatus) {
                            // CSSクラスでマッチをチェック
                            if (statusClass.includes('status-' + filterStatus)) {
                                statusMatch = true;
                                console.log('CSSクラスでマッチ:', filterStatus);
                            }
                            
                            // ステータステキストでマッチをチェック（フォールバック）
                            var statusTextMap = {
                                'unpaid': '入金待ち',
                                'unshippable': '対応開始前',
                                'ordered': '未対応',
                                'shipping': '対応中',
                                'dispatched': '対応済',
                                'cancelled': 'キャンセル'
                            };
                            
                            if (statusText === statusTextMap[filterStatus]) {
                                statusMatch = true;
                                console.log('ステータステキストでマッチ:', filterStatus);
                            }
                        });
                        
                        if (!statusMatch) {
                            shouldShow = false;
                            console.log('行' + index + ' 非表示');
                        }
                    } else {
                        console.log('行' + index + ' ステータス要素が見つからない');
                    }
                }
                
                // 顧客情報フィルターの適用（将来的な拡張用）
                if (activeFilters.customer && shouldShow) {
                    // 顧客情報が存在するかチェック（現在は常にtrue）
                    // 将来的に顧客情報の有無でフィルタリング可能
                }
                
                if (shouldShow) {
                    row.style.display = '';
                    visibleCount++;
                    
                    // 対応する詳細行も表示
                    var detailRow = document.getElementById('detail-' + row.id);
                    if (detailRow) {
                        detailRow.style.display = '';
                    }
                } else {
                    row.style.display = 'none';
                    
                    // 対応する詳細行も非表示
                    var detailRow = document.getElementById('detail-' + row.id);
                    if (detailRow) {
                        detailRow.style.display = 'none';
                    }
                }
            });
            
            console.log('表示行数:', visibleCount);
            
            // 表示件数を更新
            var orderCountElement = document.getElementById('order-count');
            if (orderCountElement) {
                orderCountElement.textContent = visibleCount;
            }
        }
        
        // AJAXでデータのみを更新（データ変更検知付き）
        function refreshOrderData() {
            showUpdateIndicator(); // 更新開始を表示
            
            // 現在のページ番号を取得
            var currentPage = getCurrentPage();
            var url = 'order_data_ajax.php';
            if (currentPage > 1) {
                url += '?page=' + currentPage;
            }
            
            fetch(url)
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
                        
                        // フィルターを再適用
                        applyFilters();
                        
                        // 現在のデータを更新
                        currentOrderData = data;
                        
                        // 更新完了後にインジケーターを非表示
                        setTimeout(hideUpdateIndicator, 500);
                    } else {
                        console.log('データに変更はありません。更新をスキップします。');
                        hideUpdateIndicator(); // 更新インジケーターを非表示
                        showNoChangeIndicator(); // 変更なしインジケーターを表示
                        
                        // データに変更がなくてもフィルターは再適用
                        applyFilters();
                        
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
                
                // 商品ごとの情報を再読み込み
                loadItemDetails();
                
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
        
        // 注文ステータス更新機能
        function updateOrderStatus(uniqueKey, status, message, videoUrl) {
            if (!confirm("注文ステータスを更新しますか？")) {
                return;
            }
            
            const data = {
                unique_key: uniqueKey,
                status: status,
                message: message || "",
                video_url: videoUrl || ""
            };
            
            fetch("update_order_status.php", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json"
                },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    alert("注文ステータスを更新しました");
                    // ページを再読み込みして最新データを表示
                    location.reload();
                } else {
                    alert("エラー: " + result.error);
                }
            })
            .catch(error => {
                alert("エラーが発生しました: " + error.message);
            });
        }
        
        function showShippingModal(uniqueKey) {
            const message = prompt("発送通知メッセージを入力してください（省略可）:");
            if (message === null) return; // キャンセル
            
            const videoUrl = prompt("動画URLを入力してください（省略可）:");
            if (videoUrl === null) return; // キャンセル
            
            updateOrderStatus(uniqueKey, "dispatched", message, videoUrl);
        }
        
        // 認証エラー表示
        function showAuthError() {
            var errorDiv = document.createElement('div');
            errorDiv.className = 'error-message';
            errorDiv.innerHTML = '<i class="fas fa-exclamation-triangle"></i><br>BASE API認証が必要です。<br><a href="test_practical_auto.php" style="background-color: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; margin-top: 10px; display: inline-block;">BASE API認証を実行</a>';
            
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
        
        
        
        // 商品ごとの情報を表示する関数
        function loadItemDetails() {
            var itemDetailContainers = document.querySelectorAll('.item-details');
            
            itemDetailContainers.forEach(function(container) {
                var orderId = container.getAttribute('data-order-id');
                if (orderId) {
                    fetch('get_order_items.php?order_id=' + encodeURIComponent(orderId))
                        .then(response => response.json())
                        .then(data => {
                            if (data.success && data.items) {
                                // プレースホルダーを削除
                                var placeholder = container.querySelector('.item-details-placeholder');
                                if (placeholder) {
                                    placeholder.remove();
                                }
                                
                                // テーブルヘッダーを追加
                                var headerDiv = document.createElement('div');
                                headerDiv.className = 'item-detail-header';
                                headerDiv.innerHTML = 
                                    '<div class="item-title">商品名</div>' +
                                    '<div class="item-quantity">数量</div>' +
                                    '<div class="item-nickname">お客様名</div>' +
                                    '<div class="item-cast">キャスト名</div>';
                                container.appendChild(headerDiv);
                                
                                // 商品ごとの情報を表示
                                data.items.forEach(function(item) {
                                    var itemDiv = document.createElement('div');
                                    itemDiv.className = 'item-detail-row';
                                    
                                    // ニックネームとキャスト名を抽出
                                    var nickname = '';
                                    var castName = '';
                                    
                                    if (item.options && item.options.length > 0) {
                                        item.options.forEach(function(option) {
                                            if (option.name === 'お客様名') {
                                                nickname = option.value;
                                            } else if (option.name === 'キャスト名') {
                                                castName = option.value;
                                            }
                                        });
                                    }
                                    
                                    itemDiv.innerHTML = 
                                        '<div class="item-title">' + item.title + '</div>' +
                                        '<div class="item-quantity">' + item.amount + '</div>' +
                                        '<div class="item-nickname">' + (nickname || 'なし') + '</div>' +
                                        '<div class="item-cast">' + (castName || 'なし') + '</div>';
                                    
                                    container.appendChild(itemDiv);
                                });
                            } else {
                                // エラー時はエラーメッセージを表示
                                var placeholder = container.querySelector('.item-details-placeholder');
                                if (placeholder) {
                                    placeholder.textContent = 'エラー: ' + (data.error || '不明なエラー');
                                    placeholder.style.color = '#dc3545';
                                }
                            }
                        })
                        .catch(error => {
                            console.error('商品情報取得エラー:', error);
                            var placeholder = container.querySelector('.item-details-placeholder');
                            if (placeholder) {
                                placeholder.textContent = 'エラー: ' + error.message;
                                placeholder.style.color = '#dc3545';
                            }
                        });
                }
            });
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
            
            // 商品ごとの情報を読み込み
            loadItemDetails();
            
            // フィルター状態の表示を初期化（適用はしない）
            updateFilterStatus();
            
            // フィルターボタンのイベントリスナーを設定
            document.querySelectorAll('.filter-btn[data-status]').forEach(function(button) {
                button.addEventListener('click', function() {
                    var status = this.getAttribute('data-status');
                    toggleFilter(status);
                });
            });
            
            // 30秒間隔で自動更新
            setInterval(refreshOrderData, 30000);
        };
    </script>
</body>
</html>