<?php
require_once __DIR__ . '/../../../common/config.php';
require_once __DIR__ . '/../../../common/dbconnect.php';

// 対象の注文ID
$target_order_id = 'C1307E3FA616BB54'; 

// 正しいIDか確認 (174885216 の可能性もあるので、ユーザーが提示した両方に対応できるよう、入力から取るか、もしくは確認してから実行)
// ユーザーは「174885216」と「C1307E3FA616BB54」の両方を提示している。
// base_orders.base_order_id は通常どちらが入っているか？ => DB定義次第だが、診断結果で出ているIDを使うのが確実。

$id = $_GET['id'] ?? $target_order_id;

echo "<h1>Status Fix Tool</h1>";
echo "Target ID: " . htmlspecialchars($id) . "<br>";

try {
    $pdo = connect();
    
    // 現在の状態確認
    $stmt = $pdo->prepare("SELECT base_order_id, status FROM base_orders WHERE base_order_id = ?");
    $stmt->execute([$id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        // 数値IDでも試す
        $alt_id = '174885216';
        $stmt->execute([$alt_id]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($order) {
            $id = $alt_id;
            echo "Found with alternative ID: " . htmlspecialchars($id) . "<br>";
        } else {
            die("Order not found.");
        }
    }

    echo "Current Status: <strong>" . htmlspecialchars($order['status']) . "</strong><br>";

    if ($order['status'] !== 'ordered' && $order['status'] !== 'unpaid') {
        echo "Updating status to 'ordered'...<br>";
        
        $upd = $pdo->prepare("UPDATE base_orders SET status = 'ordered', updated_at = NOW() WHERE base_order_id = ?");
        $upd->execute([$id]);
        
        echo "Status updated successfully.<br>";
        echo "Please check the dashboard again.";
    } else {
        echo "Status is already ordered/unpaid. No change needed.";
    }

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
