<?php
// キャストダッシュボード表示診断ツール
// なぜ「何も表示されない」のか、その理由（ステータス除外、サプライズ除外など）を内訳表示します。

require_once __DIR__ . '/../../common/config.php';
require_once __DIR__ . '/../../common/dbconnect.php';

session_start();

$target_cast_id = filter_input(INPUT_GET, 'cast_id', FILTER_VALIDATE_INT) ?? 38; // デフォルト: ウブさん

echo "<h1>Digest Cast Dashboard Diagnosis</h1>";
echo "Target Cast ID: <strong>{$target_cast_id}</strong><br><br>";

try {
    $pdo = new PDO(
        "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
        $user,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // 1. このキャストに割り当てられている全アイテム数を取得
    $stmtTotal = $pdo->prepare("SELECT COUNT(*) FROM base_order_items WHERE cast_id = ?");
    $stmtTotal->execute([$target_cast_id]);
    $total_items = $stmtTotal->fetchColumn();

    // 2. そのうち、未対応(cast_handled=0)のものを取得
    $stmtUnhandled = $pdo->prepare("SELECT COUNT(*) FROM base_order_items WHERE cast_id = ? AND (cast_handled = 0 OR cast_handled IS NULL)");
    $stmtUnhandled->execute([$target_cast_id]);
    $unhandled_items = $stmtUnhandled->fetchColumn();

    echo "<h3>Overview</h3>";
    echo "<ul>";
    echo "<li>Total Assigned Items: <strong>{$total_items}</strong></li>";
    echo "<li>Unhandled Items (cast_handled=0): <strong>{$unhandled_items}</strong></li>";
    echo "</ul>";

    // 3. 未対応なのに表示されない理由を分析
    // cast_dashboard.php と同じ結合ロジックを使用
    $sql = "
        SELECT 
            o.base_order_id,
            o.status as order_status,
            oi.product_name,
            oi.item_surprise_date
        FROM base_orders o
        INNER JOIN base_order_items oi ON o.base_order_id = oi.base_order_id
        WHERE oi.cast_id = :cast_id
        AND (oi.cast_handled = 0 OR oi.cast_handled IS NULL)
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':cast_id' => $target_cast_id]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<h3>Unhandled Items Detail (Why hidden?)</h3>";
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Order ID</th><th>Product</th><th>Order Status (BASE)</th><th>Surprise Date</th><th>Visible?</th><th>Reason</th></tr>";
    
    $today = date('Y-m-d');
    $visible_count = 0;

    foreach ($rows as $row) {
        $status = $row['order_status'];
        $sDate = $row['item_surprise_date'];
        
        $is_visible = true;
        $reason = "OK";

        // Logic Check 1: Status
        // status IN ('ordered', 'unpaid')
        if (!in_array($status, ['ordered', 'unpaid'])) {
            $is_visible = false;
            $reason = "Hidden by Status '{$status}' (Not ordered/unpaid)";
        }

        // Logic Check 2: Surprise Date
        if ($sDate && $sDate > $today) {
            $is_visible = false;
            $reason = "Hidden by Surprise Date (Future: {$sDate})";
        }

        if ($is_visible) {
            $visible_count++;
            $bg = "#ccffcc"; // Green
        } else {
            $bg = "#ffcccc"; // Red
        }

        echo "<tr style='background:{$bg}'>";
        echo "<td>{$row['base_order_id']}</td>";
        echo "<td>{$row['product_name']}</td>";
        echo "<td>{$status}</td>";
        echo "<td>{$sDate}</td>";
        echo "<td>" . ($is_visible ? 'YES' : 'NO') . "</td>";
        echo "<td>{$reason}</td>";
        echo "</tr>";
    }
    echo "</table>";

    echo "<h3>Summary</h3>";
    echo "<p>Currently Visible on Dashboard: <strong>{$visible_count}</strong> items.</p>";
    
    if ($unhandled_items > 0 && $visible_count === 0) {
        echo "<p style='color:red;'><strong>Conclusion:</strong> データはありますが、すべてステータス条件（発送済み等）またはサプライズ日付により非表示になっています。これは正常な挙動です。</p>";
    } elseif ($unhandled_items === 0) {
        echo "<p style='color:blue;'><strong>Conclusion:</strong> 未対応データ自体がありません。全て「対応済み」になっています。</p>";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
