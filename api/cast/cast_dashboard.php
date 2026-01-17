<?php
session_start();
require_once __DIR__ . '/../../../common/config.php';

// 自動ログイン処理 (Remember Me)
if (!isset($_SESSION['cast_id']) && isset($_COOKIE['cast_remember_token'])) {
    try {
        $pdo_auth = new PDO(
            "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
            $user,
            $password,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        
        $token = $_COOKIE['cast_remember_token'];
        
        // トークン照合 (有効期限内かつトークン一致)
        $stmt_auth = $pdo_auth->prepare("
            SELECT * FROM cast_mst 
            WHERE remember_token = ? 
            AND remember_expires > NOW() 
            AND drop_flg = 0 
            AND login_enabled = 1
        ");
        $stmt_auth->execute([$token]);
        $cast_auth = $stmt_auth->fetch(PDO::FETCH_ASSOC);
        
        if ($cast_auth) {
            // 自動ログイン成功
            $_SESSION['cast_id'] = $cast_auth['cast_id'];
            $_SESSION['cast_name'] = $cast_auth['cast_name'];
            $_SESSION['cast_email'] = $cast_auth['email'];
            
            // 最終ログイン日時更新
            $upd = $pdo_auth->prepare("UPDATE cast_mst SET last_login_at = NOW() WHERE cast_id = ?");
            $upd->execute([$cast_auth['cast_id']]);

            // ★ 有効期限の延長 (最終利用から30日間キープ)
            $new_expires = time() + (30 * 24 * 60 * 60);
            $new_expires_date = date('Y-m-d H:i:s', $new_expires);

            // DB更新
            $upd_token = $pdo_auth->prepare("UPDATE cast_mst SET remember_expires = ? WHERE cast_id = ?");
            $upd_token->execute([$new_expires_date, $cast_auth['cast_id']]);

            // Cookie更新
            $cookie_path = dirname($_SERVER['SCRIPT_NAME']);
            $secure = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on');
            setcookie('cast_remember_token', $token, [
                'expires' => $new_expires,
                'path' => $cookie_path,
                'domain' => '',
                'secure' => $secure,
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
        }
    } catch (Exception $e) {
        // DBエラー時は無視してログイン画面へ
    }
}

if (!isset($_SESSION['cast_id'])) {
    header('Location: cast_login.php');
    exit;
}

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
    
    // 対応が必要な注文のみ取得（ordered, unpaid のみ。dispatchedは非表示）
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
        AND o.status IN ('ordered', 'unpaid')
        AND (oi.cast_handled = 0 OR oi.cast_handled IS NULL)
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
            'item_id' => $row['item_id'], // これが不足していた
            'product_name' => $row['product_name'],
            'quantity' => $row['quantity'],
            'price' => $row['price'],
            'customer_name_from_option' => $row['customer_name_from_option'],
            'item_surprise_date' => $row['item_surprise_date']
        ];
    }
    
    
    $orders = array_values($orders_temp);
    
    // 定型文一覧取得（キャスト使用可のみ）
    $stmt = $pdo->query("
        SELECT * FROM reply_message_templates 
        WHERE is_active = 1 AND allow_cast_use = 1
        ORDER BY display_order ASC
    ");
    $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
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
                <i class="fas fa-gem"></i>
                <?= htmlspecialchars($cast_name) ?> <span class="d-none d-sm-inline">さん</span>
            </div>
            <div>
                <a href="cast_dashboard.php" class="btn btn-primary rounded-pill me-2">
                    <i class="fas fa-sync-alt"></i> 更新
                </a>
                <a href="cast_logout.php" class="btn btn-logout">
                    <i class="fas fa-sign-out-alt"></i> <span class="d-none d-sm-inline">ログアウト</span>
                </a>
            </div>
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
                                <th style="width: 50%">商品名</th>
                                <th style="width: 12%; text-align: center">数量</th>
                                <th style="width: 20%; text-align: right">単価</th>
                                <th style="width: 18%; text-align: center">あなたの対応</th>
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
                                    <td style="text-align: center">
                                        <button class="btn btn-sm btn-primary" onclick="showCompletionModal('<?= $order['base_order_id'] ?>', '<?= $item['item_id'] ?>', '<?= htmlspecialchars($item['product_name'], ENT_QUOTES) ?>')">
                                            <i class="fas fa-play-circle"></i> 対応する
                                        </button>
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
                <i class="fas fa-check-circle"></i>
                <h4>対応すべき注文はありません</h4>
                <p class="text-muted">
                    新しい注文が入ると、こちらに表示されます。<br>
                    <small>※対応済みの注文は自動的に非表示になります</small>
        <?php endif; ?>
    </div>

    <!-- 対応完了モーダル -->
    <div class="modal fade" id="completionModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-check-circle"></i> 対応完了
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="modalOrderId">
                    <input type="hidden" id="modalItemId">
                    <p class="mb-3">
                        <strong id="modalProductName"></strong> の対応方法を選択してください：
                    </p>
                    
                    <!-- 動画アップロード (追加) -->
                    <div class="mb-3">
                        <label class="form-label fw-bold">お礼の動画 (任意):</label>
                        <input type="file" class="form-control" id="modalVideoFile" accept="video/mp4,video/quicktime,video/x-m4v">
                        <div class="form-text">MP4/MOV形式。アップロードするとURLが自動挿入されます。</div>
                    </div>

                    <div class="d-grid gap-2">
                        <?php foreach ($templates as $tmpl): ?>
                            <button class="btn btn-outline-success btn-message-type" onclick="completeOrder(<?= $tmpl['id'] ?>, '<?= htmlspecialchars($tmpl['template_name'], ENT_QUOTES) ?>')">
                                <i class="<?= htmlspecialchars($tmpl['icon_class']) ?>"></i> <?= htmlspecialchars($tmpl['template_name']) ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                    <div class="alert alert-info mt-3 mb-0" style="font-size: 0.9em;">
                        <i class="fas fa-info-circle"></i> 
                        選択した内容は「承認待ち」として管理者に送られます。管理者が確認後にお客様へ送信されます。
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    // 対応完了モーダル表示
    function showCompletionModal(orderId, itemId, productName) {
        if (!itemId) {
            alert('システムの更新が必要です。画面を再読み込みします。');
            location.reload();
            return;
        }
        document.getElementById('modalOrderId').value = orderId;
        document.getElementById('modalItemId').value = itemId;
        document.getElementById('modalProductName').textContent = productName;
        document.getElementById('modalVideoFile').value = ''; // ファイル選択リセット
        
        const modal = new bootstrap.Modal(document.getElementById('completionModal'));
        modal.show();
    }

    // 対応完了実行
    async function completeOrder(templateId, templateName) {
        const orderId = document.getElementById('modalOrderId').value;
        const itemId = document.getElementById('modalItemId').value;
        const productName = document.getElementById('modalProductName').textContent;
        const videoFileParams = document.getElementById('modalVideoFile');
        const button = event.target;
        
        if (!itemId) {
            alert('商品IDが見つかりません。画面を再読み込みしてください。');
            location.reload();
            return;
        }
        
        // 動画必須チェック: テンプレート名に「動画」が含まれている場合
        if (templateName && templateName.includes('動画')) {
            if (videoFileParams.files.length === 0) {
                alert('このテンプレートには動画の添付が必要です。\n「お礼の動画」から動画ファイルを選択してください。');
                return;
            }
        }

        // テストモード判定
        const urlParams = new URLSearchParams(window.location.search);
        const testMode = urlParams.get('test_mode') === '1';
        
        // ボタン無効化
        const allButtons = document.querySelectorAll('#completionModal .btn-message-type');
        allButtons.forEach(btn => btn.disabled = true);
        
        // 元のテキスト保存
        const originalBtnText = button.innerHTML;
        
        try {
            let videoUrl = null;
            let videoResult = null;

            // 1. 動画があれば先にアップロード
            if (videoFileParams.files.length > 0) {
                button.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> 動画送信中...';
                
                const formData = new FormData();
                formData.append('video_file', videoFileParams.files[0]);
                formData.append('cast_id', <?= json_encode($cast_id) ?>);
                formData.append('order_item_id', itemId);

                const uploadResp = await fetch('../ajax/upload_video.php', {
                    method: 'POST',
                    body: formData
                });
                const uploadResult = await uploadResp.json();
                
                if (!uploadResult.success) {
                    throw new Error('動画アップロード失敗: ' + (uploadResult.error || '不明なエラー'));
                }
                
                videoUrl = uploadResult.video_url;
                videoResult = uploadResult;
            }

            // 2. 対応完了処理 (video_urlを渡す)
            button.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>' + (testMode ? 'テスト中...' : '完了処理中...');

            const apiUrl = testMode 
                ? '../ajax/cast_complete_order.php?test=1' 
                : '../ajax/cast_complete_order.php';
            
            const response = await fetch(apiUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    order_id: orderId,
                    item_id: itemId,
                    template_id: templateId,
                    product_name: productName,
                    video_url: videoUrl // URLを追加
                })
            });
            
            const result = await response.json();
            
            if (result.success) {
                // 成功
                bootstrap.Modal.getInstance(document.getElementById('completionModal')).hide();
                
                // メッセージ
                let successMsg = result.reply_message;
                if (videoResult) {
                   successMsg += '<br><br><strong>※動画を添付しました</strong>';
                }

                // テストモード時は詳細表示
                if (result.test_mode) {
                    const details = `
                        <strong>テストモード実行完了</strong><br>
                        <small>BASE APIは実行されていません</small><br><br>
                        <strong>送信予定の内容:</strong><br>
                        ${result.reply_message}
                    `;
                    showAlert('info', 'テスト成功', details);
                } else {
                    showAlert('success', '対応完了しました！', successMsg);
                }
                
                // ページをリロード
                setTimeout(() => {
                    location.reload();
                }, testMode ? 3000 : 2000);
            } else {
                throw new Error(result.error || '処理に失敗しました');
            }
        } catch (error) {
            showAlert('danger', 'エラー', error.message);
            // エラーの内容がitem_id関連ならリロードを促す
            if (error.message.includes('item_id') || error.message.includes('リロード')) {
                setTimeout(() => {
                    if(confirm('最新の状態に更新するため、再読み込みしますか？')) {
                        location.reload();
                    }
                }, 2000);
            }
            // ボタン再有効化
            allButtons.forEach(btn => btn.disabled = false);
            button.innerHTML = originalBtnText; // 元に戻す
        }
    }

    // アラート表示
    function showAlert(type, title, message) {
        const alertHtml = `
            <div class="alert alert-${type} alert-dismissible fade show" role="alert" style="position: fixed; top: 80px; right: 20px; z-index: 9999; min-width: 300px;">
                <strong>${title}</strong><br>${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;
        document.body.insertAdjacentHTML('beforeend', alertHtml);
    }

    // ボタンの元テキスト保存
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.btn-message-type').forEach(btn => {
            btn.setAttribute('data-original-text', btn.innerHTML);
        });
    });
    </script>
</body>
</html>
