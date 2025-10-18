<?php
session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/base_pre_auth_manager.php';

echo "<h1>BASE API 完全自動認証システム</h1>";
echo "<p>事前認証コードを使用して初回認証を自動化します。</p>";

echo "<h2>1. 事前認証コード設定状況</h2>";
$pre_auth_manager = new BasePreAuthManager();

// 環境変数の読み込み（.envファイルから）
$env_file = __DIR__ . '/.env';
if (file_exists($env_file)) {
    $lines = file($env_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            list($key, $value) = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
        }
    }
}

$scope_codes = [
    'orders_only' => $_ENV['BASE_PRE_AUTH_CODE_ORDERS'] ?? null,
    'items_only' => $_ENV['BASE_PRE_AUTH_CODE_ITEMS'] ?? null,
    'users_only' => $_ENV['BASE_PRE_AUTH_CODE_USERS'] ?? null,
    'users_mail_only' => $_ENV['BASE_PRE_AUTH_CODE_USERS_MAIL'] ?? null,
    'savings_only' => $_ENV['BASE_PRE_AUTH_CODE_SAVINGS'] ?? null,
    'write_items_only' => $_ENV['BASE_PRE_AUTH_CODE_WRITE_ITEMS'] ?? null,
    'write_orders_only' => $_ENV['BASE_PRE_AUTH_CODE_WRITE_ORDERS'] ?? null
];

foreach ($scope_codes as $scope_key => $code) {
    $status = $code ? '<span style="color: green;">✓ 設定済み</span>' : '<span style="color: red;">✗ 未設定</span>';
    echo "<strong>{$scope_key}</strong>: {$status}<br>";
}

echo "<h2>2. 完全自動認証実行</h2>";
echo "<p>設定済みの事前認証コードを使用して自動認証を実行します。</p>";

try {
    $results = $pre_auth_manager->performFullyAutomaticAuth();
    
    echo "<h3>認証結果</h3>";
    foreach ($results as $scope_key => $result) {
        if ($result['success']) {
            echo "<span style='color: green;'>✓ {$scope_key}: {$result['message']} (期限: {$result['expires_in']}秒)</span><br>";
        } else {
            echo "<span style='color: red;'>✗ {$scope_key}: {$result['message']}</span><br>";
        }
    }
    
    // 成功したスコープの数をカウント
    $success_count = count(array_filter($results, function($r) { return $r['success']; }));
    $total_count = count($results);
    
    if ($success_count > 0) {
        echo "<h3>認証成功！</h3>";
        echo "<p>{$success_count}/{$total_count} のスコープで認証に成功しました。</p>";
        
        if ($success_count >= 2) {
            echo "<h3>データ取得テスト</h3>";
            try {
                require_once __DIR__ . '/base_ultimate_scope_manager.php';
                $ultimate_manager = new BaseUltimateScopeManager();
                $data = $ultimate_manager->getCombinedOrderData(5);
                
                if ($data['error']) {
                    echo "<span style='color: red;'>データ取得エラー: " . htmlspecialchars($data['error']) . "</span><br>";
                } else {
                    echo "<span style='color: green;'>✓ データ取得・合成成功</span><br>";
                    echo "注文件数: " . count($data['orders']) . "<br>";
                    echo "商品件数: " . count($data['items']) . "<br>";
                    echo "合成済み注文件数: " . count($data['merged_orders']) . "<br>";
                }
                
            } catch (Exception $e) {
                echo "<span style='color: red;'>データ取得テスト失敗: " . htmlspecialchars($e->getMessage()) . "</span><br>";
            }
        }
    } else {
        echo "<h3>認証失敗</h3>";
        echo "<p>すべてのスコープで認証に失敗しました。</p>";
    }
    
} catch (Exception $e) {
    echo "<h3 style='color: red;'>システムエラー</h3>";
    echo htmlspecialchars($e->getMessage()) . "<br>";
}

echo "<h2>3. 事前認証コードの取得方法</h2>";
echo "<div style='background: #f0f0f0; padding: 15px; border: 1px solid #ccc; border-radius: 5px;'>";
echo "<h4>BASE管理画面での手順：</h4>";
echo "<ol>";
echo "<li>BASE管理画面にログイン</li>";
echo "<li>「設定」→「API連携」に移動</li>";
echo "<li>「新しい認証コードを生成」をクリック</li>";
echo "<li>必要なスコープを選択</li>";
echo "<li>生成された認証コードをコピー</li>";
echo "<li>api/.envファイルに設定</li>";
echo "</ol>";
echo "<br>";
echo "<h4>設定例：</h4>";
echo "<pre style='background: #fff; padding: 10px; border: 1px solid #ddd;'>";
echo "BASE_PRE_AUTH_CODE_ORDERS=abc123def456\n";
echo "BASE_PRE_AUTH_CODE_ITEMS=xyz789uvw012\n";
echo "</pre>";
echo "</div>";

echo "<h2>4. 管理機能</h2>";
echo '<a href="test_ultimate.php" style="background: #17a2b8; color: white; padding: 5px 10px; text-decoration: none; border-radius: 3px;">通常テストに戻る</a><br>';
echo '<a href="order_monitor.php?debug=nickname" style="background: #28a745; color: white; padding: 5px 10px; text-decoration: none; border-radius: 3px;">実際の注文監視画面</a><br>';
?>
