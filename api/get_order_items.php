<?php
require_once __DIR__ . '/../../common/config.php';
require_once __DIR__ . '/../../common/dbconnect.php';
require_once __DIR__ . '/../../common/functions.php';
require_once __DIR__ . '/base_practical_auto_manager.php';

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
    
    // 注文詳細を取得（read_ordersスコープを使用）
    $order_detail_response = $manager->getDataWithAutoAuth('read_orders', '/orders/detail/' . $order_id);
    $order_detail = $order_detail_response['order'] ?? null;
    
    if (empty($order_detail)) {
        echo json_encode(['error' => '注文詳細が見つかりませんでした。']);
        exit;
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
?>
