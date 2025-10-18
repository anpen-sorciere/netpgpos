<?php
// エラー表示を有効化
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<!-- デバッグ開始 -->";

require_once '../common/dbconnect.php';
require_once '../common/functions.php';
session_start();

$utype = 0;
if (isset($_GET['utype'])) {
    $utype = $_GET['utype'];
    $_SESSION['utype'] = $utype;
} elseif (isset($_SESSION['utype'])) {
    $utype = $_SESSION['utype'];
} else {
    echo "ユーザータイプ情報が無効です。";
    exit();
}

// ショップ名の取得
$shop_name = '';
if ($utype == 1024) {
    $shop_name = 'ソルシエール';
} elseif ($utype == 2) {
    $shop_name = 'レーヴェス';
} elseif ($utype == 3) {
    $shop_name = 'コレクト';
} else {
    exit();
}

echo "<!-- utype: $utype, shop_name: $shop_name -->";
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BASEデータ同期 - <?= htmlspecialchars($shop_name, ENT_QUOTES) ?>管理システム</title>
    <link href="https://use.fontawesome.com/releases/v5.6.1/css/all.css" rel="stylesheet">
    <link href="https://unpkg.com/sanitize.css" rel="stylesheet"/>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <h1><i class="fas fa-sync-alt"></i> BASEデータ同期</h1>
        
        <div style="background-color: #f8f9fa; padding: 20px; margin-bottom: 20px; border-radius: 8px;">
            <h2>デバッグ情報</h2>
            <p><strong>utype:</strong> <?= htmlspecialchars($utype) ?></p>
            <p><strong>shop_name:</strong> <?= htmlspecialchars($shop_name) ?></p>
            
            <?php
            echo "<!-- BASE API認証チェック開始 -->";
            
            try {
                echo "<!-- require_once api/base_api_client.php を試行中 -->";
                require_once 'api/base_api_client.php';
                echo "<!-- BaseApiClient クラス読み込み成功 -->";
                
                // 設定値の確認
                echo "<!-- 設定値確認開始 -->";
                global $base_client_id, $base_client_secret, $base_redirect_uri;
                echo "<!-- client_id: " . (isset($base_client_id) ? $base_client_id : '未設定') . " -->";
                echo "<!-- client_secret: " . (isset($base_client_secret) ? substr($base_client_secret, 0, 10) . '...' : '未設定') . " -->";
                echo "<!-- redirect_uri: " . (isset($base_redirect_uri) ? $base_redirect_uri : '未設定') . " -->";
                echo "<!-- 設定値確認終了 -->";
                
                $baseApi = new BaseApiClient();
                echo "<!-- BaseApiClient インスタンス作成成功 -->";
                
                $needsAuth = $baseApi->needsAuth();
                echo "<!-- needsAuth() 結果: " . ($needsAuth ? 'true' : 'false') . " -->";
                
                if ($needsAuth) {
                    echo "<!-- 認証が必要な場合の処理 -->";
                    echo '<div style="background-color: #fff3cd; padding: 15px; border-radius: 8px; margin: 20px 0;">';
                    echo '<h3><i class="fas fa-key"></i> BASE API認証が必要です</h3>';
                    echo '<p>BASE APIを使用するには認証が必要です。</p>';
                    
                    try {
                        echo "<!-- getAuthUrl() を試行中 -->";
                        $authUrl = $baseApi->getAuthUrl();
                        echo "<!-- 認証URL生成成功: " . $authUrl . " -->";
                        echo '<p><strong>認証URL:</strong> ' . htmlspecialchars($authUrl) . '</p>';
                        echo '<a href="' . htmlspecialchars($authUrl) . '" class="btn btn-primary" style="display: inline-block; padding: 10px 20px; background-color: #007bff; color: white; text-decoration: none; border-radius: 5px;">';
                        echo '<i class="fas fa-sign-in-alt"></i> BASE API認証を開始';
                        echo '</a>';
                    } catch (Exception $e) {
                        echo "<!-- getAuthUrl() エラー: " . $e->getMessage() . " -->";
                        echo '<p style="color: red;">認証URL生成エラー: ' . htmlspecialchars($e->getMessage()) . '</p>';
                        echo '<p style="color: red;">ファイル: ' . htmlspecialchars($e->getFile()) . '</p>';
                        echo '<p style="color: red;">行: ' . $e->getLine() . '</p>';
                    }
                    
                    echo '</div>';
                } else {
                    echo "<!-- 認証済みの場合の処理 -->";
                    echo '<div style="background-color: #d4edda; padding: 15px; border-radius: 8px; margin: 20px 0;">';
                    echo '<h3><i class="fas fa-check-circle"></i> BASE API認証済み</h3>';
                    echo '<p>BASE API認証が完了しています。</p>';
                    echo '</div>';
                }
                
            } catch (Exception $e) {
                echo "<!-- エラー発生: " . $e->getMessage() . " -->";
                echo '<div style="background-color: #f8d7da; padding: 15px; border-radius: 8px; margin: 20px 0;">';
                echo '<h3><i class="fas fa-exclamation-triangle"></i> エラー</h3>';
                echo '<p><strong>エラーメッセージ:</strong> ' . htmlspecialchars($e->getMessage()) . '</p>';
                echo '<p><strong>ファイル:</strong> ' . htmlspecialchars($e->getFile()) . '</p>';
                echo '<p><strong>行:</strong> ' . $e->getLine() . '</p>';
                echo '</div>';
            }
            
            echo "<!-- BASE API認証チェック終了 -->";
            ?>
        </div>
        
        <div class="control-buttons">
            <a href="index.php?utype=<?= htmlspecialchars($utype) ?>" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> メニューに戻る
            </a>
        </div>
    </div>
</body>
</html>