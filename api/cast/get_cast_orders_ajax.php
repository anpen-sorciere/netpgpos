<?php
/**
 * 特定のキャストの未対応注文リストを返すAjax (admin_cast_progress.php用)
 */
require_once __DIR__ . '/../../../common/config.php';
require_once __DIR__ . '/../../../common/dbconnect.php';

$cast_id = $_GET['cast_id'] ?? 0;
if (!$cast_id) {
    echo '<div class="alert alert-danger">キャストIDが指定されていません。</div>';
    exit;
}

try {
    $pdo = new PDO(
        "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
        $user,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // キャストダッシュボードと同じSQLロジック
    $sql = "
        SELECT 
            o.base_order_id,
            o.order_date,
            o.customer_name,
            o.status,
            o.is_surprise,
            o.surprise_date,
            oi.product_name,
            oi.item_surprise_date,
            oi.price
        FROM base_orders o
        INNER JOIN base_order_items oi ON o.base_order_id = oi.base_order_id
        WHERE oi.cast_id = :cast_id
        AND o.status IN ('ordered', 'unpaid')
        ORDER BY o.order_date ASC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':cast_id' => $cast_id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $orders = [];
    $today = date('Y-m-d');

    foreach ($rows as $row) {
        // 未来のサプライズは非表示（キャストダッシュボードと同じ条件）
        $sDate = $row['item_surprise_date'];
        if ($sDate && $sDate > $today) {
            continue; 
        }
        $orders[] = $row;
    }

    if (empty($orders)) {
        echo '<div class="alert alert-success"><i class="fas fa-check-circle"></i> 現在、未対応の注文はありません。</div>';
        exit;
    }

    // テーブル表示
    ?>
    <div class="table-responsive">
        <table class="table table-bordered table-striped">
            <thead class="table-light">
                <tr>
                    <th>注文日時</th>
                    <th>注文ID</th>
                    <th>お客様名</th>
                    <th>商品</th>
                    <th>ステータス</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orders as $order): ?>
                    <?php 
                        // 遅延の警告色
                        $is_delayed = (strtotime($order['order_date']) < strtotime('-3 days'));
                        $date_class = $is_delayed ? 'text-danger fw-bold' : '';
                    ?>
                    <tr>
                        <td class="<?= $date_class ?>">
                            <?= date('Y/m/d H:i', strtotime($order['order_date'])) ?>
                            <?php if ($is_delayed): ?>
                                <br><small><i class="fas fa-exclamation-triangle"></i> 遅延</small>
                            <?php endif; ?>
                        </td>
                        <td><?= $order['base_order_id'] ?></td>
                        <td><?= htmlspecialchars($order['customer_name']) ?></td>
                        <td>
                            <?= htmlspecialchars($order['product_name']) ?>
                            <?php if ($order['item_surprise_date']): ?>
                                <br><span class="badge bg-warning text-dark">サプライズ: <?= $order['item_surprise_date'] ?></span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($order['status'] == 'unpaid'): ?>
                                <span class="badge bg-warning text-dark">入金待ち</span>
                            <?php else: ?>
                                <span class="badge bg-primary">未対応</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php

} catch (PDOException $e) {
    echo '<div class="alert alert-danger">データ取得エラー: ' . htmlspecialchars($e->getMessage()) . '</div>';
}
?>
