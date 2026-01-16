<?php
session_start();
if (!isset($_SESSION['cast_id'])) {
    header('Location: cast_login.php');
    exit;
}

require_once __DIR__ . '/../../../common/config.php';

$cast_name = $_SESSION['cast_name'];
$orders = [];

try {
    $pdo = new PDO(
        "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
        $user,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $cast_id = $_SESSION['cast_id'];
    
    // 注文とその商品を取得（商品ごとに行を分ける）
    $sql = "
        SELECT 
            o.base_order_id,
            o.order_date,
            o.customer_name,
            o.total_amount,
            o.status,
            o.payment_method,
            o.is_surprise,
            o.surprise_date,
            oi.id as item_id,
            oi.product_name,
            oi.quantity,
            oi.price,
            oi.customer_name_from_option,
            oi.item_surprise_date
        FROM base_orders o
        INNER JOIN base_order_items oi ON o.base_order_id = oi.base_order_id
        WHERE oi.cast_id = :cast_id
        ORDER BY o.order_date DESC, oi.id ASC
        LIMIT 500
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':cast_id' => $cast_id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $today = date('Y-m-d');
    
    // 注文ごとにグループ化
    $orders_temp = [];
    foreach ($rows as $row) {
        // サプライズ日付フィルター
        $sDate = $row['item_surprise_date'];
        if ($sDate && $sDate > $today) {
            continue; // 未来のサプライズは非表示
        }
        
        $order_id = $row['base_order_id'];
        
        if (!isset($orders_temp[$order_id])) {
            $orders_temp[$order_id] = [
                'base_order_id' => $row['base_order_id'],
                'order_date' => $row['order_date'],
                'customer_name' => $row['customer_name'],
                'total_amount' => $row['total_amount'],
                'status' => $row['status'],
                'payment_method' => $row['payment_method'],
                'is_surprise' => $row['is_surprise'],
                'surprise_date' => $row['surprise_date'],
                'items' => []
            ];
        }
        
        $orders_temp[$order_id]['items'][] = [
            'product_name' => $row['product_name'],
            'quantity' => $row['quantity'],
            'price' => $row['price'],
            'customer_name_from_option' => $row['customer_name_from_option'],
            'item_surprise_date' => $row['item_surprise_date']
        ];
    }
    
    $orders = array_values($orders_temp);
    
} catch (PDOException $e) {
    $error = "データ取得エラー: " . $e->getMessage();
}

// ステータス表示用の関数
function getStatusBadge($status) {
    $badges = [
        'ordered' => '<span class="badge bg-primary">未対応</span>',
        'unpaid' => '<span class="badge bg-warning text-dark">入金待ち</span>',
        'paid' => '<span class="badge bg-info">入金済み</span>',
        'shipping' => '<span class="badge bg-success">発送済み</span>',
        'cancel' => '<span class="badge bg-secondary">キャンセル</span>',
        'arrived' => '<span class="badge bg-success">配送完了</span>',
    ];
    return $badges[$status] ?? '<span class="badge bg-secondary">' . htmlspecialchars($status) . '</span>';
}

// 支払い方法の表示
function getPaymentMethod($method) {
    $methods = [
        'cvs' => 'コンビニ決済',
        'bt' => '銀行振込',
        'credit_card' => 'クレジットカード',
        'atobarai' => '後払い',
        'cod' => '代金引換',
    ];
    return $methods[$method] ?? htmlspecialchars($method);
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($cast_name) ?> - Cast Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { 
            background: linear-gradient(135deg, #fce4ec 0%, #f8bbd0 100%);
            padding-bottom: 50px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .header { 
            background: white; 
            padding: 20px; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.1); 
            margin-bottom: 20px;
            border-bottom: 3px solid #e91e63;
        }
        .welcome { 
            color: #d81b60; 
            font-weight: bold; 
            font-size: 1.3em;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .stats-bar {
            background: white;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            display: flex;
            justify-content: space-around;
            text-align: center;
        }
        .stat-item {
            flex: 1;
        }
        .stat-number {
            font-size: 2em;
            font-weight: bold;
            color: #e91e63;
        }
        .stat-label {
            font-size: 0.85em;
            color: #666;
        }
        .order-card { 
            background: white; 
            border-radius: 12px; 
            padding: 20px; 
            margin-bottom: 15px; 
            border-left: 5px solid #e91e63; 
            box-shadow: 0 3px 8px rgba(0,0,0,0.08);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .order-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.15);
        }
        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px dashed #f0f0f0;
        }
        .order-id {
            font-family: monospace;
            font-size: 0.9em;
            color: #888;
        }
        .order-date { 
            font-size: 0.95em; 
            color: #666;
        }
        .order-amount {
            font-size: 1.5em;
            font-weight: bold;
            color: #e91e63;
        }
        .items-table {
            width: 100%;
            margin: 15px 0;
        }
        .items-table th {
            background: #f8f9fa;
            padding: 10px;
            text-align: left;
            font-size: 0.9em;
            color: #666;
            border-bottom: 2px solid #e0e0e0;
        }
        .items-table td {
            padding: 12px 10px;
            border-bottom: 1px solid #f0f0f0;
            vertical-align: middle;
        }
        .items-table tr:last-child td {
            border-bottom: none;
        }
        .items-table tr:hover {
            background: #fafafa;
        }
        .product-name {
            font-weight: 500;
            color: #333;
        }
        .product-price {
            color: #e91e63;
            font-weight: 600;
        }
        .customer-info {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px;
            background: #fff3e0;
            border-radius: 8px;
            margin: 10px 0;
        }
        .customer-name { 
            color: #d81b60; 
            font-weight: 600;
            font-size: 1.1em;
        }
        .surprise-badge { 
            background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%);
            color: white; 
            padding: 4px 10px; 
            border-radius: 20px; 
            font-size: 0.85em; 
            font-weight: bold;
            box-shadow: 0 2px 5px rgba(255,152,0,0.3);
        }
        .payment-info {
            font-size: 0.9em;
            color: #666;
            margin-top: 10px;
        }
        .btn-logout { 
            background: #f50057;
            color: white;
            border: none;
            padding: 8px 20px;
            border-radius: 20px;
        }
        .btn-logout:hover {
            background: #c51162;
            color: white;
        }
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 15px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.05);
        }
        .empty-state i {
            font-size: 4em;
            color: #e0e0e0;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="d-flex justify-content-between align-items-center">
            <div class="welcome">
                <i class="fas fa-user-circle"></i>
                <?= htmlspecialchars($cast_name) ?> さん
            </div>
            <a href="cast_logout.php" class="btn btn-logout">
                <i class="fas fa-sign-out-alt"></i> ログアウト
            </a>
        </div>
    </div>

    <div class="container">
        <?php if (!empty($orders)): ?>
            <!-- 統計情報 -->
            <?php
                $total_amount = array_sum(array_column($orders, 'total_amount'));
                $total_orders = count($orders);
                $total_items = 0;
                foreach ($orders as $order) {
                    $total_items += count($order['items']);
                }
            ?>
            <div class="stats-bar">
                <div class="stat-item">
                    <div class="stat-number"><?= $total_orders ?></div>
                    <div class="stat-label"><i class="fas fa-shopping-cart"></i> 注文数</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?= $total_items ?></div>
                    <div class="stat-label"><i class="fas fa-box"></i> 商品数</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number">¥<?= number_format($total_amount) ?></div>
                    <div class="stat-label"><i class="fas fa-yen-sign"></i> 合計金額</div>
                </div>
            </div>

            <h5 class="mb-3 text-secondary">
                <i class="fas fa-history"></i> 最近の注文 (<?= count($orders) ?>件)
            </h5>

            <?php foreach ($orders as $order): ?>
                <div class="order-card">
                    <div class="order-header">
                        <div>
                            <div class="order-date">
                                <i class="fas fa-calendar-alt"></i> 
                                <?= date('Y年m月d日 H:i', strtotime($order['order_date'])) ?>
                            </div>
                            <div class="order-id">
                                <i class="fas fa-hashtag"></i> <?= htmlspecialchars($order['base_order_id']) ?>
                            </div>
                        </div>
                        <div class="text-end">
                            <div class="order-amount">
                                ¥<?= number_format($order['total_amount']) ?>
                            </div>
                            <div>
                                <?= getStatusBadge($order['status']) ?>
                            </div>
                        </div>
                    </div>

                    <!-- お客様情報 -->
                    <?php
                        // 商品から顧客名を取得（最初の非NULL値）
                        $customer_display = $order['customer_name'];
                        foreach ($order['items'] as $item) {
                            if (!empty($item['customer_name_from_option'])) {
                                $customer_display = $item['customer_name_from_option'];
                                break;
                            }
                        }
                        
                        // サプライズ確認
                        $has_surprise = false;
                        $surprise_date = null;
                        foreach ($order['items'] as $item) {
                            if ($item['item_surprise_date'] && $item['item_surprise_date'] <= $today) {
                                $has_surprise = true;
                                $surprise_date = $item['item_surprise_date'];
                                break;
                            }
                        }
                    ?>
                    <div class="customer-info">
                        <i class="fas fa-user text-primary"></i>
                        <span class="customer-name">
                            <?= htmlspecialchars($customer_display ?: '名前なし') ?>
                        </span>
                        様
                        <?php if ($has_surprise): ?>
                            <span class="surprise-badge ms-2">
                                <i class="fas fa-gift"></i> サプライズ (<?= $surprise_date ?>)
                            </span>
                        <?php endif; ?>
                    </div>

                    <!-- 商品テーブル（1行1商品） -->
                    <table class="items-table">
                        <thead>
                            <tr>
                                <th style="width: 60%">商品名</th>
                                <th style="width: 15%; text-align: center">数量</th>
                                <th style="width: 25%; text-align: right">単価</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($order['items'] as $item): ?>
                                <tr>
                                    <td class="product-name">
                                        <?= htmlspecialchars($item['product_name']) ?>
                                        <?php if ($item['item_surprise_date'] && $item['item_surprise_date'] <= $today): ?>
                                            <i class="fas fa-gift text-warning ms-1" title="サプライズ商品"></i>
                                        <?php endif; ?>
                                    </td>
                                    <td style="text-align: center">
                                        <span class="badge bg-secondary rounded-pill">×<?= $item['quantity'] ?></span>
                                    </td>
                                    <td class="product-price" style="text-align: right">
                                        ¥<?= number_format($item['price']) ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <!-- 支払い情報 -->
                    <div class="payment-info">
                        <i class="fas fa-credit-card"></i> 
                        <?= getPaymentMethod($order['payment_method']) ?>
                    </div>
                </div>
            <?php endforeach; ?>

        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-inbox"></i>
                <h4>まだ注文履歴がありません</h4>
                <p class="text-muted">
                    注文が入ると、こちらに表示されます。<br>
                    <small>※モニター画面が開かれたときにデータが自動同期されます。</small>
                </p>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
