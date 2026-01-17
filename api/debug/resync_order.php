<?php
/**
 * 特定注文IDの手動再同期
 */
set_time_limit(60);
require_once __DIR__ . '/../../../common/config.php';
require_once __DIR__ . '/../classes/base_practical_auto_manager.php';
// 最新のOrderSyncを使用するように修正
require_once __DIR__ . '/../classes/OrderSync.php';

$target_order_id = filter_input(INPUT_GET, 'order_id') ?? '';
$shop_id = filter_input(INPUT_GET, 'shop_id', FILTER_VALIDATE_INT) ?? 1;
$execute = isset($_POST['execute']) && $_POST['execute'] === 'true';

?>
<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><title>手動再同期</title></head>
<body>
<h1>BASE注文 手動再同期ツール (Header & Items)</h1>
<p>BASE APIから最新の注文情報を取得し、ヘッダー情報(Status含む)と明細情報をDBに強制上書き保存します。</p>

<form method="get">
    <div>
        Shop ID: 
        <select name="shop_id">
            <option value="1" <?= $shop_id == 1 ? 'selected' : '' ?>>Shop 1</option>
            <option value="2" <?= $shop_id == 2 ? 'selected' : '' ?>>Shop 2</option>
        </select>
    </div>
    <div>
        Order ID: <input type="text" name="order_id" value="<?= htmlspecialchars($target_order_id) ?>" size="30" placeholder="e.g. 7C12...">
    </div>
    <button type="submit">確認</button>
</form>
<hr>

<?php
if ($target_order_id) {
    echo '<h2>実行結果</h2>';
    echo '<pre>';
    echo "対象注文ID: {$target_order_id}\n";
    echo "Shop ID: {$shop_id}\n";
    echo "モード: " . ($execute ? "本番実行" : "確認のみ") . "\n\n";
    
    try {
        $pdo = new PDO(
            "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
            $user,
            $password,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        
        $manager = new BasePracticalAutoManager($shop_id);
        
        // STEP 1: 現在の状態確認
        echo "=== STEP 1: 現在の状態 ===\n";
        $stmt = $pdo->prepare("SELECT status, updated_at FROM base_orders WHERE base_order_id = ?");
        $stmt->execute([$target_order_id]);
        $current_header = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($current_header) {
            echo "base_orders Status: {$current_header['status']}\n";
            echo "base_orders Updated: {$current_header['updated_at']}\n";
        } else {
            echo "base_orders: 未登録\n";
        }
        
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM base_order_items WHERE base_order_id = ?");
        $stmt->execute([$target_order_id]);
        $current_count = $stmt->fetchColumn();
        echo "base_order_items の件数: {$current_count}件\n\n";
        
        // STEP 2: BASE APIから詳細取得
        echo "=== STEP 2: BASE API詳細取得 ===\n";
        
        $detail_response = $manager->getDataWithAutoAuth('read_orders', "/orders/detail/{$target_order_id}");
        
        if (isset($detail_response['order'])) {
            $order = $detail_response['order'];
            echo "✅ 詳細取得成功\n";
            echo "Status (BASE): " . ($order['dispatch_status'] ?? 'unknown') . "\n";
            echo "Modified (BASE): " . ($order['modified'] ?? 'null') . "\n";
            
            // 商品数確認
            $item_count = isset($order['order_items']) ? count($order['order_items']) : 0;
            echo "order_items 件数: {$item_count}件\n";
            
            if ($item_count > 0) {
                echo "\n商品一覧:\n";
                foreach ($order['order_items'] as $idx => $item) {
                    $num = $idx + 1;
                    echo "  {$num}. {$item['title']} (Status: {$item['status']})\n";
                }
            } else {
                echo "⚠️ order_itemsが空です\n";
            }
            
            // STEP 3: DB保存
            if ($execute) {
                echo "\n=== STEP 3: DB保存 ===\n";
                
                if ($item_count > 0) {
                    // OrderSyncクラスを使って保存
                    OrderSync::syncOrdersToDb($pdo, [$order], null, $shop_id);
                    echo "✅ DB保存実行 (OrderSync::syncOrdersToDb)\n";
                    
                    // 保存後の確認
                    $stmt = $pdo->prepare("SELECT status FROM base_orders WHERE base_order_id = ?");
                    $stmt->execute([$target_order_id]);
                    $new_status = $stmt->fetchColumn();
                    echo "保存後のDB Status: {$new_status}\n";
                    
                } else {
                    echo "❌ order_itemsが空のため保存不可\n";
                }
                
            } else {
                echo "\n=== STEP 3: DB保存（スキップ） ===\n";
                echo "実行するには以下のボタンを押してください:\n";
                ?>
                <form method="post" action="?order_id=<?= htmlspecialchars($target_order_id) ?>&shop_id=<?= $shop_id ?>">
                    <input type="hidden" name="execute" value="true">
                    <button type="submit" style="padding:10px; background:red; color:white;">本番実行 (DB上書き)</button>
                </form>
                <?php
            }
            
        } else {
            echo "❌ 詳細取得失敗: orderキーがありません\n";
        }
        
    } catch (Exception $e) {
        echo "❌ エラー: " . $e->getMessage() . "\n";
    }
    echo '</pre>';
}
?>
</body>
</html>
<?php exit; ?>
?>
