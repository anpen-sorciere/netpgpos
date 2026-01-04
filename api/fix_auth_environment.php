<?php
// 自動認証環境修復スクリプト
// 暗号化キーの再生成とトークンテーブルの初期化を行います。
require_once __DIR__ . '/../../common/config.php';
require_once __DIR__ . '/../../common/dbconnect.php';

echo <<<HTML
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>AUTH Environment Fix</title>
    <style>
        body { font-family: sans-serif; padding: 20px; max-width: 800px; margin: 0 auto; }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        .warning { color: orange; font-weight: bold; }
        pre { background: #f5f5f5; padding: 10px; border: 1px solid #ccc; }
        button { padding: 10px 20px; font-size: 1.2em; cursor: pointer; }
    </style>
</head>
<body>
<h1>BASE API 自動認証環境 修復ツール</h1>
HTML;

try {
    $pdo = new PDO(
        "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
        $user,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        echo "<h2>実行結果</h2>";

        // 1. 暗号化キーの再生成
        $new_key = bin2hex(random_bytes(32));
        
        // 既存のキーを確認
        $stmt = $pdo->prepare("SELECT value FROM system_config WHERE key_name = 'encryption_key'");
        $stmt->execute();
        $exists = $stmt->fetch();

        if ($exists) {
            $stmt = $pdo->prepare("UPDATE system_config SET value = ?, updated_at = ? WHERE key_name = 'encryption_key'");
            $stmt->execute([$new_key, time()]);
            echo "<p class='success'>✓ 暗号化キーを更新しました (Length: " . strlen($new_key) . ")</p>";
        } else {
            $stmt = $pdo->prepare("INSERT INTO system_config (key_name, value, created_at, updated_at) VALUES ('encryption_key', ?, ?, ?)");
            $stmt->execute([$new_key, time(), time()]);
            echo "<p class='success'>✓ 新しい暗号化キーを作成しました (Length: " . strlen($new_key) . ")</p>";
        }

        // 2. トークンテーブルの初期化
        // 暗号化キーが変わると既存のトークンは復号できなくなるため削除する
        $pdo->exec("TRUNCATE TABLE base_api_tokens");
        echo "<p class='success'>✓ 古いトークンデータを消去しました（テーブル初期化）</p>";

        echo "<hr>";
        echo "<p><strong>修復が完了しました。</strong></p>";
        echo "<p>以下のボタンから再度認証を行ってください。</p>";
        echo "<a href='setup_auth.php'><button>初期認証画面へ移動 (setup_auth.php)</button></a>";

    } else {
        echo "<div class='warning'>注意: この操作を行うと、既存のBASE API認証トークンはすべて無効になり、再認証が必要になります。</div>";
        echo "<h3>現在の状態チェック</h3>";
        
        // キーの状態
        $stmt = $pdo->prepare("SELECT value FROM system_config WHERE key_name = 'encryption_key'");
        $stmt->execute();
        $key_row = $stmt->fetch();
        $key_len = $key_row ? strlen($key_row['value']) : 0;
        
        echo "<p>現在の暗号化キー長: <span class='" . ($key_len > 0 ? "success" : "error") . "'>{$key_len}</span> (0の場合は異常です)</p>";
        
        // トークン数
        $stmt = $pdo->query("SELECT COUNT(*) FROM base_api_tokens");
        $count = $stmt->fetchColumn();
        echo "<p>保存されているトークン数: {$count}件</p>";

        echo "<form method='post' onsubmit='return confirm(\"本当に修復を実行しますか？現在保存されているトークンは削除されます。\");'>";
        echo "<button type='submit'>修復を実行する</button>";
        echo "</form>";
    }

} catch (PDOException $e) {
    echo "<p class='error'>データベースエラー: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "</body></html>";
?>
