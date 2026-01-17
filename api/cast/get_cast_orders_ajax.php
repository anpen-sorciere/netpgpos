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
        AND o.status IN ('ordered', 'unpaid')
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
                            <?= htmlspecialchars($order['product_name']) ?>
                            <?php if ($order['item_surprise_date']): ?>
                                <br><span class="badge bg-warning text-dark">サプライズ: <?= $order['item_surprise_date'] ?></span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <?php if ($is_handled): ?>
                                <button class="btn btn-primary btn-sm btn-approve" 
                                        data-order-id="<?= $order['base_order_id'] ?>"
                                        data-cast-id="<?= $cast_id ?>">
                                    <i class="fas fa-check"></i> 承認・反映
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

    <script>
    // 承認ボタンイベント（動的生成されるためここでバインド）
    document.querySelectorAll('.btn-approve').forEach(btn => {
        btn.addEventListener('click', async function() {
            // if (!confirm('このキャスト対応を承認し、BASEへ反映しますか？\n（お客様へ発送メールが送信されます）')) return; // プレビュー確認へ変更

            const orderId = this.dataset.orderId;
            const castId = this.dataset.castId;
            const originalText = this.innerHTML;
            this.disabled = true;
            this.innerHTML = '<span class="spinner-border spinner-border-sm"></span> 処理中';

            try {
                // Step 1: プレビュー取得
                const response = await fetch('../../api/ajax/admin_approve_order.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ order_id: orderId, cast_id: castId, preview: true })
                });
                
                const result = await response.json();
                
                if (result.success && result.preview) {
                    // プレビュー成功、モーダル表示
                    const confirmModalEl = document.getElementById('approveConfirmModal');
                    const confirmModal = new bootstrap.Modal(confirmModalEl);
                    
                    document.getElementById('previewMessage').value = result.message;
                    
                    // 送信確定ボタンの設定
                    const confirmBtn = document.getElementById('btnConfirmSend');
                    
                    // 以前のリスナーを削除するためにクローン作成
                    const newConfirmBtn = confirmBtn.cloneNode(true);
                    confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);
                    
                    newConfirmBtn.addEventListener('click', async function() {
                        // 本送信処理
                        this.disabled = true;
                        this.innerHTML = '<span class="spinner-border spinner-border-sm"></span> 送信中...';
                        
                        try {
                            const sendResponse = await fetch('../../api/ajax/admin_approve_order.php', {
                                method: 'POST',
                                headers: {'Content-Type': 'application/json'},
                                body: JSON.stringify({ order_id: orderId, cast_id: castId }) // previewなし
                            });
                            
                            const sendResult = await sendResponse.json();
                            
                            if (sendResult.success) {
                                alert('承認・送信完了しました！');
                                confirmModal.hide();
                                location.reload();
                            } else {
                                throw new Error(sendResult.error || '送信エラー');
                            }
                        } catch (sendError) {
                            alert('送信エラー: ' + sendError.message);
                            this.disabled = false;
                            this.innerHTML = '<i class="fas fa-paper-plane"></i> 送信確定';
                        }
                    });
                    
                    // ボタンの状態を戻して表示
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                    
                    confirmModal.show();
                    
                } else {
                    throw new Error(result.error || 'プレビュー取得エラー');
                }
            } catch (e) {
                alert('エラー: ' + e.message);
                this.innerHTML = originalText;
                this.disabled = false;
            }
        });
    });
    </script>
    <?php

} catch (PDOException $e) {
    echo '<div class="alert alert-danger">データ取得エラー: ' . htmlspecialchars($e->getMessage()) . '</div>';
}
?>
