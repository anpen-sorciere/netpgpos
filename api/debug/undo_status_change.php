<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../../common/dbconnect.php';

// Undo実行フラグ
$undo_mode = isset($_GET['undo']) && $_GET['undo'] === '1';

echo "<h1>Undo Status Changes</h1>";
echo "<p>直近(60分以内)にステータスが「ordered」に変更された注文を分析し、過剰に修正されてしまったデータ（本来発送済みのままにしておくべきだったもの）を元に戻します。</p>";

try {
    $pdo = connect();
    if (!$pdo) exit;

    // 1. 直近で更新された 'ordered' の注文を取得
    $sql = "
        SELECT 
            o.base_order_id, 
            o.order_date, 
            o.customer_name, 
            o.status,
            o.updated_at,
            SUM(CASE WHEN oi.cast_handled > 0 THEN 1 ELSE 0 END) as handled_count,
            SUM(CASE WHEN (oi.cast_handled = 0 OR oi.cast_handled IS NULL) AND oi.cast_id > 0 THEN 1 ELSE 0 END) as unhandled_count
        FROM base_orders o
        INNER JOIN base_order_items oi ON o.base_order_id = oi.base_order_id
        WHERE o.status = 'ordered'
        AND o.updated_at > DATE_SUB(NOW(), INTERVAL 60 MINUTE)
        GROUP BY o.base_order_id
        ORDER BY o.updated_at DESC
        LIMIT 200
    ";

    $stmt = $pdo->query($sql);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 分類
    $candidates_for_undo = []; // 手動運用っぽいもの（handled_count == 0） -> dispatchedに戻すべき
    $valid_fixes = [];        // バグ修正で正しくorderedになったもの（handled_count > 0）

    foreach ($orders as $order) {
        if ($order['handled_count'] == 0) {
            // システム的な承認記録が一切ないのに ordered になっている = 多分前のツールで間違って戻された手動運用データ
            $candidates_for_undo[] = $order;
        } else {
            // 一部承認済み(handled > 0)で、未承認(unhandled > 0)がある = さっきのバグ修正で正しく戻されたデータ
            // (これらはorderedのままで正解なのでUndo対象外)
            $valid_fixes[] = $order;
        }
    }

    echo "<h3>Undo Candidates (Should be reverted to 'dispatched'?)</h3>";
    echo "<p>以下のデータは、<strong>「キャストによるシステム対応記録がない(Handled=0)」</strong>データです。<br>
    これらが直近で更新されている場合、先ほどのツールで<strong>誤って『未対応』に戻されてしまった手動発送データ</strong>である可能性が高いです。</p>";

    if (empty($candidates_for_undo)) {
        echo "<p>該当する候補はありませんでした。</p>";
    } else {
        echo "<div style='background:#fff3cd; padding:10px; margin-bottom:20px; border:1px solid #ffecb5;'>
        これらを一括で「発送済み(dispatched)」に戻しますか？<br>
        <a href='?undo=1' style='font-weight:bold; color:#d63384;'>[ここをクリックしてUndo実行 (Candidatesのみ dispatched に戻す)]</a>
        </div>";

        echo "<table border='1' cellpadding='5' style='width:100%; border-collapse:collapse;'>";
        echo "<tr style='background:#f2f2f2;'><th>Order ID</th><th>Updated At</th><th>Handled</th><th>Pending</th><th>Current Status</th><th>Result</th></tr>";
        
        foreach ($candidates_for_undo as $row) {
            $msg = "-";
            if ($undo_mode) {
                // Undo実行: dispatchedに戻す
                $stmtUpd = $pdo->prepare("UPDATE base_orders SET status = 'dispatched', updated_at = NOW() WHERE base_order_id = ?");
                $stmtUpd->execute([$row['base_order_id']]);
                $msg = "<span style='color:green;'>Reverted to dispatched</span>";
            }
            
            echo "<tr>";
            echo "<td>{$row['base_order_id']}</td>";
            echo "<td>{$row['updated_at']}</td>";
            echo "<td>{$row['handled_count']}</td>";
            echo "<td>{$row['unhandled_count']}</td>";
            echo "<td>{$row['status']}</td>";
            echo "<td>{$msg}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }

    echo "<br><hr><br>";
    echo "<h3 style='color:gray;'>Valid Fixes (Correctly kept as 'ordered')</h3>";
    echo "<p style='color:gray;'>以下は「一部対応済み」の記録があるため、バグ修正として正しく「未対応(ordered)」に戻されたデータです。これらはUndoしません。</p>";
    
    // 省略表示
    echo "<details><summary>詳細を表示 (" . count($valid_fixes) . "件)</summary>";
    echo "<table border='1' cellpadding='5' style='color:gray;'>";
    echo "<tr><th>Order ID</th><th>Handled</th><th>Pending</th></tr>";
    foreach ($valid_fixes as $row) {
        echo "<tr><td>{$row['base_order_id']}</td><td>{$row['handled_count']}</td><td>{$row['unhandled_count']}</td></tr>";
    }
    echo "</table></details>";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
