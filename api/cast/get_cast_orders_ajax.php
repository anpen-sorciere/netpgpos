<?php
/**
 * 特定のキャストの未対応注文リストを返すAjax (admin_cast_progress.php用)
 * 承認フロー対応版：cast_handled=1 も表示し、承認ボタンを出す
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

    // 未対応または未承認のアイテムを取得
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
            oi.price,
            oi.cast_handled,
            oi.cast_handled_at
        FROM base_orders o
        INNER JOIN base_order_items oi ON o.base_order_id = oi.base_order_id
        WHERE oi.cast_id = :cast_id
        AND o.status IN ('ordered', 'unpaid')
        -- AND (oi.cast_handled = 0 OR oi.cast_handled = 1) -- 明示的に書くならこうだが、現状全てorderedなのでOK
        ORDER BY oi.cast_handled ASC, o.order_date ASC -- 未対応を上に
    ";
    
    // 略称取得のためにJOINを追加したSQLに書き換え
    $sql = "
        SELECT 
            o.base_order_id,
            o.order_date,
            o.customer_name,
            o.status,
            o.is_surprise,
            o.surprise_date,
            oi.product_name,
            oi.customer_name_from_option,
            oi.item_surprise_date,
            oi.price,
            oi.cast_handled,
            oi.cast_handled_at,
            t.template_abbreviation,
            t.template_name
        FROM base_orders o
        INNER JOIN base_order_items oi ON o.base_order_id = oi.base_order_id
        LEFT JOIN reply_message_templates t ON oi.cast_handled_template_id = t.id
        WHERE oi.cast_id = :cast_id
        AND o.status IN ('ordered', 'unpaid', '対応中')
        AND (oi.cast_handled IS NULL OR oi.cast_handled IN (0, 1)) -- 2(承認済)は非表示
        ORDER BY oi.cast_handled ASC, o.order_date ASC
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':cast_id' => $cast_id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $orders = [];
    $today = date('Y-m-d');

    foreach ($rows as $row) {
        $sDate = $row['item_surprise_date'];
        if ($sDate && $sDate > $today) {
            continue; 
        }
        $orders[] = $row;
    }

    if (empty($orders)) {
        echo '<div class="alert alert-success"><i class="fas fa-check-circle"></i> 現在、対応が必要な注文はありません。</div>';
        exit;
    }

    ?>
    <div class="table-responsive">
        <table class="table table-bordered table-striped align-middle">
            <thead class="table-light">
                <tr>
                    <th>状態</th>
                    <th>注文日時</th>
                    <th>注文ID</th>
                    <th>お客様名</th>
                    <th>商品</th>
                    <th>アクション</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($orders as $order): ?>
                    <?php 
                        $is_delayed = (strtotime($order['order_date']) < strtotime('-3 days'));
                        $date_class = $is_delayed ? 'text-danger fw-bold' : '';
                        $is_handled = !empty($order['cast_handled']);
                    ?>
                    <tr class="<?= $is_handled ? 'table-warning' : '' ?>">
                        <td class="text-center">
                            <?php if ($is_handled): ?>
                                <span class="badge bg-warning text-dark"><i class="fas fa-clock"></i> 承認待ち</span>
                                <br><small><?= date('m/d H:i', strtotime($order['cast_handled_at'])) ?></small>
                                <?php if (!empty($order['template_abbreviation'])): ?>
                                    <br><span class="badge bg-info text-dark mt-1"><?= htmlspecialchars($order['template_abbreviation']) ?></span>
                                <?php endif; ?>
                            <?php else: ?>
                                <span class="badge bg-danger">キャスト未対応</span>
                            <?php endif; ?>
                        </td>
                        <td class="<?= $date_class ?>">
                            <?= date('Y/m/d H:i', strtotime($order['order_date'])) ?>
                        </td>
                        <td><?= $order['base_order_id'] ?></td>
                        <td>
                            <strong><?= htmlspecialchars($order['customer_name'] ?: '未設定') ?></strong>
                            <?php if (!empty($order['customer_name_from_option'])): ?>
                                <br><small class="text-muted">(<?= htmlspecialchars($order['customer_name_from_option']) ?>)</small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?= htmlspecialchars($order['product_name']) ?>
                            <?php if ($order['item_surprise_date']): ?>
                                <br><span class="badge bg-warning text-dark">サプライズ: <?= $order['item_surprise_date'] ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <?php if ($is_handled): ?>
                                <button class="btn btn-primary btn-sm btn-approve mb-1" 
                                        data-order-id="<?= $order['base_order_id'] ?>"
                                        data-item-id="<?= $order['id'] ?>"
                                        data-cast-id="<?= $cast_id ?>">
                                    <i class="fas fa-check"></i> 承認
                                </button>
                                <button class="btn btn-outline-danger btn-sm btn-reject" 
                                        data-order-id="<?= $order['base_order_id'] ?>"
                                        data-item-id="<?= $order['id'] ?>"
                                        data-product-name="<?= htmlspecialchars($order['product_name']) ?>">
                                    <i class="fas fa-undo"></i> 差戻し
                                </button>
                            <?php else: ?>
                                <span class="text-muted small">対応待ち</span>
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
