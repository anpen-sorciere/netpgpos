<?php
// BASE API OAuth認証コールバック処理（パス修正版）
error_reporting(E_ALL);
ini_set('display_errors', 1);

// デバッグ情報（開発時のみ表示）
$debug_mode = isset($_GET['debug']) && $_GET['debug'] === '1';

if ($debug_mode) {
    echo "<h1>BASE API コールバック デバッグ</h1>";
    
    echo "<h2>1. 基本情報</h2>";
    echo "GET パラメーター: " . print_r($_GET, true) . "<br>";
    echo "POST パラメーター: " . print_r($_POST, true) . "<br>";
    
    echo "<h2>2. ファイル存在確認</h2>";
    echo "../common/config.php: " . (file_exists('../common/config.php') ? '存在' : '不存在') . "<br>";
    echo "../../common/config.php: " . (file_exists('../../common/config.php') ? '存在' : '不存在') . "<br>";
    echo "../common/dbconnect.php: " . (file_exists('../common/dbconnect.php') ? '存在' : '不存在') . "<br>";
    echo "../../common/dbconnect.php: " . (file_exists('../../common/dbconnect.php') ? '存在' : '不存在') . "<br>";
    
    echo "<h2>3. ディレクトリ構造確認</h2>";
    echo "現在のディレクトリ: " . getcwd() . "<br>";
    echo "スクリプトのパス: " . __FILE__ . "<br>";
    
    echo "<h2>4. config.php読み込みテスト</h2>";
}

try {
    // 複数のパスを試行
    $config_paths = [
        '../config.php',
        '../common/config.php',
        '../../common/config.php',
        '/home/purplelion51/www/common/config.php'
    ];
    
    $config_loaded = false;
    foreach ($config_paths as $path) {
        if (file_exists($path)) {
            if ($debug_mode) echo "config.php発見: " . $path . "<br>";
            require_once $path;
            $config_loaded = true;
            break;
        }
    }
    
    if (!$config_loaded) {
        if ($debug_mode) echo "config.phpが見つかりません<br>";
    } else {
        if ($debug_mode) {
            echo "config.php読み込み: 成功<br>";
            echo "base_client_id: " . (isset($base_client_id) ? $base_client_id : '未設定') . "<br>";
            echo "base_client_secret: " . (isset($base_client_secret) ? substr($base_client_secret, 0, 10) . '...' : '未設定') . "<br>";
            echo "base_redirect_uri: " . (isset($base_redirect_uri) ? $base_redirect_uri : '未設定') . "<br>";
        }
    }
} catch (Exception $e) {
    if ($debug_mode) echo "config.php読み込みエラー: " . $e->getMessage() . "<br>";
}

if ($debug_mode) {
    echo "<h2>5. セッション開始テスト</h2>";
}

try {
    session_start();
    if ($debug_mode) echo "セッション開始: 成功<br>";
} catch (Exception $e) {
    if ($debug_mode) echo "セッション開始エラー: " . $e->getMessage() . "<br>";
}

if ($debug_mode) {
    echo "<h2>6. 認証コード処理</h2>";
    echo "GET パラメーター詳細: " . print_r($_GET, true) . "<br>";
    echo "state パラメーター: " . (isset($_GET['state']) ? htmlspecialchars($_GET['state']) : '未設定') . "<br>";
}

