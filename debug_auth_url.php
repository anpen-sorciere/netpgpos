<?php
require_once 'config.php';
require_once 'api/base_practical_auto_manager.php';

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>BASE認証URL確認</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .debug { background: #f0f0f0; padding: 15px; margin: 10px 0; border-radius: 5px; }
        .url { word-break: break-all; background: #e9ecef; padding: 10px; border-radius: 3px; }
        .btn { background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; display: inline-block; margin: 5px; }
    </style>
</head>
<body>
    <h2>BASE認証URL確認</h2>
    
    <?php
    try {
        $manager = new BasePracticalAutoManager();
        
        echo '<div class="debug">';
        echo '<h3>設定確認</h3>';
        echo '<p><strong>Client ID:</strong> ' . htmlspecialchars($base_client_id) . '</p>';
        echo '<p><strong>Redirect URI:</strong> ' . htmlspecialchars($base_redirect_uri) . '</p>';
        echo '<p><strong>API URL:</strong> ' . htmlspecialchars($base_api_url) . '</p>';
        echo '</div>';
        
        // テスト用のreturn_url
        $test_return_url = 'https://purplelion51.sakura.ne.jp/netpgpos/api/order_monitor.php?page=1';
        
        echo '<div class="debug">';
        echo '<h3>認証URL生成テスト</h3>';
        echo '<p><strong>テスト用return_url:</strong> ' . htmlspecialchars($test_return_url) . '</p>';
        
        $auth_url = $manager->getAuthUrl('read_items', $test_return_url);
        
        echo '<p><strong>生成された認証URL:</strong></p>';
        echo '<div class="url">' . htmlspecialchars($auth_url) . '</div>';
        
        // URLを解析
        $parsed_url = parse_url($auth_url);
        $query_params = [];
        parse_str($parsed_url['query'], $query_params);
        
        echo '<h4>URL解析結果:</h4>';
        echo '<ul>';
        echo '<li><strong>Base URL:</strong> ' . htmlspecialchars($parsed_url['scheme'] . '://' . $parsed_url['host'] . $parsed_url['path']) . '</li>';
        echo '<li><strong>Client ID:</strong> ' . htmlspecialchars($query_params['client_id'] ?? 'なし') . '</li>';
        echo '<li><strong>Redirect URI:</strong> ' . htmlspecialchars($query_params['redirect_uri'] ?? 'なし') . '</li>';
        echo '<li><strong>Scope:</strong> ' . htmlspecialchars($query_params['scope'] ?? 'なし') . '</li>';
        echo '<li><strong>State:</strong> ' . htmlspecialchars($query_params['state'] ?? 'なし') . '</li>';
        echo '</ul>';
        
        // Stateをデコード
        if (isset($query_params['state'])) {
            try {
                $state_data = json_decode(base64_decode($query_params['state']), true);
                echo '<h4>State デコード結果:</h4>';
                echo '<pre>' . htmlspecialchars(json_encode($state_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . '</pre>';
            } catch (Exception $e) {
                echo '<p style="color: red;">State デコードエラー: ' . htmlspecialchars($e->getMessage()) . '</p>';
            }
        }
        
        echo '</div>';
        
        echo '<div class="debug">';
        echo '<h3>テスト実行</h3>';
        echo '<a href="' . htmlspecialchars($auth_url) . '" class="btn" target="_blank">BASE認証を実行</a>';
        echo '<a href="api/order_monitor.php" class="btn">注文監視に戻る</a>';
        echo '</div>';
        
    } catch (Exception $e) {
        echo '<div class="debug" style="background: #f8d7da; color: #721c24;">';
        echo '<h3>エラー</h3>';
        echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
        echo '<p><strong>ファイル:</strong> ' . htmlspecialchars($e->getFile()) . '</p>';
        echo '<p><strong>行:</strong> ' . $e->getLine() . '</p>';
        echo '</div>';
    }
    ?>
</body>
</html>
