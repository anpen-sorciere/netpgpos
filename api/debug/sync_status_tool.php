<?php
/**
 * BASEステータス同期・修復ツール
 * BASE APIの情報を正として、ローカルDBの cast_handled フラグを更新します。
 */
require_once __DIR__ . '/../../../common/config.php';
require_once __DIR__ . '/../../../common/dbconnect.php';
require_once __DIR__ . '/../classes/base_practical_auto_manager.php';

session_start();

$shop_id = filter_input(INPUT_GET, 'shop_id', FILTER_VALIDATE_INT) ?? 1;
$mode = filter_input(INPUT_GET, 'mode') ?? 'order'; // 'order' or 'cast'
$target_id = filter_input(INPUT_GET, 'target_id') ?? ''; // Order ID or Cast ID
$execute = filter_input(INPUT_POST, 'execute');

// メッセージ表示用
$messages = [];

// データベース接続
try {
    $pdo = new PDO(
        "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
        $user,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    die("Database Connection Error: " . $e->getMessage());
}

// 処理実行
if ($execute && $target_id) {
    try {
        $manager = new BasePracticalAutoManager($shop_id);
        
        // 対象となる注文IDのリストを作成
        $order_ids = [];
        if ($mode === 'order') {
            $order_ids[] = $target_id;
        } elseif ($mode === 'cast') {
            // キャストが担当する直近の注文を取得 (未完了のもの優先、または過去1ヶ月分など)
            // ここではシンプルにベースオーダーアイテムテーブルから該当キャストの注文IDを抽出
            $stmt = $pdo->prepare("
                SELECT DISTINCT base_order_id 
                FROM base_order_items 
                WHERE cast_id = ? 
                ORDER BY id DESC 
                LIMIT 50
            ");
            $stmt->execute([$target_id]);
            $order_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        }

        if (empty($order_ids)) {
            $messages[] = ['type' => 'error', 'text' => '対象となる注文が見つかりませんでした。'];
        } else {
            $sync_count = 0;
            foreach ($order_ids as $order_id) {
                // APIから詳細取得
                try {
                    $detail = $manager->makeApiRequest('read_orders', '/orders/detail/' . $order_id);
                } catch (Exception $e) {
                    $messages[] = ['type' => 'error', 'text' => "Order {$order_id} API Error: " . $e->getMessage()];
                    continue;
                }

                if (empty($detail['order'])) {
                    $messages[] = ['type' => 'error', 'text' => "Order {$order_id} not found in BASE."];
                    continue;
                }

                $order_data = $detail['order'];
                $items = $order_data['order_items'] ?? [];
                
                foreach ($items as $item) {
                    $item_id = $item['item_id']; // Product ID
                    $order_item_id = $item['order_item_id'] ?? null; // Unique ID
                    $title = $item['title'] ?? 'Unknown Item';
                    $status = $item['status']; // 'ordered', 'dispatched', 'cancelled', etc.
                    
                    // BASEのステータス判定
                    $should_be_handled = ($status === 'dispatched' || $status === 'cancelled') ? 1 : 0;
                    
                    // DBの現状を取得
                    $current_handled = null;
                    $db_exists = false;
                    
                    // まず order_item_id で検索
                    $stmtCheck = $pdo->prepare("SELECT cast_handled FROM base_order_items WHERE base_order_item_id = ?");
                    $stmtCheck->execute([$order_item_id]);
                    $row = $stmtCheck->fetch(PDO::FETCH_ASSOC);
                    
                    if ($row) {
                        $db_exists = true;
                        $current_handled = (int)$row['cast_handled'];
                    } else {
                        // なければ product_id + order_id で検索（後方互換）
                        $stmtCheck = $pdo->prepare("SELECT cast_handled FROM base_order_items WHERE base_order_id = ? AND product_id = ?");
                        $stmtCheck->execute([$order_id, $item_id]);
                        $row = $stmtCheck->fetch(PDO::FETCH_ASSOC);
                        if ($row) {
                            $db_exists = true;
                            $current_handled = (int)$row['cast_handled'];
                        }
                    }

                    // 比較と更新
                    if (!$db_exists) {
                        $messages[] = ['type' => 'error', 'text' => "[MISSING] Item {$title} (ID: {$order_item_id}) - DBにレコードが存在しません。"];
                        continue;
                    }

                    if ($current_handled === $should_be_handled) {
                        $status_str = $should_be_handled ? '対応済' : '未対応';
                        $messages[] = ['type' => 'info', 'text' => "[SKIP] Item {$title} - BASE: {$status} / DB: {$status_str} (一致しています)"];
                    } else {
                        // 更新実行
                        if ($order_item_id) {
                            $sql = "UPDATE base_order_items SET cast_handled = ? WHERE base_order_item_id = ?";
                            $stmt = $pdo->prepare($sql);
                            $stmt->execute([$should_be_handled, $order_item_id]);
                        } else {
                            $sql = "UPDATE base_order_items SET cast_handled = ? WHERE base_order_id = ? AND product_id = ?";
                            $stmt = $pdo->prepare($sql);
                            $stmt->execute([$should_be_handled, $order_id, $item_id]);
                        }
                        
                        $from = $current_handled ? '対応済' : '未対応';
                        $to = $should_be_handled ? '対応済' : '未対応';
                        $messages[] = ['type' => 'success', 'text' => "[UPDATED] Item {$title} - DB Status: {$from} -> {$to} (BASE Status: {$status})"];
                        $sync_count++;
                    }
                }
            }
            if ($sync_count === 0) {
                 $messages[] = ['type' => 'info', 'text' => "更新が行われたアイテムはありませんでした。詳細は上記ログを確認してください。"];
            } else {
                 $messages[] = ['type' => 'success', 'text' => "合計 {$sync_count} 件のアイテムステータスを同期しました。"];
            }
        }

    } catch (Exception $e) {
        $messages[] = ['type' => 'error', 'text' => "Error: " . $e->getMessage()];
    }
}

?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>BASE Status Sync Tool</title>
    <style>
        body { font-family: sans-serif; padding: 20px; }
        .form-group { margin-bottom: 15px; }
        .msg { padding: 10px; margin-bottom: 10px; border-radius: 4px; }
        .msg.error { background: #fee; color: #c00; }
        .msg.success { background: #eef; color: #00c; }
        .msg.info { background: #eee; color: #333; }
        label { display: inline-block; width: 100px; font-weight: bold; }
        input[type=text], select { padding: 5px; width: 300px; }
        button { padding: 10px 20px; background: #007bff; color: white; border: none; cursor: pointer; }
        button:hover { background: #0056b3; }
    </style>
</head>
<body>
    <h1>BASE Status Sync Tool</h1>
    <p>BASE APIの最新ステータスを取得し、ローカルDBの対応状況(cast_handled)を強制的に同期させます。</p>
    <p><strong>ルール:</strong> BASEが <code>dispatched/cancelled</code> なら <strong>対応済(1)</strong>、<code>ordered</code> なら <strong>未対応(0)</strong> に上書きします。</p>

    <?php foreach ($messages as $msg): ?>
        <div class="msg <?= $msg['type'] ?>"><?= htmlspecialchars($msg['text']) ?></div>
    <?php endforeach; ?>

    <form method="get" action="">
        <div class="form-group">
            <label>Shop ID:</label>
            <select name="shop_id" onchange="this.form.submit()">
                <option value="1" <?= $shop_id == 1 ? 'selected' : '' ?>>Shop 1 (ソルシエール)</option>
                <option value="2" <?= $shop_id == 2 ? 'selected' : '' ?>>Shop 2 (レーヴェス)</option>
            </select>
        </div>
        <div class="form-group">
            <label>Mode:</label>
            <select name="mode" onchange="this.form.submit()">
                <option value="order" <?= $mode == 'order' ? 'selected' : '' ?>>Single Order (注文ID指定)</option>
                <option value="cast" <?= $mode == 'cast' ? 'selected' : '' ?>>By Cast (キャストID指定)</option>
            </select>
        </div>
        <div class="form-group">
            <label>Target ID:</label>
            <input type="text" name="target_id" value="<?= htmlspecialchars($target_id) ?>" placeholder="<?= $mode == 'order' ? 'Order ID (e.g. 7C12...)' : 'Cast ID (e.g. 38)' ?>">
        </div>
        
        <?php if ($target_id): ?>
            <div class="form-group">
                <button type="submit" formmethod="post" name="execute" value="1" onclick="return confirm('本当に同期を実行しますか？ローカルのステータスが上書きされます。')">
                    Sync Status Checked
                </button>
            </div>
        <?php else: ?>
            <div class="form-group">
                <button type="submit">Check</button>
            </div>
        <?php endif; ?>
    </form>
</body>
</html>
