<?php
/**
 * 既存注文データのcast_id移行スクリプト
 * BASE APIから注文を再取得して、既存のbase_order_itemsにcast_idを設定
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(300); // 5分

require_once __DIR__ . '/../../../common/config.php';
require_once __DIR__ . '/../../../common/dbconnect.php';
require_once __DIR__ . '/../classes/base_practical_auto_manager.php';

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>cast_id移行</title></head><body>";
echo "<h1>既存注文データのcast_id移行</h1>";
echo "<p>BASE APIから注文を再取得して、cast_idを設定します...</p>";

try {
    $pdo = new PDO(
        "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
        $user,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
    
    $manager = new BasePracticalAutoManager();
    
    // 認証チェック
    $auth_status = $manager->getAuthStatus();
    $orders_ok = $auth_status['read_orders']['authenticated'] ?? false;
    $items_ok = $auth_status['read_items']['authenticated'] ?? false;
    
    if (!$orders_ok || !$items_ok) {
        die("<p style='color:red'>エラー: BASE API認証が必要です。</p></body></html>");
    }
    
    echo "<p>✓ 認証OK</p>";
    
    // キャスト名→cast_idマッピングを準備
    $castMap = [];
    $stmt = $pdo->query("SELECT cast_id, cast_name FROM cast_mst WHERE drop_flg = 0");
    while ($row = $stmt->fetch()) {
        $castMap[$row['cast_name']] = $row['cast_id'];
    }
    
    echo "<p>✓ キャストマッピング準備完了（" . count($castMap) . "件）</p>";
    echo "<ul>";
    foreach ($castMap as $name => $id) {
        echo "<li>" . htmlspecialchars($name) . " → ID:" . $id . "</li>";
    }
    echo "</ul>";
    
    // base_order_itemsを更新するための準備済みステートメント
    $updateStmt = $pdo->prepare("
        UPDATE base_order_items 
        SET cast_id = :cast_id 
        WHERE base_order_id = :base_order_id 
          AND product_id = :product_id 
          AND cast_id IS NULL
    ");
    
    // BASE APIから注文を取得（最大500件）
    $offset = 0;
    $limit = 100;
    $totalUpdated = 0;
    $totalSkipped = 0;
    
    echo "<h2>注文データ処理</h2>";
    
    for ($i = 0; $i < 5; $i++) {
        echo "<p>バッチ " . ($i + 1) . " (offset: $offset)...</p>";
        
        $response = $manager->getDataWithAutoAuth(
            'read_orders',
            '/orders',
            ['limit' => $limit, 'offset' => $offset]
        );
        
        $orders = $response['orders'] ?? [];
        $count = count($orders);
        
        echo "<p>→ {$count}件の注文を取得</p>";
        
        if ($count === 0) {
            echo "<p>データなし。終了します。</p>";
            break;
        }
        
        foreach ($orders as $order) {
            $order_id = $order['unique_key'] ?? null;
            if (!$order_id) continue;
            
            // 注文アイテムを処理
            if (isset($order['order_items']) && is_array($order['order_items'])) {
                foreach ($order['order_items'] as $item) {
                    $product_id = $item['item_id'] ?? 'unknown';
                    $castName = null;
                    
                    // オプションからキャスト名を抽出
                    if (isset($item['options'])) {
                        foreach ($item['options'] as $opt) {
                            $nm = $opt['option_name'] ?? '';
                            $val = $opt['option_value'] ?? '';
                            
                            if (mb_strpos($nm, 'キャスト名') !== false) {
                                $castName = $val;
                                break;
                            }
                        }
                    }
                    
                    // キャスト名が見つかってマッピングに存在する場合
                    if ($castName && isset($castMap[$castName])) {
                        $cast_id = $castMap[$castName];
                        
                        try {
                            $updateStmt->execute([
                                ':cast_id' => $cast_id,
                                ':base_order_id' => $order_id,
                                ':product_id' => $product_id
                            ]);
                            
                            if ($updateStmt->rowCount() > 0) {
                                $totalUpdated++;
                                echo "<span style='color:green'>✓</span> ";
                            } else {
                                $totalSkipped++;
                            }
                        } catch (Exception $e) {
                            echo "<span style='color:red'>✗ エラー: " . htmlspecialchars($e->getMessage()) . "</span><br>";
                        }
                    }
                }
            }
        }
        
        $offset += $limit;
        echo "<p>累計更新: {$totalUpdated}件、スキップ: {$totalSkipped}件</p>";
        flush();
    }
    
    echo "<h2>完了</h2>";
    echo "<p style='color:green; font-size:1.2em'>✓ 移行が完了しました</p>";
    echo "<ul>";
    echo "<li>更新件数: <strong>{$totalUpdated}</strong>件</li>";
    echo "<li>スキップ件数: {$totalSkipped}件（既に設定済み）</li>";
    echo "</ul>";
    
    echo "<p><a href='../cast/cast_login.php'>キャストログインページへ</a></p>";
    
} catch (Exception $e) {
    echo "<p style='color:red'>エラーが発生しました: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
}

echo "</body></html>";
