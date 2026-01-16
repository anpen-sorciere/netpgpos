<?php
/**
 * 注文ステータス確認スクリプト
 */
session_start();
if (!isset($_SESSION['cast_id'])) {
    echo "キャストログインが必要です";
    exit;
}

require_once __DIR__ . '/../../../common/config.php';

$pdo = new PDO(
    "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
    $user,
    $password,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$cast_id = $_SESSION['cast_id'];

// 注文ステータスを確認
$sql = "
    SELECT 
        o.base_order_id,
        o.order_date,
        o.status,
        COUNT(oi.id) as item_count
    FROM base_orders o
    INNER JOIN base_order_items oi ON o.base_order_id = oi.base_order_id
    WHERE oi.cast_id = :cast_id
    GROUP BY o.base_order_id
    ORDER BY o.order_date DESC
    LIMIT 50
";

$stmt = $pdo->prepare($sql);
$stmt->execute([':cast_id' => $cast_id]);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo '<h2>注文ステータス確認</h2>';
echo '<table border="1" cellpadding="10">';
echo '<tr><th>注文ID</th><th>注文日</th><th>ステータス</th><th>商品数</th><th>表示される内容</th></tr>';

foreach ($orders as $order) {
    $status = $order['status'];
    $display = '';
    
    if ($status === 'ordered' || $status === 'unpaid') {
        $display = '「完了」ボタン';
    } elseif ($status === 'shipping') {
        $display = '「対応済み」バッジ';
    } else {
        $display = "「{$status}」バッジ";
    }
    
    echo "<tr>";
    echo "<td>{$order['base_order_id']}</td>";
    echo "<td>{$order['order_date']}</td>";
    echo "<td><strong>{$status}</strong></td>";
    echo "<td>{$order['item_count']}件</td>";
    echo "<td>{$display}</td>";
    echo "</tr>";
}

echo '</table>';

// ステータスの種類をカウント
echo '<h3>ステータス集計</h3>';
$stmt = $pdo->prepare("
    SELECT 
        o.status,
        COUNT(DISTINCT o.base_order_id) as order_count
    FROM base_orders o
    INNER JOIN base_order_items oi ON o.base_order_id = oi.base_order_id
    WHERE oi.cast_id = :cast_id
    GROUP BY o.status
");
$stmt->execute([':cast_id' => $cast_id]);
$status_counts = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo '<ul>';
foreach ($status_counts as $sc) {
    echo "<li><strong>{$sc['status']}</strong>: {$sc['order_count']}件</li>";
}
echo '</ul>';
?>
