<?php
// 手動リフレッシュ・デバッグツール
// ブラウザからアクセスして、リフレッシュ機能が正常に動作するか詳細にテストします。

require_once __DIR__ . '/../../common/config.php';
require_once __DIR__ . '/../../common/dbconnect.php';
require_once __DIR__ . '/base_practical_auto_manager.php';

echo <<<HTML
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>Debug Token Refresh</title>
    <style>
        body { font-family: monospace; white-space: pre-wrap; padding: 20px; }
        .log { margin: 5px 0; border-bottom: 1px solid #eee; }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        .info { color: blue; }
    </style>
</head>
<body>
<h1>Token Refresh Debugger</h1>
<hr>
HTML;

try {
    $manager = new BasePracticalAutoManager();
    
    // 定義されているスコープ一覧（想定）
    $scopes = [
        'read_orders',
        'read_items',
        'write_orders',
        'read_users',
        'read_users_mail',
        'read_savings',
        'write_items'
    ];

    foreach ($scopes as $scope) {
        echo "<div class='log'>\n";
        echo "<strong>Target Scope: {$scope}</strong>\n";
        
        // 1. トークン取得チェック
        $token_data = $manager->getScopeToken($scope);
        if (!$token_data) {
            echo "<span class='error'>[ERROR] No token found in DB for this scope.</span>\n";
            continue;
        }
        
        echo "  - Access Token (Preview): " . substr($token_data['access_token'], 0, 10) . "...\n";
        echo "  - Access Expires: " . date('Y-m-d H:i:s', $token_data['access_expires']) . "\n";
        echo "  - Refresh Expires: " . date('Y-m-d H:i:s', $token_data['refresh_expires']) . "\n";
        
        // 2. 有効期限チェック
        $is_valid = $manager->isTokenValid($scope); 
        // Note: isTokenValid attempts refresh if expired. So if this returns true, refresh *might* have just happened.
        
        if ($is_valid) {
            echo "  - <span class='success'>[OK] Token is currently valid (or successfully auto-refreshed).</span>\n";
            
            // 再取得して更新されたか確認
            $new_data = $manager->getScopeToken($scope);
            if ($new_data['access_expires'] > $token_data['access_expires']) {
                echo "    -> <span class='success'>Token WAS refreshed during check! New expiry: " . date('Y-m-d H:i:s', $new_data['access_expires']) . "</span>\n";
            } else {
                 echo "    -> Token was not refreshed (Time remaining or refresh unnecessary).\n";
                 
                 // 強制リフレッシュテスト
                 echo "  - <strong>Attempting FORCE REFRESH...</strong>\n";
                 try {
                     $manager->refreshScopeToken($scope);
                     $refreshed_data = $manager->getScopeToken($scope);
                     echo "    -> <span class='success'>[SUCCESS] Force refresh worked! New expiry: " . date('Y-m-d H:i:s', $refreshed_data['access_expires']) . "</span>\n";
                 } catch (Exception $e) {
                     echo "    -> <span class='error'>[FAIL] Force refresh failed: " . htmlspecialchars($e->getMessage()) . "</span>\n";
                 }
            }
            
        } else {
            echo "  - <span class='error'>[FAIL] Token is INVALID. Auto-refresh failed?</span>\n";
            
            // 明示的にリフレッシュを試みてエラー内容を表示
            echo "  - <strong>Retrying Refresh to capture error...</strong>\n";
            try {
                $manager->refreshScopeToken($scope);
                echo "    -> <span class='success'>[SUCCESS] Manual retry worked! (Strange, why did isTokenValid fail?)</span>\n";
            } catch (Exception $e) {
                echo "    -> <span class='error'>[FAIL] Refresh Error: " . htmlspecialchars($e->getMessage()) . "</span>\n";
            }
        }
        echo "</div>\n";
    }

} catch (Exception $e) {
    echo "<div class='error'>Fatal Error: " . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</body></html>";
?>
