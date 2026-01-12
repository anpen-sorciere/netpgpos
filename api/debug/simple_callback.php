<?php
// 最小限のコールバックページ
header('Content-Type: text/html; charset=UTF-8');

echo "<h2>BASE Callback - 最小版</h2>";
echo "<p>現在時刻: " . date('Y-m-d H:i:s') . "</p>";

if (isset($_GET['code'])) {
    echo "<h3>認証コード受信</h3>";
    echo "<p>Code: " . htmlspecialchars($_GET['code']) . "</p>";
    
    if (isset($_GET['state'])) {
        echo "<p>State: " . htmlspecialchars($_GET['state']) . "</p>";
        
        // Stateをデコード
        try {
            $state_data = json_decode(base64_decode($_GET['state']), true);
            if ($state_data && isset($state_data['return_url'])) {
                echo "<p>Return URL: " . htmlspecialchars($state_data['return_url']) . "</p>";
                echo "<p><a href='" . htmlspecialchars($state_data['return_url']) . "'>元のページに戻る</a></p>";
            }
        } catch (Exception $e) {
            echo "<p>State デコードエラー: " . htmlspecialchars($e->getMessage()) . "</p>";
        }
    }
} else {
    echo "<h3>エラー</h3>";
    if (isset($_GET['error'])) {
        echo "<p>Error: " . htmlspecialchars($_GET['error']) . "</p>";
    } else {
        echo "<p>認証コードが受信されませんでした。</p>";
    }
}

echo "<p><a href='../order_monitor.php'>注文監視に戻る</a></p>";
?>
