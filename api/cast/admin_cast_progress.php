<?php
/**
 * 管理者向け全キャスト対応状況監視画面
 * 各キャストの未対応件数や放置期間を一覧表示
 */
session_start();

// 簡易認証（index.phpなどから遷移している前提）
// 本来はauth checkが必要だが、現状の構成に合わせて簡易実装
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
            AND o.status IN ('ordered', 'unpaid')
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

            // 承認待ちかどうか
            if (!empty($order['cast_handled'])) {
                $approval_pending_count++;
            } else {
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
                        <label class="form-label fw-bold">送信メッセージプレビュー:</label>
                        <textarea class="form-control" id="previewMessage" rows="10" readonly style="background-color: #f8f9fa; font-family: monospace;"></textarea>
                    </div>
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
            let currentApproveConfig = null; // 送信データ保持用

            // イベントデリゲーション：動的に生成される「承認」ボタンのクリックを監視
            document.body.addEventListener('click', async function(e) {
                if (e.target && (e.target.classList.contains('btn-approve') || e.target.closest('.btn-approve'))) {
                    const btn = e.target.classList.contains('btn-approve') ? e.target : e.target.closest('.btn-approve');
                    
                    const orderId = btn.dataset.orderId;
                    const castId = btn.dataset.castId;
                    const originalText = btn.innerHTML;
                    
                    btn.disabled = true;
                    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> 処理中';

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
                            document.getElementById('previewMessage').value = result.message;
                            
                            // 本送信用データを一時保存
                            currentApproveConfig = { orderId, castId };
                            
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
                
                this.disabled = true;
                this.innerHTML = '<span class="spinner-border spinner-border-sm"></span> 送信中...';
                
                try {
                    const sendResponse = await fetch('../../api/ajax/admin_approve_order.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify({ 
                            order_id: currentApproveConfig.orderId, 
                            cast_id: currentApproveConfig.castId 
                        }) // previewなし = 本送信
                    });
                    
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
        });
    </script>
</body>
</html>
