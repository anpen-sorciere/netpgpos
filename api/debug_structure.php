<?php
// APIデータ構造デバッグ用スクリプト
header('Content-Type: text/html; charset=utf-8');

// 必要な設定とDB接続を読み込む
require_once __DIR__ . '/../../common/config.php';
require_once __DIR__ . '/../../common/dbconnect.php';
require_once __DIR__ . '/../../common/functions.php';

require_once __DIR__ . '/base_practical_auto_manager.php';

echo '<pre>';
echo "データ構造解析開始...\n";

try {
    $manager = new BasePracticalAutoManager();
    $target_order_id = '630D93D6D9511DE5';
    
    echo "注文ID: {$target_order_id} の詳細を取得中...\n";
    
    // 特定の注文詳細を取得
    $order = $manager->getDataWithAutoAuth('read_orders', '/orders/detail/' . $target_order_id, []);
    
    if ($order) {
        echo "取得成功! データ構造:\n";
        print_r($order);
        
        echo "\n--------------------------------------------------\n";
        echo "オプション検索テスト:\n";
        $json = json_encode($order, JSON_UNESCAPED_UNICODE);
        if (strpos($json, 'サプライズ') !== false) {
            echo "✔ JSON内に「サプライズ」の文字列が見つかりました。\n";
        } else {
            echo "❌ JSON内に「サプライズ」の文字列が見つかりませんでした。\n";
            echo "文字コードや表記（例: Surprise）が異なる可能性があります。\n";
        }
    } else {
        echo "注文が見つかりませんでした。\n";
    }

} catch (Exception $e) {
    echo "エラー: " . $e->getMessage();
}
echo '</pre>';
