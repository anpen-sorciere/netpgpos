<?php
require_once __DIR__ . '/../../../common/config.php';
require_once __DIR__ . '/../../../common/dbconnect.php';
require_once __DIR__ . '/../../../common/functions.php';
require_once __DIR__ . '/../classes/base_practical_auto_manager.php';

// セッション開始
session_start();

// BASE API認証チェック (BasePracticalAutoManagerを使用するため、セッションチェックは不要)
// if (!isset($_SESSION['base_access_token'])) { ... }

// 注文IDを取得
$order_id = $_GET['order_id'] ?? '';
if (empty($order_id)) {
    // デバッグ用: 直接ブラウザでアクセスした場合の表示
    if (!isset($_GET['order_id'])) {
        echo '<h2>get_order_items.php デバッグ</h2>';
        echo '<p>使用方法: get_order_items.php?order_id=28FEEB6EDAA52A18</p>';
        echo '<p>例: <a href="?order_id=28FEEB6EDAA52A18">?order_id=28FEEB6EDAA52A18</a></p>';
        exit;
    }
    echo json_encode(['error' => '注文IDが指定されていません。']);
    exit;
}

try {
    $manager = new BasePracticalAutoManager();
    
    // デバッグ: 認証状況を確認
    $debug_info = [];
    $debug_info[] = '注文ID: ' . $order_id;
    
    // 各スコープの認証状況を確認
    $scopes = ['read_orders', 'read_items', 'write_orders'];
    foreach ($scopes as $scope) {
        $is_valid = $manager->isTokenValid($scope);
        $debug_info[] = "スコープ {$scope}: " . ($is_valid ? '有効' : '無効');
    }
    

    // DB接続 (Cast Portal Sync用)
    $sync_pdo = null;
    try {
        $sync_pdo = new PDO(
            "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
            $user,
            $password,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]
        );
    } catch (Exception $e) {
        // Ignore
    }

    // 注文詳細を取得（read_ordersスコープを使用）
    $order_detail_response = $manager->getDataWithAutoAuth('read_orders', '/orders/detail/' . $order_id);
    $order_detail = $order_detail_response['order'] ?? null;
    
    if (empty($order_detail)) {
        echo json_encode(['error' => '注文詳細が見つかりませんでした。']);
        exit;
    }

    // ★ DB同期 (Cast Portal用)
    if ($sync_pdo && $order_detail) {
        syncOrdersToDb($sync_pdo, [$order_detail]);
    }
    
    // 商品情報を抽出
    $items = [];
    
    if (isset($order_detail['order_items']) && is_array($order_detail['order_items'])) {
        foreach ($order_detail['order_items'] as $index => $item) {
            $item_info = [
                'index' => $index + 1,
                'title' => $item['title'] ?? 'N/A',
                'item_id' => $item['item_id'] ?? 'N/A',
                'amount' => $item['amount'] ?? 'N/A',
                'price' => $item['price'] ?? 0,
                'total' => $item['total'] ?? 0,
                'status' => $item['status'] ?? 'N/A',
                'options' => []
            ];
            
            // オプション情報を抽出
            if (isset($item['options']) && is_array($item['options'])) {
                foreach ($item['options'] as $option) {
                    $opt_name = $option['option_name'] ?? '';
                    $opt_value = $option['option_value'] ?? '';
                    $item_info['options'][] = [
                        'option_name' => $opt_name,
                        'option_value' => $opt_value,
                        'name' => $opt_name,   // 旧仕様互換
                        'value' => $opt_value  // 旧仕様互換
                    ];
                }
            }
            
            $items[] = $item_info;
        }
    }
    
    // デバッグ用: 直接ブラウザでアクセスした場合のHTML表示
    if (isset($_GET['debug']) && $_GET['debug'] === 'html') {
        echo '<h2>get_order_items.php デバッグ結果</h2>';
        echo '<h3>注文ID: ' . htmlspecialchars($order_id) . '</h3>';
        echo '<h4>デバッグ情報:</h4>';
        echo '<ul>';
        foreach ($debug_info as $info) {
            echo '<li>' . htmlspecialchars($info) . '</li>';
        }
        echo '</ul>';
        echo '<h4>APIレスポンス構造:</h4>';
        echo '<pre>';
        print_r(array_keys($order_detail_response));
        echo '</pre>';
        if (isset($order_detail_response['order'])) {
             echo '<h4>Items Count (Wrapped): ' . count($order_detail_response['order']['order_items'] ?? []) . '</h4>';
        } else {
             echo '<h4>Items Count (Direct): ' . count($order_detail_response['order_items'] ?? []) . '</h4>';
             echo '<p>Warning: "order" key missing. Dumping root keys:</p>';
             print_r(array_keys($order_detail_response));
        }
        echo '<h4>商品数: ' . count($items) . '</h4>';
        echo '<h4>商品一覧:</h4>';
        echo '<ul>';
        foreach ($items as $item) {
            echo '<li>商品' . $item['index'] . ': ' . htmlspecialchars($item['title']) . ' (オプション数: ' . count($item['options']) . ')</li>';
        }
        echo '</ul>';
        echo '<h4>JSONレスポンス:</h4>';
        echo '<pre>' . htmlspecialchars(json_encode([
            'success' => true,
            'items' => $items,
            'item_count' => count($items),
            'debug_info' => $debug_info
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . '</pre>';
        exit;
    }
    
    echo json_encode([
        'success' => true,
        'items' => $items,
        'item_count' => count($items),
        'debug_info' => $debug_info
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'error' => 'エラー: ' . $e->getMessage(),
        'debug_info' => [
            'セッションアクセストークン存在: ' . (isset($_SESSION['base_access_token']) ? 'Yes' : 'No'),
            'トークン長: ' . (isset($_SESSION['base_access_token']) ? strlen($_SESSION['base_access_token']) : 'N/A'),
            'エラーファイル: ' . $e->getFile(),
            'エラー行: ' . $e->getLine()
        ]
    ]);
}

// ★ キャストポータル用データ同期関数
function syncOrdersToDb($pdo, $orders) {
    if (empty($orders)) return;

    // orders アップサート文
    $stmtOrder = $pdo->prepare("
        INSERT INTO orders (order_id, ordered_at, customer_name, total_price, payment_method, dispatch_status, is_surprise, surprise_date)
        VALUES (:order_id, :ordered_at, :customer_name, :total_price, :payment_method, :dispatch_status, :is_surprise, :surprise_date)
        ON DUPLICATE KEY UPDATE
            customer_name = VALUES(customer_name),
            total_price = VALUES(total_price),
            payment_method = VALUES(payment_method),
            dispatch_status = VALUES(dispatch_status),
            is_surprise = VALUES(is_surprise),
            surprise_date = VALUES(surprise_date),
            last_synced_at = NOW()
    ");

    // order_items アップサート文
    // UNIQUE KEY (order_id, base_item_id, cast_name) を利用
    $stmtItem = $pdo->prepare("
        INSERT INTO order_items (base_item_id, order_id, title, price, quantity, customer_name, cast_name, item_surprise_date)
        VALUES (:base_item_id, :order_id, :title, :price, :quantity, :customer_name, :cast_name, :item_surprise_date)
        ON DUPLICATE KEY UPDATE
            title = VALUES(title),
            price = VALUES(price),
            quantity = VALUES(quantity),
            customer_name = VALUES(customer_name),
            item_surprise_date = VALUES(item_surprise_date)
    ");

    foreach ($orders as $order) {
        $order_id = $order['unique_key'] ?? null;
        if (!$order_id) continue;

        // データの整形
        $ordered_at = date('Y-m-d H:i:s', is_numeric($order['ordered']) ? $order['ordered'] : strtotime($order['ordered']));
        $last_name = $order['last_name'] ?? '';
        $first_name = $order['first_name'] ?? '';
        $customer_name = trim($last_name . ' ' . $first_name);
        $total_price = $order['total'] ?? 0;
        $payment_method = $order['payment'] ?? '';
        $dispatch_status = $order['dispatch_status'] ?? 'unknown';

        // サプライズ判定（オーダーレベル）
        $is_surprise = 0;
        $surprise_date = null;
        
        if (isset($order['order_items']) && is_array($order['order_items'])) {
            foreach ($order['order_items'] as $item) {
                if (isset($item['options'])) {
                    foreach ($item['options'] as $opt) {
                        $nm = $opt['option_name'] ?? '';
                        $val = $opt['option_value'] ?? '';
                        if (mb_strpos($nm, 'サプライズ') !== false) {
                            $is_surprise = 1;
                            $surprise_date = $val;
                        }
                    }
                }
            }
        }
        
        // Order実行
        try {
            $stmtOrder->execute([
                ':order_id' => $order_id,
                ':ordered_at' => $ordered_at,
                ':customer_name' => $customer_name,
                ':total_price' => $total_price,
                ':payment_method' => $payment_method,
                ':dispatch_status' => $dispatch_status,
                ':is_surprise' => $is_surprise,
                ':surprise_date' => $surprise_date // nullの場合はNULLが入るはず
            ]);
        } catch (Exception $e) {
            // エラーログだけ吐いて継続
            // error_log("Order Sync Error ($order_id): " . $e->getMessage());
        }

        // Items実行
        if (isset($order['order_items']) && is_array($order['order_items'])) {
            foreach ($order['order_items'] as $item) {
                $base_item_id = $item['item_id'] ?? 'unknown'; // item_idがない場合はunknown
                $title = $item['title'] ?? '';
                $price = $item['price'] ?? 0;
                $quantity = $item['amount'] ?? 1;

                // オプション解析
                $item_customer = null;
                $item_cast = null; // nullなら誰のものでもない
                $item_surprise_date = null;

                if (isset($item['options'])) {
                    foreach ($item['options'] as $opt) {
                        $nm = $opt['option_name'] ?? $opt['name'] ?? '';
                        $val = $opt['option_value'] ?? $opt['value'] ?? '';

                        if (mb_strpos($nm, 'お客様名') !== false || mb_strpos($nm, 'ニックネーム') !== false) {
                            $item_customer = $val;
                        }
                        if (mb_strpos($nm, 'キャスト名') !== false) {
                            $item_cast = $val;
                        }
                        if (mb_strpos($nm, 'サプライズ') !== false) {
                            $item_surprise_date = $val;
                        }
                    }
                }

                try {
                    $stmtItem->execute([
                        ':base_item_id' => $base_item_id,
                        ':order_id' => $order_id,
                        ':title' => $title,
                        ':price' => $price,
                        ':quantity' => $quantity,
                        ':customer_name' => $item_customer,
                        ':cast_name' => $item_cast, // 文字列またはnull
                        ':item_surprise_date' => $item_surprise_date
                    ]);
                } catch (Exception $e) {
                    // error_log("Item Sync Error ($order_id / $base_item_id): " . $e->getMessage());
                }
            }
        }
    }
}
?>