if (isset($_GET['code'])) {
    $auth_code = $_GET['code'];
    echo "認証コード: " . htmlspecialchars($auth_code) . "<br>";
    
    if (isset($base_client_id) && isset($base_client_secret) && isset($base_redirect_uri)) {
        echo "<h3>アクセストークン取得テスト</h3>";
        try {
            $url = 'https://api.thebase.in/1/oauth/token';
            
            $post_data = [
                'grant_type' => 'authorization_code',
                'client_id' => $base_client_id,
                'client_secret' => $base_client_secret,
                'redirect_uri' => $base_redirect_uri,
                'code' => $auth_code
            ];
            
            echo "POST データ: " . print_r($post_data, true) . "<br>";
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            echo "HTTP コード: " . $http_code . "<br>";
            echo "レスポンス: " . htmlspecialchars($response) . "<br>";
            
            if ($http_code === 200) {
                $token_data = json_decode($response, true);
                echo "トークンデータ: " . print_r($token_data, true) . "<br>";
                
                // セッションに保存
                $_SESSION['base_access_token'] = $token_data['access_token'];
                $_SESSION['base_refresh_token'] = $token_data['refresh_token'] ?? '';
                $_SESSION['base_token_expires'] = time() + ($token_data['expires_in'] ?? 3600);
                
                // スコープ情報を保存（stateパラメータから）
                echo "<h4>スコープ情報処理:</h4>";
                echo "state パラメーター: " . (isset($_GET['state']) ? htmlspecialchars($_GET['state']) : '未設定') . "<br>";
                
                if (isset($_GET['state'])) {
                    $scope_key = $_GET['state'];
                    $_SESSION['base_current_scope'] = $scope_key;
                    echo "処理するスコープキー: " . htmlspecialchars($scope_key) . "<br>";
                    
                // スコープ別のトークンも保存（新しいシステム）
                require_once __DIR__ . '/base_practical_auto_manager.php';
                
                // データベース接続情報の確認
                echo "<h4>データベース接続情報確認:</h4>";
                echo "host: " . (isset($host) ? $host : '未設定') . "<br>";
                echo "user: " . (isset($user) ? $user : '未設定') . "<br>";
                echo "dbname: " . (isset($dbname) ? $dbname : '未設定') . "<br>";
                
                try {
                    $practical_manager = new BasePracticalAutoManager();
                    $practical_manager->saveScopeToken(
                        $scope_key,
                        $token_data['access_token'],
                        $token_data['refresh_token'] ?? '',
                        $token_data['expires_in'] ?? 3600
                    );
                    
                    echo "✅ データベースに保存成功: " . $scope_key . "<br>";
                } catch (Exception $e) {
                    echo "❌ データベース保存エラー: " . $e->getMessage() . "<br>";
                }
                }
                
                echo "保存されたリフレッシュトークン: " . (isset($token_data['refresh_token']) ? 'あり' : 'なし') . "<br>";
                echo "トークン有効期限: " . ($token_data['expires_in'] ?? '不明') . "秒<br>";
                
                // 動的な戻り先を設定
                $return_url = '../base_data_sync_top.php?utype=1024'; // デフォルト
                
                // return_urlパラメータが指定されている場合はそれを使用
                if (isset($_GET['return_url'])) {
                    $return_url = urldecode($_GET['return_url']);
                }
                
                if ($debug_mode) {
                    echo "<h3>認証成功！</h3>";
                    echo "戻り先URL: " . htmlspecialchars($return_url) . "<br>";
                    echo '<a href="' . htmlspecialchars($return_url) . '">元のページに戻る</a><br>';
                    echo '<a href="../base_data_sync_top.php?utype=1024">BASEデータ同期に戻る</a>';
                } else {
                    // 本番環境では自動リダイレクト
                    echo "<h2>認証完了</h2>";
                    echo "<p>認証が完了しました。元のページに戻ります...</p>";
                    echo '<script>setTimeout(function() { window.location.href = "' . htmlspecialchars($return_url) . '"; }, 2000);</script>';
                    echo '<p><a href="' . htmlspecialchars($return_url) . '">すぐに戻る</a></p>';
                }
            } else {
                echo "<h3>認証エラー</h3>";
                echo "エラー: " . htmlspecialchars($response) . "<br>";
            }
            
        } catch (Exception $e) {
            echo "アクセストークン取得エラー: " . $e->getMessage() . "<br>";
        }
    } else {
        echo "BASE API設定が不完全です<br>";
    }
} elseif (isset($_GET['error'])) {
    echo "認証エラー: " . htmlspecialchars($_GET['error']) . "<br>";
} else {
    echo "認証コードが取得できませんでした。<br>";
}
?>