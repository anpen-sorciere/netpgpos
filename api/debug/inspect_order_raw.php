<?php
// 特定注文の生データ確認用スクリプト
require_once __DIR__ . '/../../../common/config.php';
require_once __DIR__ . '/../../../common/dbconnect.php';
require_once __DIR__ . '/../classes/base_practical_auto_manager.php';

session_start();

// エラーが発生した注文ID
$target_order_id = '1EB4D1B9CF519AE3';
$target_shop_id = 2; // レーヴェス

echo "<h1>Inspect Order Raw Data</h1>";
echo "Target Order ID: {$target_order_id}<br>";
echo "Target Shop ID: {$target_shop_id}<br>";

try {
    // Manager初期化 (Shop IDを指定)
    $manager = new BasePracticalAutoManager($target_shop_id);
    
    // トークン状態確認
    $status = $manager->getAuthStatus();
    if (empty($status['read_orders']['authenticated'])) {
        throw new Exception("read_orders scope is not authenticated for shop {$target_shop_id}");
    }

    // 特定の注文を取得
    echo "<h3>Fetching order detail from BASE API...</h3>";
    $endpoint = '/orders/detail/' . $target_order_id;
    $response = $manager->makeApiRequest('read_orders', $endpoint);

    if ($response) {
        echo "<h3>Raw Response:</h3>";
        echo "<textarea style='width:100%; height:400px; font-family:monospace;'>" . json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</textarea>";
        
        if (isset($response['order'])) {
            $order = $response['order'];
            echo "<h3>Parsed Fields:</h3>";
            echo "<table border='1'>";
            echo "<tr><th>Field</th><th>Value</th><th>strtotime() result</th></tr>";
            
            $fields = ['ordered', 'updated', 'created'];
            foreach ($fields as $field) {
                $val = $order[$field] ?? 'NULL';
                $ts = strtotime($val);
                $date = $ts ? date('Y-m-d H:i:s', $ts) : 'FALSE';
                echo "<tr><td>{$field}</td><td>{$val}</td><td>{$ts} ({$date})</td></tr>";
            }
            echo "</table>";
        }
    } else {
        echo "<p style='color:red'>No response data.</p>";
    }

} catch (Exception $e) {
    echo "<p style='color:red'>Error: " . $e->getMessage() . "</p>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}
