<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

// タイムアウト対策
set_time_limit(300);

require_once __DIR__ . '/../../../common/dbconnect.php';
require_once __DIR__ . '/../classes/base_practical_auto_manager.php';

$sync_mode = isset($_GET['sync']) && $_GET['sync'] === '1';

echo "<h1>BASE Status Sync Repair</h1>";
echo "<p>BASEの最新データを正とし、直近でステータスが変更された注文の整合性を回復します。</p>";

try {
    $pdo = connect();
    if (!$pdo) exit;

    // 1. 直近(60分)で 'ordered' になったデータを取得
    // (先ほどの修復ツールで変更されたものが対象)
    $sql = "
        SELECT base_order_id, shop_id, status, updated_at
        FROM base_orders 
        WHERE status = 'ordered'
        AND updated_at > DATE_SUB(NOW(), INTERVAL 60 MINUTE)
        ORDER BY shop_id, updated_at DESC
        LIMIT 200
    ";
    
    $stmt = $pdo->query($sql);
    $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($orders)) {
        echo "<p>対象となる直近の変更データがありませんでした。</p>";
        exit;
    }

    // shop_idごとにグループ化
    $orders_by_shop = [];
    foreach ($orders as $o) {
        $orders_by_shop[$o['shop_id']][] = $o;
    }

    echo "<div style='background:#e2e3e5; padding:10px; margin-bottom:20px; border:1px solid #d6d8db;'>
        対象: " . count($orders) . "件<br>
        <a href='?sync=1' style='font-weight:bold; color:#0d6efd;'>[BASEと同期して修正を実行する]</a>
    </div>";

    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Order ID</th><th>Shop</th><th>Local Status</th><th>BASE Status</th><th>Diff?</th><th>Result</th></tr>";

    foreach ($orders_by_shop as $shop_id => $shop_orders) {
        // マネージャー初期化
        try {
            $manager = new BasePracticalAutoManager($shop_id);
        } catch (Exception $e) {
            echo "<tr><td colspan='6' style='color:red;'>Shop ID {$shop_id} Error: " . $e->getMessage() . "</td></tr>";
            continue;
        }

        foreach ($shop_orders as $order) {
            $oid = $order['base_order_id'];
            $base_status = 'Fetch Error';
            $is_diff = false;
            $msg = '-';
            $item_details = '';

            if ($sync_mode || true) { // Always fetch for preview
                // APIリクエスト
                try {
                    // APIレートリミットへの配慮（少しsleep）
                    usleep(200000); // 0.2秒

                    $res = $manager->makeApiRequest('read_orders', '/orders/detail/' . $oid);
                    
                    if (isset($res['order']['dispatch_status'])) {
                        $base_status = $res['order']['dispatch_status'];
                        $api_items = $res['order']['order_items'] ?? [];
                        
                        // ステータス不一致なら更新 (Syncモード時)
                        if ($base_status !== $order['status']) {
                            $is_diff = true;
                            if ($sync_mode) {
                                $upd = $pdo->prepare("UPDATE base_orders SET status = ?, updated_at = NOW() WHERE base_order_id = ?");
                                $upd->execute([$base_status, $oid]);
                                $msg = "<span style='color:blue; font-weight:bold;'>Updated to {$base_status}</span>";
                            } else {
                                $msg = "<span style='color:orange;'>Will Update to {$base_status}</span>";
                            }
                        } else {
                            $msg = "<span style='color:green;'>Match (No change)</span>";
                        }

                        // アイテム詳細の比較生成
                        $item_details .= "<table style='font-size:0.85em; width:100%; margin-top:5px; border-top:1px dashed #ccc;'>";
                        $item_details .= "<tr style='background:#f9f9f9;'><th>Item</th><th>BASE Status</th><th>Local Handled</th></tr>";
                        
                        // DB上のアイテム取得
                        $stmtItems = $pdo->prepare("SELECT * FROM base_order_items WHERE base_order_id = ?");
                        $stmtItems->execute([$oid]);
                        $db_items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

                        foreach ($api_items as $a_item) {
                            $a_status = $a_item['status'];
                            $d_handled = '-';
                            
                            // マッチング
                            foreach ($db_items as $d_item) {
                                if ((isset($d_item['base_order_item_id']) && $d_item['base_order_item_id'] == $a_item['order_item_id'])
                                    || ($d_item['product_id'] == $a_item['item_id'])) {
                                    $d_handled = $d_item['cast_handled'];
                                    break;
                                }
                            }

                            $item_details .= "<tr>
                                <td>" . htmlspecialchars($a_item['title']) . "</td>
                                <td>{$a_status}</td>
                                <td>" . ($d_handled > 0 ? 'Done' : 'Pending') . "</td>
                            </tr>";
                        }
                        $item_details .= "</table>";

                    } else {
                        $msg = "<span style='color:red;'>API Error (No status)</span>";
                    }

                } catch (Exception $e) {
                    $msg = "<span style='color:red;'>API Error: " . $e->getMessage() . "</span>";
                }
            }

            $bg = $is_diff ? '#fff3cd' : '#fff';

            echo "<tr style='background:{$bg}'>";
            echo "<td>{$oid}<br>{$item_details}</td>";
            echo "<td>{$shop_id}</td>";
            echo "<td>{$order['status']}</td>";
            echo "<td>" . $base_status . "</td>";
            echo "<td>" . ($is_diff ? '<strong>YES</strong>' : '-') . "</td>";
            echo "<td>{$msg}</td>";
            echo "</tr>";
        }
    }
    echo "</table>";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
