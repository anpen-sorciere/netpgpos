<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/base_practical_auto_manager.php';

// セッション開始
session_start();

// BASE API認証チェック
if (!isset($_SESSION['base_access_token'])) {
    echo json_encode(['error' => 'BASE API認証が必要です。']);
    exit;
}

// 注文IDを取得
$order_id = $_GET['order_id'] ?? '';
if (empty($order_id)) {
    // デバッグ用: 直接ブラウザでアクセスした場合の表示
    if (!isset($_GET['order_id'])) {
        echo '<h2>get_order_options.php デバッグ</h2>';
        echo '<p>使用方法: get_order_options.php?order_id=28FEEB6EDAA52A18</p>';
        echo '<p>例: <a href="?order_id=28FEEB6EDAA52A18">?order_id=28FEEB6EDAA52A18</a></p>';
        exit;
    }
    echo json_encode(['error' => '注文IDが指定されていません。']);
    exit;
}

try {
    $manager = new BasePracticalAutoManager();
    
    // 注文詳細を取得（read_ordersスコープを使用）
    $order_detail_response = $manager->getDataWithAutoAuth('read_orders', '/orders/detail/' . $order_id);
    $order_detail = $order_detail_response['order'] ?? null;
    
    if (empty($order_detail)) {
        echo json_encode(['error' => '注文詳細が見つかりませんでした。']);
        exit;
    }
    
    // デバッグ: 注文詳細データの構造を確認
    $debug_info = [];
    $debug_info[] = '注文ID: ' . $order_id;
    $debug_info[] = 'order_items存在: ' . (isset($order_detail['order_items']) ? 'Yes' : 'No');
    if (isset($order_detail['order_items'])) {
        $debug_info[] = 'order_items数: ' . count($order_detail['order_items']);
        foreach ($order_detail['order_items'] as $index => $item) {
            $debug_info[] = '商品' . ($index + 1) . ': ' . ($item['title'] ?? 'N/A');
            $debug_info[] = '商品' . ($index + 1) . 'のオプション存在: ' . (isset($item['options']) ? 'Yes' : 'No');
            if (isset($item['options'])) {
                $debug_info[] = '商品' . ($index + 1) . 'のオプション数: ' . count($item['options']);
                foreach ($item['options'] as $optIndex => $option) {
                    $debug_info[] = 'オプション' . ($optIndex + 1) . ': ' . ($option['option_name'] ?? 'N/A') . ' = ' . ($option['option_value'] ?? 'N/A');
                }
            }
        }
    }
    
    // ニックネームとキャスト名を抽出
    $nicknames = [];
    $cast_names = [];
    
    if (isset($order_detail['order_items']) && is_array($order_detail['order_items'])) {
        foreach ($order_detail['order_items'] as $item) {
            if (isset($item['options']) && is_array($item['options'])) {
                foreach ($item['options'] as $option) {
                    $option_name = $option['option_name'] ?? '';
                    $option_value = $option['option_value'] ?? '';
                    
                    // お客様名（ニックネーム）を抽出
                    if ($option_name === 'お客様名') {
                        if (!empty($option_value) && !in_array($option_value, $nicknames)) {
                            $nicknames[] = htmlspecialchars($option_value);
                        }
                    }
                    
                    // キャスト名を抽出
                    if ($option_name === 'キャスト名') {
                        if (!empty($option_value) && !in_array($option_value, $cast_names)) {
                            $cast_names[] = htmlspecialchars($option_value);
                        }
                    }
                }
            }
        }
    }
    
    // デバッグ用: 直接ブラウザでアクセスした場合のHTML表示
    if (isset($_GET['debug']) && $_GET['debug'] === 'html') {
        echo '<h2>get_order_options.php デバッグ結果</h2>';
        echo '<h3>注文ID: ' . htmlspecialchars($order_id) . '</h3>';
        echo '<h4>デバッグ情報:</h4>';
        echo '<ul>';
        foreach ($debug_info as $info) {
            echo '<li>' . htmlspecialchars($info) . '</li>';
        }
        echo '</ul>';
        echo '<h4>抽出結果:</h4>';
        echo '<p>ニックネーム: ' . (!empty($nicknames) ? implode(', ', $nicknames) : 'なし') . '</p>';
        echo '<p>キャスト名: ' . (!empty($cast_names) ? implode(', ', $cast_names) : 'なし') . '</p>';
        echo '<h4>JSONレスポンス:</h4>';
        echo '<pre>' . htmlspecialchars(json_encode([
            'success' => true,
            'nicknames' => $nicknames,
            'cast_names' => $cast_names,
            'nickname_display' => !empty($nicknames) ? implode(', ', $nicknames) : '',
            'cast_display' => !empty($cast_names) ? implode(', ', $cast_names) : '',
            'debug_info' => $debug_info
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . '</pre>';
        exit;
    }
    
    echo json_encode([
        'success' => true,
        'nicknames' => $nicknames,
        'cast_names' => $cast_names,
        'nickname_display' => !empty($nicknames) ? implode(', ', $nicknames) : '',
        'cast_display' => !empty($cast_names) ? implode(', ', $cast_names) : '',
        'debug_info' => $debug_info
    ]);
    
} catch (Exception $e) {
    echo json_encode(['error' => 'エラー: ' . $e->getMessage()]);
}
?>
