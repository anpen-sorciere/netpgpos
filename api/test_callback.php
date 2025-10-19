<?php
// シンプルなテストページ
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>BASE Callback テスト</h2>";
echo "<p>現在時刻: " . date('Y-m-d H:i:s') . "</p>";

echo "<h3>GET パラメータ:</h3>";
echo "<pre>" . print_r($_GET, true) . "</pre>";

echo "<h3>PHP情報:</h3>";
echo "<p>PHP Version: " . phpversion() . "</p>";
echo "<p>Memory Limit: " . ini_get('memory_limit') . "</p>";
echo "<p>Max Execution Time: " . ini_get('max_execution_time') . "</p>";

echo "<h3>ファイル存在確認:</h3>";
$files_to_check = [
    '../config.php',
    '../api/base_practical_auto_manager.php',
    '../dbconnect.php'
];

foreach ($files_to_check as $file) {
    echo "<p>" . $file . ": " . (file_exists($file) ? '存在' : '不存在') . "</p>";
}

echo "<h3>config.php読み込みテスト:</h3>";
try {
    require_once '../config.php';
    echo "<p style='color: green;'>config.php読み込み成功</p>";
    echo "<p>base_client_id: " . (isset($base_client_id) ? '設定済み' : '未設定') . "</p>";
    echo "<p>base_redirect_uri: " . (isset($base_redirect_uri) ? '設定済み' : '未設定') . "</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>config.php読み込みエラー: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<h3>データベース接続テスト:</h3>";
try {
    require_once '../dbconnect.php';
    echo "<p style='color: green;'>データベース接続成功</p>";
} catch (Exception $e) {
    echo "<p style='color: red;'>データベース接続エラー: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>
