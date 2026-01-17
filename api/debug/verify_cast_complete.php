<?php
// 検証用: キャストダッシュボード完了API (IDベース) テストスクリプト
// ブラウザからアクセスして実行することで、擬似的にAPIをテストする

require_once __DIR__ . '/../../../common/config.php';
require_once __DIR__ . '/../../../common/dbconnect.php';

session_start();

// 1. テストデータの準備 (キャストログイン状態の模倣)
// すでに存在することを前提とする（cast_id=1など）
$_SESSION['cast_id'] = 1; 

echo "<h1>Cast Complete Order API Test (ID Base)</h1>";

try {
    $pdo = new PDO(
        "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
        $user,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // 2. テスト対象の注文明細を取得 (まだ完了していないもの)
    // base_order_items から1件取得
    $stmt = $pdo->query("SELECT * FROM base_order_items WHERE cast_id = 1 AND cast_handled = 0 LIMIT 1");
    $target_item = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$target_item) {
        echo "<p style='color:red'>テスト可能な未対応データが見つかりません。syncを実行するか、データをリセットしてください。</p>";
        // 仕方ないので完了済みのものをリセットして試す
        $stmt = $pdo->query("SELECT * FROM base_order_items WHERE cast_id = 1 LIMIT 1");
        $target_item = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($target_item) {
            echo "<p>既存のデータをリセットしてテストします。ID: {$target_item['id']}</p>";
            $pdo->query("UPDATE base_order_items SET cast_handled = 0 WHERE id = {$target_item['id']}");
        } else {
             exit;
        }
    }

    echo "<h3>Target Item:</h3>";
    echo "<pre>"; print_r($target_item); echo "</pre>";

    // 3. APIエンドポイントに対してリクエストを送信 (cURLを使用)
    // 自身のURLからパスを構築
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
    $api_url = $protocol . "://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']) . "/../ajax/cast_complete_order.php";
    
    // api/ajax は session_startしているため、同じセッションIDを送る必要がある
    $cookie_string = 'PHPSESSID=' . session_id();

    $post_data = [
        'order_id' => $target_item['base_order_id'],
        'item_id' => $target_item['id'], // ★ ここが重要：IDを送る
        'template_id' => 1, // 仮のテンプレートID
        'product_name' => $target_item['product_name'] // 念のため送るが使われないはず
    ];

    echo "<h3>Sending Request to: {$api_url}</h3>";
    echo "Using Item ID: <strong>{$target_item['id']}</strong><br>";

    $ch = curl_init($api_url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_COOKIE, $cookie_string); // セッション維持
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_errno($ch)) {
        echo "<p style='color:red'>cURL Error: " . curl_error($ch) . "</p>";
    }
    curl_close($ch);

    echo "<h3>Response (HTTP {$http_code}):</h3>";
    echo "<pre>" . htmlspecialchars($response) . "</pre>";
    
    $result = json_decode($response, true);

    // 4. 結果確認
    if ($http_code == 200 && isset($result['success']) && $result['success']) {
        echo "<h3 style='color:green'>SUCCESS: API request successful.</h3>";
        
        // DBの状態を確認
        $stmt_check = $pdo->prepare("SELECT * FROM base_order_items WHERE id = ?");
        $stmt_check->execute([$target_item['id']]);
        $updated_item = $stmt_check->fetch(PDO::FETCH_ASSOC);
        
        echo "<h3>DB Check:</h3>";
        if ($updated_item['cast_handled'] == 1) {
            echo "<p style='color:green'>Verified: cast_handled is now 1 for ID {$target_item['id']}</p>";
        } else {
            echo "<p style='color:red'>FAILED: cast_handled is still 0</p>";
        }
        
    } else {
        echo "<h3 style='color:red'>FAILURE: API request failed.</h3>";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
