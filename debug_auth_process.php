<?php
/**
 * BASE API 認証プロセス詳細デバッグスクリプト
 * 認証プロセスの各段階を詳細に確認
 */
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/api/base_practical_auto_manager.php';

echo "<h1>BASE API 認証プロセス詳細デバッグ</h1>";
echo "<p>認証プロセスの各段階を詳細に確認します</p>";

try {
    $practical_manager = new BasePracticalAutoManager();
    
    echo "<h2>1. 現在の認証状態</h2>";
    $auth_status = $practical_manager->getAuthStatus();
    
    echo "<div style='background: #f0f0f0; padding: 15px; border: 1px solid #ccc; border-radius: 5px;'>";
    foreach ($auth_status as $scope => $status) {
        $authenticated = $status['authenticated'] ? '✓ 認証済み' : '✗ 未認証';
        $expires = isset($status['expires']) ? $status['expires'] : '不明';
        $refresh_token = isset($status['refresh_token']) && !empty($status['refresh_token']) ? 'あり' : 'なし';
        $is_expired = isset($status['is_expired']) ? ($status['is_expired'] ? '期限切れ' : '有効') : '不明';
        
        echo "<p><strong>{$scope}:</strong> {$authenticated} (期限: {$expires}, リフレッシュ: {$refresh_token}, 状態: {$is_expired})</p>";
    }
    echo "</div>";
    
    echo "<h2>2. データベース内のトークン確認</h2>";
    try {
        require_once __DIR__ . '/dbconnect.php';
        
        $stmt = $pdo->prepare("SELECT * FROM base_api_tokens ORDER BY created_at DESC");
        $stmt->execute();
        $tokens = $stmt->fetchAll();
        
        if (empty($tokens)) {
            echo "<div style='color: red;'>✗ データベースにトークンが保存されていません</div>";
        } else {
            echo "<div style='background: #f9f9f9; padding: 10px; border: 1px solid #ddd; border-radius: 3px;'>";
            echo "<h3>保存されているトークン</h3>";
            foreach ($tokens as $token) {
                echo "<div style='margin-bottom: 10px; padding: 10px; border: 1px solid #ccc; border-radius: 3px;'>";
                echo "<p><strong>スコープ:</strong> " . htmlspecialchars($token['scope']) . "</p>";
                echo "<p><strong>作成日時:</strong> " . htmlspecialchars($token['created_at']) . "</p>";
                echo "<p><strong>アクセストークン期限:</strong> " . htmlspecialchars($token['expires_at']) . "</p>";
                echo "<p><strong>リフレッシュトークン期限:</strong> " . htmlspecialchars($token['refresh_expires_at']) . "</p>";
                echo "<p><strong>アクセストークン:</strong> " . (empty($token['access_token']) ? 'なし' : 'あり（暗号化済み）') . "</p>";
                echo "<p><strong>リフレッシュトークン:</strong> " . (empty($token['refresh_token']) ? 'なし' : 'あり（暗号化済み）') . "</p>";
                echo "</div>";
            }
            echo "</div>";
        }
        
    } catch (Exception $e) {
        echo "<div style='color: red;'>✗ データベース確認エラー: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
    
    echo "<h2>3. 認証URL生成テスト</h2>";
    try {
        $auth_urls = [];
        $scopes = ['orders_only', 'items_only'];
        
        foreach ($scopes as $scope) {
            $auth_url = $practical_manager->getAuthUrl($scope);
            $auth_urls[$scope] = $auth_url;
            echo "<p><strong>{$scope}:</strong> <a href='{$auth_url}' target='_blank'>認証URL</a></p>";
        }
        
    } catch (Exception $e) {
        echo "<div style='color: red;'>✗ 認証URL生成エラー: " . htmlspecialchars($e->getMessage()) . "</div>";
    }
    
    echo "<h2>4. 手動認証リンク</h2>";
    echo "<div style='background: #e8f4fd; padding: 15px; border: 1px solid #b3d9ff; border-radius: 5px;'>";
    echo "<p>以下のリンクをクリックして認証を実行してください：</p>";
    
    $scopes = ['orders_only', 'items_only'];
    foreach ($scopes as $scope) {
        $auth_url = $practical_manager->getAuthUrl($scope);
        echo "<p><a href='{$auth_url}' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; margin-right: 10px;' target='_blank'>{$scope} 認証を実行</a></p>";
    }
    echo "</div>";
    
    echo "<h2>5. セッション情報</h2>";
    echo "<div style='background: #fff3cd; padding: 15px; border: 1px solid #ffeaa7; border-radius: 5px;'>";
    echo "<h3>セッションデータ</h3>";
    echo "<pre style='background: white; padding: 10px; border: 1px solid #ccc; border-radius: 3px; overflow-x: auto;'>";
    echo htmlspecialchars(print_r($_SESSION, true));
    echo "</pre>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div style='color: red; background: #f8d7da; padding: 15px; border: 1px solid #f5c6cb; border-radius: 5px;'>";
    echo "<h3>エラーが発生しました</h3>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p><strong>ファイル:</strong> " . $e->getFile() . "</p>";
    echo "<p><strong>行:</strong> " . $e->getLine() . "</p>";
    echo "</div>";
}
?>
