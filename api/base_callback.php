<?php
// BASE API OAuth認証コールバック処理
require_once __DIR__ . '/../../common/config.php';
require_once __DIR__ . '/../../common/dbconnect.php';
require_once __DIR__ . '/../../common/functions.php';
session_start();

// エラーハンドリング
$error = '';
$success = '';

try {
    // 認証コードの取得
    if (isset($_GET['code'])) {
        $auth_code = $_GET['code'];
        
        // アクセストークンの取得
        $token_data = getBaseAccessToken($auth_code);
        
        if ($token_data && isset($token_data['access_token'])) {
            // セッションにトークンを保存
            $_SESSION['base_access_token'] = $token_data['access_token'];
            $_SESSION['base_refresh_token'] = $token_data['refresh_token'] ?? '';
            $_SESSION['base_token_expires'] = time() + ($token_data['expires_in'] ?? 3600);
            
            $success = 'BASE API認証が完了しました！';
        } else {
            $error = 'アクセストークンの取得に失敗しました。';
        }
    } elseif (isset($_GET['error'])) {
        $error = '認証エラー: ' . htmlspecialchars($_GET['error']);
    } else {
        $error = '認証コードが取得できませんでした。';
    }
} catch (Exception $e) {
    $error = 'エラーが発生しました: ' . $e->getMessage();
}

/**
 * BASE APIからアクセストークンを取得
 */
function getBaseAccessToken($auth_code) {
    global $base_client_id, $base_client_secret, $base_redirect_uri;
    
    $url = 'https://api.thebase.in/1/oauth/token';
    
    $post_data = [
        'grant_type' => 'authorization_code',
        'client_id' => $base_client_id,
        'client_secret' => $base_client_secret,
        'redirect_uri' => $base_redirect_uri,
        'code' => $auth_code
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200) {
        return json_decode($response, true);
    } else {
        error_log("BASE API Token Error: HTTP $http_code - $response");
        return false;
    }
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BASE API認証結果</title>
    <link href="https://unpkg.com/sanitize.css" rel="stylesheet"/>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <div class="container">
        <h1><i class="fas fa-check-circle"></i> BASE API認証結果</h1>
        
        <?php if ($success): ?>
            <div style="background-color: #d4edda; color: #155724; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                <i class="fas fa-check"></i> <?= htmlspecialchars($success) ?>
            </div>
            
            <div style="background-color: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                <h3>認証情報</h3>
                <p><strong>アクセストークン:</strong> <?= htmlspecialchars(substr($_SESSION['base_access_token'], 0, 20)) ?>...</p>
                <p><strong>有効期限:</strong> <?= date('Y-m-d H:i:s', $_SESSION['base_token_expires']) ?></p>
            </div>
            
        <?php elseif ($error): ?>
            <div style="background-color: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                <i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <div class="control-buttons">
            <a href="../base_data_sync_top.php?utype=<?= htmlspecialchars($_SESSION['utype'] ?? '1024') ?>" class="btn btn-primary">
                <i class="fas fa-arrow-left"></i> BASEデータ同期に戻る
            </a>
            <a href="../index.php?utype=<?= htmlspecialchars($_SESSION['utype'] ?? '1024') ?>" class="btn btn-secondary">
                <i class="fas fa-home"></i> メニューに戻る
            </a>
        </div>
    </div>
</body>
</html>
