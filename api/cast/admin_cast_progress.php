<?php
/**
 * 管理者向け全キャスト対応状況監視画面
 * 各キャストの未対応件数や放置期間を一覧表示
 */
session_start();

// セッション認証チェック
if (!isset($_SESSION['utype'])) {
    // セッションが切れている場合はメインメニューへリダイレクト
    ?>
    <!DOCTYPE html>
    <html><head><meta charset="utf-8"></head>
    <body>
        <script>
            alert('セッションが切れています。メインメニューに移動します。');
            window.location.href = '../../index.php';
        </script>
    </body>
    </html>
    <?php
    exit;
}

require_once __DIR__ . '/../../../common/config.php';
require_once __DIR__ . '/../../../common/dbconnect.php';

// 今日の日付（放置判定用）
$today = date('Y-m-d');
$warning_threshold_days = 3; // 3日以上で警告

// キャストと未対応注文情報の取得
try {
    $pdo = new PDO(
        "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
        $user,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // 未対応（ordered, unpaid）の注文を持つキャストごとの集計
    // 未来のサプライズは除く必要あり？ -> 「未対応」という仕事としては残っているので、
    // 管理者視点では「未来の仕事」として件数には入れるが、警告対象からは外すなどの調整も考えられるが、
    // 一旦シンプルに「表示される仕事」ベースで集計する。
    // キャストダッシュボードと同じロジック（未来サプライズ除外）で集計すると正確。
    
    // 集計はPHP側でやったほうがサプライズフィルタ等のロジックを合わせやすいので
    // 一旦全キャスト取得 -> 各キャストのオーダー取得 -> フィルタ & カウント という流れ（人数少なければOK）
    // あるいはSQLで頑張るか。
    // まずはSQLでざっくり取得し、PHPで微調整する。

    // 全キャスト取得
    $stmt = $pdo->query("SELECT * FROM cast_mst WHERE drop_flg = 0 ORDER BY cast_id ASC");
    $casts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // キャストごとのステータス配列を作成
    foreach ($casts as &$cast) {
        $cast_id = $cast['cast_id'];
        
        // そのキャストの未対応注文を取得
        $sql = "
            SELECT 
                o.order_date,
                oi.item_surprise_date,
                oi.cast_handled
            FROM base_orders o
            INNER JOIN base_order_items oi ON o.base_order_id = oi.base_order_id
            WHERE oi.cast_id = :cast_id
            AND o.status IN ('ordered', 'unpaid', '対応中')
        ";
        $stmt_orders = $pdo->prepare($sql);
        $stmt_orders->execute([':cast_id' => $cast_id]);
        $orders = $stmt_orders->fetchAll(PDO::FETCH_ASSOC);

        $unfinished_count = 0;
        $approval_pending_count = 0; // 承認待ち件数
        $oldest_unfinished_date = null;

        foreach ($orders as $order) {
            $sDate = $order['item_surprise_date'];
            if ($sDate && $sDate > $today) {
                continue;
            }

            // 承認待ちかどうか（cast_handled = 1 のみ。2は承認済みなので除外）
            if ($order['cast_handled'] == 1) {
                $approval_pending_count++;
            } elseif (empty($order['cast_handled']) || $order['cast_handled'] == 0) {
                // キャスト未対応のものだけを「未対応件数」としてカウントするか、
                // あるいは「全担当分」とするか？
                // ユーザー要望は「承認待ちがある人を優先」なので、
                // unfinished_count はあくまで「タスク残」として、承認待ちは別カウントとするのが分かりやすい。
                $unfinished_count++;
            }
            
            // 最古の日付更新 (承認待ちのものは日付チェックから外す？ -> いや、未対応のものだけチェックすべき)
            if (empty($order['cast_handled'])) {
                if ($oldest_unfinished_date === null || $order['order_date'] < $oldest_unfinished_date) {
                    $oldest_unfinished_date = $order['order_date'];
                }
            }
        }

        $cast['unfinished_count'] = $unfinished_count;
        $cast['approval_pending_count'] = $approval_pending_count;
        $cast['oldest_unfinished_date'] = $oldest_unfinished_date;
        
        // 放置日数計算
        $cast['elapsed_days'] = 0;
        if ($oldest_unfinished_date) {
            $cast['elapsed_days'] = (strtotime($today) - strtotime($oldest_unfinished_date)) / (60 * 60 * 24);
        }
    }
    unset($cast); // 参照解除

    // 並び替え: 承認待ちが多い順 > 放置日数が長い順
    usort($casts, function($a, $b) {
        // まず承認待ちがある人を最優先
        if ($a['approval_pending_count'] != $b['approval_pending_count']) {
            return $b['approval_pending_count'] <=> $a['approval_pending_count'];
        }
        // 次に放置日数
        if ($a['elapsed_days'] != $b['elapsed_days']) {
            return $b['elapsed_days'] <=> $a['elapsed_days'];
        }
        // 最後に未対応件数
        return $b['unfinished_count'] <=> $a['unfinished_count'];
    });

    // 定型文一覧取得
    $stmt_templates = $pdo->query("SELECT * FROM reply_message_templates ORDER BY display_order ASC");
    $templates = $stmt_templates->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error = "DB Error: " . $e->getMessage();
}

?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>キャスト進捗状況モニター</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; font-family: 'Segoe UI', sans-serif; }
        .monitor-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            overflow: hidden;
        }
        .header {
            background-color: #343a40;
            color: white;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .warning-row { background-color: #fff3cd; }
        .danger-row { background-color: #f8d7da; }
        .approval-row { background-color: #d1e7dd; } /* 承認待ちがある行 */
        .table th { vertical-align: middle; background-color: #e9ecef; }
        .table td { vertical-align: middle; }
        
        .badge-count { font-size: 1.1em; padding: 8px 12px; border-radius: 20px; }
        .alert-icon { font-size: 1.2em; color: #dc3545; animation: pulse 2s infinite; }
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }
    </style>
</head>
<body>
    <div class="container-fluid py-4">
        <div class="monitor-card">
            <div class="header">
                <h4 class="m-0"><i class="fas fa-tasks"></i> キャスト対応状況モニター</h4>
                <div>
                    <a href="../../index.php" class="btn btn-outline-light btn-sm"><i class="fas fa-home"></i> メインメニュー</a>
                    <a href="admin_cast_progress.php" class="btn btn-primary btn-sm ms-2"><i class="fas fa-sync-alt"></i> 更新</a>
                </div>
            </div>
            
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>キャスト名</th>
                            <th class="text-center">承認待ち</th>
                            <th class="text-center">キャスト対応待ち</th>
                            <th class="text-center">最も古い未対応</th>
                            <th class="text-center">放置日数</th>
                            <th class="text-center">最終ログイン</th>
                            <th class="text-center">アクション</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($casts as $cast): ?>
                            <?php 
                                // 行の色分け
                                $rowClass = '';
                                if ($cast['approval_pending_count'] > 0) {
                                    $rowClass = 'approval-row'; // 承認待ち最優先
                                } elseif ($cast['elapsed_days'] >= 5) {
                                    $rowClass = 'danger-row';
                                } elseif ($cast['elapsed_days'] >= 3) {
                                    $rowClass = 'warning-row';
                                } elseif ($cast['unfinished_count'] == 0) {
                                    $rowClass = 'text-muted';
                                }
                                
                                // 未対応件数バッジの色
                                $badgeClass = 'bg-secondary';
                                if ($cast['unfinished_count'] > 0) {
                                    $badgeClass = ($cast['elapsed_days'] >= 3) ? 'bg-danger' : 'bg-primary';
                                }
                            ?>
                            <tr class="<?= $rowClass ?>">
                                <td>
                                    <strong><?= htmlspecialchars($cast['cast_name']) ?></strong>
                                    <?php if ($cast['elapsed_days'] >= 3): ?>
                                        <i class="fas fa-exclamation-triangle text-danger ms-2" title="対応遅れあり"></i>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($cast['approval_pending_count'] > 0): ?>
                                        <button class="btn btn-success btn-sm position-relative">
                                            承認待ち
                                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                                <?= $cast['approval_pending_count'] ?>
                                                <span class="visually-hidden">unread messages</span>
                                            </span>
                                        </button>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($cast['unfinished_count'] > 0): ?>
                                        <span class="badge <?= $badgeClass ?> badge-count">
                                            <?= $cast['unfinished_count'] ?> 件
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($cast['oldest_unfinished_date']): ?>
                                        <?= htmlspecialchars($cast['oldest_unfinished_date']) ?>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($cast['elapsed_days'] > 0): ?>
                                        <strong><?= floor($cast['elapsed_days']) ?> 日</strong>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($cast['last_login_at']): ?>
                                        <?= date('m/d H:i', strtotime($cast['last_login_at'])) ?>
                                        <?php if (strtotime($cast['last_login_at']) < strtotime('-3 days')): ?>
                                            <span class="text-danger small"><br>(3日以上前)</span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted">未ログイン</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <button class="btn btn-outline-dark btn-sm view-details" 
                                            data-cast-id="<?= $cast['cast_id'] ?>" 
                                            data-cast-name="<?= htmlspecialchars($cast['cast_name']) ?>">
                                        <i class="fas fa-list-alt"></i> 詳細
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- 詳細モーダル -->
    <div class="modal fade" id="detailModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="detailModalLabel">詳細確認</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="modalBody">
                    <div class="text-center p-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 承認確認モーダル -->
    <div class="modal fade" id="approveConfirmModal" tabindex="-1" data-bs-backdrop="static">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="fas fa-check-circle"></i> 承認・送信内容の確認</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="alert alert-warning">
                        <i class="fas fa-exclamation-circle"></i> 以下の内容でお客様へメールが送信され、BASEのステータスが「発送済み」になります。<br>
                        問題なければ「送信確定」を押してください。
                    </p>

                    <div class="mb-3">
                        <label class="form-label fw-bold">使用する定型文:</label>
                        <select class="form-select" id="templateSelect">
                            <option value="">(選択なし - キャスト選択を維持)</option>
                            <?php foreach ($templates as $tmpl): ?>
                                <option value="<?= $tmpl['id'] ?>">
                                    <?= htmlspecialchars($tmpl['template_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text text-muted small">変更するとメッセージが再構築されます。</div>
                    </div>

                    <!-- 配送情報（任意） -->
                    <div class="card bg-light mb-3">
                        <div class="card-body py-2">
                            <h6 class="card-title text-muted border-bottom pb-1 mb-2">配送情報（必要な場合のみ入力）</h6>
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <label class="form-label small">配送業者</label>
                                    <span id="deliveryTypeDisplay" class="badge bg-secondary text-white ms-1 fw-normal d-none"></span>
                                    <select class="form-select form-select-sm" id="deliveryCompany">
                                        <option value="">(指定なし)</option>
                                        <option value="1">ヤマト運輸</option>
                                        <option value="2">佐川急便</option>
                                        <option value="3">日本郵便</option>
                                        <option value="4">西濃運輸</option>
                                        <option value="5">福山通運</option>
                                        <option value="6">その他</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small">追跡番号</label>
                                    <input type="text" class="form-control form-control-sm" id="trackingNumber" placeholder="1234-5678-9012">
                                </div>
                            </div>
                            <div class="form-text small mt-1">選択・入力するとメッセージ内の変数 <code>{delivery_company}</code> <code>{tracking_number}</code> が置換されます。</div>
                        </div>
                    </div>

                    <!-- 特典クーポン入力（動的表示） -->
                    <div id="couponInputContainer" class="card bg-warning bg-opacity-10 mb-3 d-none">
                        <div class="card-body py-2">
                             <h6 class="card-title text-dark border-bottom border-warning pb-1 mb-2">
                                <i class="fas fa-ticket-alt text-warning"></i> 特典クーポンコード <span class="badge bg-danger">必須</span>
                            </h6>
                            <input type="text" class="form-control" id="couponCode" placeholder="例: A1B2C3D4E5" maxlength="20">
                            <div class="form-text text-danger small"><strong>※ 特典申請があるため、発行したクーポンコードを入力してください。</strong></div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-bold">送信メッセージ（編集可能）:</label>
                        <textarea class="form-control" id="previewMessage" rows="10" style="background-color: #fff; font-family: monospace;"></textarea>
                    </div>

                    <!-- デバッグ情報 -->
                    <details class="mt-3 border-top pt-2">
                        <summary class="text-muted small" style="cursor: pointer;">デバッグ情報 (配送方法調査用)</summary>
                        <pre id="debugInfo" class="bg-light p-2 small border mt-1 text-break" style="max-height: 200px; overflow-y: auto; white-space: pre-wrap;"></pre>
                    </details>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">キャンセル</button>
                    <button type="button" class="btn btn-success" id="btnConfirmSend">
                        <i class="fas fa-paper-plane"></i> 送信確定
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const detailModal = new bootstrap.Modal(document.getElementById('detailModal'));
            const modalTitle = document.getElementById('detailModalLabel');
            const modalBody = document.getElementById('modalBody');
            
            // 確認モーダル
            const confirmModalEl = document.getElementById('approveConfirmModal');
            const confirmModal = new bootstrap.Modal(confirmModalEl);
            const btnConfirmSend = document.getElementById('btnConfirmSend');
            const templateSelect = document.getElementById('templateSelect');
            const previewMessage = document.getElementById('previewMessage');
            let currentApproveConfig = null; // 送信データ保持用

            const deliveryCompany = document.getElementById('deliveryCompany');
            const trackingNumber = document.getElementById('trackingNumber');
            
            // クーポン関連
            const couponInputContainer = document.getElementById('couponInputContainer');
            const couponCodeInput = document.getElementById('couponCode');

            // プレビュー更新関数
            async function updatePreview() {
                if (!currentApproveConfig) return;

                const templateId = templateSelect.value;
                const deliveryId = deliveryCompany.value;
                const trackingNum = trackingNumber.value;
                const couponCode = couponCodeInput.value.trim(); // 追加

                previewMessage.disabled = true;
                previewMessage.value = "再読み込み中...";
                
                try {
                    const response = await fetch('../../api/ajax/admin_approve_order.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify({ 
                            order_id: currentApproveConfig.orderId, 
                            cast_id: currentApproveConfig.castId, 
                            shop_id: currentApproveConfig.shopId,
                            template_id: templateId, 
                            delivery_company_id: deliveryId,
                            tracking_number: trackingNum,
                            coupon_code: couponCode, // 追加
                            preview: true 
                        })
                    });
                    const result = await response.json();
                    if (result.success) {
                        previewMessage.value = result.message;
                    } else {
                        previewMessage.value = "エラー: " + result.error;
                    }
                } catch (e) {
                    previewMessage.value = "通信エラー";
                } finally {
                    previewMessage.disabled = false;
                }
            }

            // イベントリスナー設定
            templateSelect.addEventListener('change', updatePreview);
            deliveryCompany.addEventListener('change', updatePreview);
            trackingNumber.addEventListener('change', updatePreview); // 変更確定時
            couponCodeInput.addEventListener('change', updatePreview); // クーポン変更時

            // イベントデリゲーション：お客様名クリック
            document.body.addEventListener('click', async function(e) {
                if (e.target && e.target.closest('.customer-info-trigger')) {
                    const el = e.target.closest('.customer-info-trigger');
                    const orderId = el.dataset.orderId;
                    const shopId = el.dataset.shopId;
                    
                    // 簡易的なローディング表示（ポインターをwaitに）
                    document.body.style.cursor = 'wait';

                    try {
                        const res = await fetch(`../../api/ajax/get_order_customer_info.php?order_id=${orderId}&shop_id=${shopId}`);
                        const json = await res.json();

                        if (json.success) {
                            const d = json.data;
                            const info = `
                                <strong>氏名:</strong> ${d.last_name} ${d.first_name}<br>
                                <strong>電話:</strong> ${d.tel}<br>
                                <strong>Email:</strong> ${d.mail_address}<br>
                                <hr class="my-2">
                                <strong>郵便番号:</strong> ${d.zip_code}<br>
                                <strong>住所:</strong> ${d.prefecture} ${d.address} ${d.address2}<br>
                                <hr class="my-2">
                                <strong>備考:</strong> ${d.remark || 'なし'}
                            `;
                            
                            // 既存のモーダルに関係なく、情報を表示するための簡易モーダルを動的生成して表示
                            // またはアラートよりリッチなBootstrap Modalを呼び出す
                            showCustomerInfoModal(info);
                        } else {
                            alert('情報取得エラー: ' + json.error);
                        }
                    } catch (err) {
                        alert('通信エラーが発生しました');
                    } finally {
                        document.body.style.cursor = 'default';
                    }
                }
            });

            function showCustomerInfoModal(htmlContent) {
                let modalEl = document.getElementById('customerInfoModal');
                if (!modalEl) {
                    const div = document.createElement('div');
                    div.innerHTML = `
                        <div class="modal fade" id="customerInfoModal" tabindex="-1" style="z-index: 1060;"> <!-- z-index higher than detail modal -->
                            <div class="modal-dialog modal-sm modal-dialog-centered">
                                <div class="modal-content">
                                    <div class="modal-header py-2">
                                        <h6 class="modal-title">お客様情報</h6>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body text-break bg-light" style="font-size:0.9rem;">
                                        ${htmlContent}
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                    document.body.appendChild(div);
                    modalEl = document.getElementById('customerInfoModal');
                } else {
                    modalEl.querySelector('.modal-body').innerHTML = htmlContent;
                }
                const modal = new bootstrap.Modal(modalEl);
                modal.show();
            }

            // イベントデリゲーション：動的に生成される「承認」ボタンのクリックを監視
            document.body.addEventListener('click', async function(e) {
                if (e.target && (e.target.classList.contains('btn-approve') || e.target.closest('.btn-approve'))) {
                    const btn = e.target.classList.contains('btn-approve') ? e.target : e.target.closest('.btn-approve');
                    
                    const orderId = btn.dataset.orderId;
                    const castId = btn.dataset.castId;
                    const shopId = btn.dataset.shopId; // 追加
                    const itemId = btn.dataset.itemId; // 追加
                    const hasRewards = btn.dataset.hasRewards === '1'; // 追加: 特典有無
                    const originalText = btn.innerHTML;
                    
                    btn.disabled = true;
                    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> 処理中';

                    // テンプレート選択リセット
                    templateSelect.value = "";
                    deliveryCompany.value = ""; // リセット
                    trackingNumber.value = ""; // リセット
                    document.getElementById('deliveryTypeDisplay').classList.add('d-none'); // バッジ非表示リセット

                    // クーポン入力欄の制御
                    couponCodeInput.value = ""; // リセット
                    if (hasRewards) {
                        couponInputContainer.classList.remove('d-none');
                        couponInputContainer.scrollIntoView({ behavior: 'smooth', block: 'center' }); // 目立たせる
                    } else {
                        couponInputContainer.classList.add('d-none');
                    }

                    try {
                        // Step 1: プレビュー取得 (初期状態)
                        // 初期状態では配送情報は空で送る
                        const response = await fetch('../../api/ajax/admin_approve_order.php', {
                            method: 'POST',
                            headers: {'Content-Type': 'application/json'},
                            body: JSON.stringify({ 
                                order_id: orderId, 
                                cast_id: castId,
                                shop_id: shopId,
                                item_id: itemId, // 追加
                                preview: true,
                                init_fetch: true // ★配送情報を自動取得
                            })
                        });
                        
                        const result = await response.json();
                        
                        if (result.success && result.preview) {
                            // プレビュー成功
                            document.getElementById('previewMessage').value = result.message;

                            // ★配送情報の自動入力
                            if (result.suggested_delivery) {
                                if (result.suggested_delivery.company_id) {
                                    deliveryCompany.value = result.suggested_delivery.company_id;
                                }
                                if (result.suggested_delivery.tracking_number) {
                                    trackingNumber.value = result.suggested_delivery.tracking_number;
                                }
                                
                                // 生の配送方法名の表示
                                if (result.suggested_delivery.raw_delivery_type_name) {
                                    const badge = document.getElementById('deliveryTypeDisplay');
                                    badge.textContent = result.suggested_delivery.raw_delivery_type_name;
                                    badge.classList.remove('d-none');
                                }

                                // デバッグ情報の表示
                                if (result.debug_raw_order) {
                                    document.getElementById('debugInfo').textContent = JSON.stringify(result.debug_raw_order, null, 2);
                                }

                                // 値が入ったらプレビューも更新した方が親切だが、
                                // init_fetch時にも変数は空文字で置換されているはずなので
                                // ユーザーが何か変更したときに再取得すればOK。
                                // ただし「ヤマト運輸」などが自動選択されたのにメッセージ内の {delivery_company} が空だと違和感あるかも？
                                // init_fetchの結果で一度 updatePreview を呼ぶか、
                                // admin_approve_order.php側で init_fetch 時は suggested_delivery を使ってメッセージ組むか。
                                // 現状 admin_approve_order.php は tracking_number はリクエストの入力をそのまま使う仕様。
                                // なので、ここで値をセットした後に再度 updatePreview() を呼ぶのが確実。
                                if (result.suggested_delivery.company_id || result.suggested_delivery.tracking_number) {
                                     // 値をセットしてから少し待って再更新（UI反映後）
                                     setTimeout(updatePreview, 100);
                                }
                            }
                            
                            // 本送信用データを一時保存
                            currentApproveConfig = { orderId, castId, shopId, itemId, hasRewards };
                            
                            confirmModal.show();
                            
                        } else {
                            throw new Error(result.error || 'プレビュー取得エラー');
                        }
                    } catch (error) {
                        alert('エラー: ' + error.message);
                    } finally {
                        // ボタン状態を戻す
                        btn.innerHTML = originalText;
                        btn.disabled = false;
                    }
                }
            });

            // 送信確定ボタンクリック時
            btnConfirmSend.addEventListener('click', async function() {
                if (!currentApproveConfig) return;
                
                // バリデーション: 特典ありの場合、クーポンコード必須
                if (currentApproveConfig.hasRewards) {
                    const code = couponCodeInput.value.trim();
                    if (!code) {
                        alert('【必須】クーポンコードを入力してください。\n特典申請が含まれている注文です。');
                        couponCodeInput.focus();
                        return;
                    }
                }
                
                this.disabled = true;
                this.innerHTML = '<span class="spinner-border spinner-border-sm"></span> 送信中...';
                
                try {
                    const sendResponse = await fetch('../../api/ajax/admin_approve_order.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify({ 
                            order_id: currentApproveConfig.orderId, 
                            cast_id: currentApproveConfig.castId,
                            shop_id: currentApproveConfig.shopId,
                            item_id: currentApproveConfig.itemId, // 追加
                            template_id: templateSelect.value, 
                            delivery_company_id: deliveryCompany.value, // 追加
                            tracking_number: trackingNumber.value, // 追加
                            coupon_code: couponCodeInput.value.trim(), // 追加
                            custom_message: document.getElementById('previewMessage').value 
                        }) 
                    });
                    // 以下略

                    
                    const sendResult = await sendResponse.json();
                    
                    if (sendResult.success) {
                        alert('承認・送信完了しました！');
                        confirmModal.hide();
                        // 親画面をリロードしてリストを更新
                        location.reload();
                    } else {
                        throw new Error(sendResult.error || '送信エラー');
                    }
                } catch (sendError) {
                    alert('送信エラー: ' + sendError.message);
                } finally {
                    this.disabled = false;
                    this.innerHTML = '<i class="fas fa-paper-plane"></i> 送信確定';
                }
            });

            document.querySelectorAll('.view-details').forEach(button => {
                button.addEventListener('click', function() {
                    const castId = this.dataset.castId;
                    const castName = this.dataset.castName;

                    modalTitle.textContent = `${castName} さんの未対応注文リスト`;
                    modalBody.innerHTML = `
                        <div class="text-center p-5">
                            <div class="spinner-border text-primary" role="status"></div>
                            <p class="mt-2">データを取得中...</p>
                        </div>
                    `;
                    detailModal.show();

                    // 非同期で詳細データを取得
                    fetch(`get_cast_orders_ajax.php?cast_id=${castId}`)
                        .then(response => response.text())
                        .then(html => {
                            modalBody.innerHTML = html;
                        })
                        .catch(error => {
                            modalBody.innerHTML = `<div class="alert alert-danger">データ取得エラー: ${error}</div>`;
                        });
                });
            });

            // 差し戻しボタンのイベント委譲（動的に生成されるボタン用）
            document.getElementById('modalBody').addEventListener('click', async function(e) {
                const btn = e.target.closest('.btn-reject');
                if (!btn) return;

                const orderId = btn.dataset.orderId;
                const itemId = btn.dataset.itemId;
                const productName = btn.dataset.productName;

                if (!confirm(`「${productName}」の対応を差し戻しますか？\nキャストに再対応を依頼します。`)) {
                    return;
                }

                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

                try {
                    const response = await fetch('../ajax/reject_order.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ order_id: orderId, item_id: itemId })
                    });
                    const result = await response.json();

                    if (result.success) {
                        alert('差し戻しが完了しました。');
                        location.reload();
                    } else {
                        throw new Error(result.error || '差し戻し失敗');
                    }
                } catch (error) {
                    alert('エラー: ' + error.message);
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-undo"></i> 差戻し';
                }
            });
        });
    </script>
</body>
</html>
