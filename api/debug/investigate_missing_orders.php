<?php
/**
 * 未対応データが表示されない原因を調査
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
$today = date('Y-m-d');

echo "<h2>未対応データ調査</h2>";
echo "<p>ログイン中のキャストID: {$cast_id}</p>";
echo "<p>今日の日付: {$today}</p>";

// 1. 全ての ordered/unpaid をキャストID関係なく取得
echo "<h3>1. 全システム内の ordered/unpaid 注文</h3>";
$stmt = $pdo->query("
    SELECT base_order_id, order_date, status, customer_name
    FROM base_orders
    WHERE status IN ('ordered', 'unpaid')
    ORDER BY order_date DESC
    LIMIT 50
");
$all_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<p>全体: " . count($all_orders) . "件</p>";
echo "<table border='1' cellpadding='5'><tr><th>注文ID</th><th>注文日</th><th>ステータス</th><th>顧客名</th></tr>";
foreach ($all_orders as $o) {
    echo "<tr><td>{$o['base_order_id']}</td><td>{$o['order_date']}</td><td>{$o['status']}</td><td>{$o['customer_name']}</td></tr>";
}
echo "</table>";

// 2. 自分のキャストIDが紐付いている ordered/unpaid
echo "<h3>2. あなた（cast_id={$cast_id}）に紐付いている ordered/unpaid</h3>";
$stmt = $pdo->prepare("
    SELECT DISTINCT o.base_order_id, o.order_date, o.status, o.customer_name
    FROM base_orders o
    INNER JOIN base_order_items oi ON o.base_order_id = oi.base_order_id
    WHERE oi.cast_id = :cast_id
    AND o.status IN ('ordered', 'unpaid')
    ORDER BY o.order_date DESC
");
$stmt->execute([':cast_id' => $cast_id]);
$my_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<p>あなたに紐付き: " . count($my_orders) . "件</p>";
echo "<table border='1' cellpadding='5'><tr><th>注文ID</th><th>注文日</th><th>ステータス</th><th>顧客名</th></tr>";
foreach ($my_orders as $o) {
    echo "<tr><td>{$o['base_order_id']}</td><td>{$o['order_date']}</td><td>{$o['status']}</td><td>{$o['customer_name']}</td></tr>";
}
echo "</table>";

// 3. サプライズ日付でフィルターされている可能性
echo "<h3>3. サプライズ日付フィルター後</h3>";
$stmt = $pdo->prepare("
    SELECT 
        o.base_order_id,
        o.order_date,
        o.status,
        oi.product_name,
        oi.item_surprise_date
    FROM base_orders o
    INNER JOIN base_order_items oi ON o.base_order_id = oi.base_order_id
    WHERE oi.cast_id = :cast_id
    AND o.status IN ('ordered', 'unpaid')
    ORDER BY o.order_date DESC
");
$stmt->execute([':cast_id' => $cast_id]);
$all_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

$filtered_out = [];
$passed = [];

foreach ($all_items as $item) {
    if ($item['item_surprise_date'] && $item['item_surprise_date'] > $today) {
        $filtered_out[] = $item;
    } else {
        $passed[] = $item;
    }
}

echo "<p>サプライズフィルター通過: " . count($passed) . "件</p>";
echo "<p>サプライズフィルターで除外: " . count($filtered_out) . "件</p>";

if (!empty($filtered_out)) {
    echo "<h4>除外された商品（未来のサプライズ）:</h4>";
    echo "<table border='1' cellpadding='5'><tr><th>注文ID</th><th>商品名</th><th>サプライズ日付</th></tr>";
    foreach ($filtered_out as $item) {
        echo "<tr><td>{$item['base_order_id']}</td><td>{$item['product_name']}</td><td style='color:red'>{$item['item_surprise_date']}</td></tr>";
    }
    echo "</table>";
}

// 4. 12月のデータ確認
echo "<h3>4. 12月の ordered/unpaid データ</h3>";
$stmt = $pdo->query("
    SELECT base_order_id, order_date, status, customer_name
    FROM base_orders
    WHERE status IN ('ordered', 'unpaid')
    AND order_date >= '2024-12-01'
    AND order_date < '2025-01-01'
    ORDER BY order_date DESC
");
$dec_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "<p>12月の未対応注文: " . count($dec_orders) . "件</p>";

if (!empty($dec_orders)) {
    echo "<table border='1' cellpadding='5'><tr><th>注文ID</th><th>注文日</th><th>cast_id紐付き状況</th></tr>";
    foreach ($dec_orders as $o) {
        $stmt2 = $pdo->prepare("SELECT COUNT(*) FROM base_order_items WHERE base_order_id = ? AND cast_id = ?");
        $stmt2->execute([$o['base_order_id'], $cast_id]);
        $has_cast = $stmt2->fetchColumn() > 0;
        
        $cast_status = $has_cast ? "✅ 紐付きあり" : "❌ 紐付きなし";
        echo "<tr><td>{$o['base_order_id']}</td><td>{$o['order_date']}</td><td>{$cast_status}</td></tr>";
    }
    echo "</table>";
}

echo "<h3>結論</h3>";
echo "<ul>";
echo "<li>全システム: " . count($all_orders) . "件の未対応注文</li>";
echo "<li>あなたに紐付き: " . count($my_orders) . "件</li>";
echo "<li>サプライズフィルター通過: " . count($passed) . "件（実際に表示される）</li>";
echo "<li>12月データ: " . count($dec_orders) . "件</li>";
echo "</ul>";
?>
