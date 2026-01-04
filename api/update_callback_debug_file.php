<?php
// base_callback_debug.php の内容を更新するスクリプト
$target_file = __DIR__ . '/base_callback_debug.php';

$new_content = <<<'EOD'
<?php
// BASE API OAuth Callack Handler (Updated by Antigravity)
// Saves tokens to database for persistent auto-refresh
require_once __DIR__ . '/../../common/config.php';
require_once __DIR__ . '/../../common/dbconnect.php';
require_once __DIR__ . '/base_practical_auto_manager.php';
session_start();

$code = $_GET['code'] ?? null;
$state = $_GET['state'] ?? null;
$error = $_GET['error'] ?? null;

if ($error) {
    die("認証エラー: " . htmlspecialchars($error));
}

if (!$code) {
    die("認証コードが見つかりません");
}

try {
    $manager = new BasePracticalAutoManager();
    
    // デフォルト値
    $scope = 'read_orders';
    $return_url = 'order_monitor.php';
    
    // stateからスコープと戻り先を取得
    if ($state) {
        $state_json = base64_decode($state);
        $state_data = json_decode($state_json, true);
        if ($state_data) {
            $scope = $state_data['scope'] ?? $scope;
            $return_url = $state_data['return_url'] ?? $return_url;
        }
    }
    
    // トークン交換と保存
    $manager->exchangeCodeForToken($code, $scope);
    
    // 成功メッセージとリダイレクト
    echo "<h1>認証完了</h1>";
    echo "<p>スコープ: " . htmlspecialchars($scope) . " の認証に成功しました。</p>";
    echo "<p>データをデータベースに保存しました。自動更新が有効になります。</p>";
    echo "<p><a href='" . htmlspecialchars($return_url) . "'>元のページに戻る</a></p>";
    echo "<script>setTimeout(function(){ window.location.href = '" . $return_url . "'; }, 3000);</script>";
    
} catch (Exception $e) {
    echo "<h1>エラーが発生しました</h1>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><a href='order_monitor.php'>戻る</a></p>";
}
?>
EOD;

// ファイルに書き込み（ファイルが存在しない場合は作成され、存在する場合は上書きされる）
if (file_put_contents($target_file, $new_content) !== false) {
    echo "Successfully updated base_callback_debug.php\n";
} else {
    echo "Failed to update base_callback_debug.php\n";
}
?>
