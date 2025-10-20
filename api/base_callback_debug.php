<?php
// BASE API OAuth認証コールバック処理
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

// エラーログを確認
echo "<!-- PHP Error Log: " . ini_get('error_log') . " -->\n";

// デバッグ情報（開発時のみ表示）
$debug_mode = isset($_GET['debug']) && $_GET['debug'] === '1';

// 一時的にデバッグモードを有効化（エラー調査用）
if (isset($_GET['force_debug']) && $_GET['force_debug'] === '1') {
    $debug_mode = true;
}

// 強制デバッグモード（500エラー調査用）
if (isset($_GET['error_debug']) && $_GET['error_debug'] === '1') {
    $debug_mode = true;
    echo "<!-- 強制デバッグモード有効 -->\n";
}

// HTMLヘッダーとスタイル
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BASE API 認証完了</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            margin: 0;
            padding: 20px;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            padding: 40px;
            max-width: 600px;
            width: 100%;
            text-align: center;
        }
        
        .success-icon {
            font-size: 4rem;
            color: #28a745;
            margin-bottom: 20px;
        }
        
        .title {
            font-size: 2rem;
            font-weight: bold;
            color: #2c3e50;
            margin-bottom: 20px;
        }
        
        .message {
            font-size: 1.1rem;
            color: #6c757d;
            margin-bottom: 30px;
            line-height: 1.6;
        }
        
        .btn {
            display: inline-block;
            padding: 12px 24px;
            margin: 8px;
            border: none;
            border-radius: 6px;
            text-decoration: none;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background-color: #007bff;
            color: white;
        }
        
        .btn-primary:hover {
            background-color: #0056b3;
            transform: translateY(-2px);
        }
        
        .btn-secondary {
            background-color: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background-color: #545b62;
            transform: translateY(-2px);
        }
        
        .debug-section {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
            text-align: left;
        }
        
        .debug-title {
            font-size: 1.2rem;
            font-weight: bold;
            color: #495057;
            margin-bottom: 15px;
        }
        
        .debug-content {
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            background-color: #e9ecef;
            padding: 10px;
            border-radius: 4px;
            overflow-x: auto;
        }
        
        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid #007bff;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-right: 10px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if ($debug_mode): ?>
            <div class="debug-section">
                <div class="debug-title">
                    <i class="fas fa-bug"></i> デバッグ情報
                </div>
                <div class="debug-content">
                    <strong>GET パラメーター:</strong><br>
                    <?= htmlspecialchars(print_r($_GET, true)) ?><br><br>
                    
                    <strong>POST パラメーター:</strong><br>
                    <?= htmlspecialchars(print_r($_POST, true)) ?><br><br>
                    
                    <strong>現在のディレクトリ:</strong> <?= htmlspecialchars(getcwd()) ?><br>
                    <strong>スクリプトのパス:</strong> <?= htmlspecialchars(__FILE__) ?>
                </div>
            </div>
        <?php endif; ?>

<?php
// PHP処理開始
try {
    // エラーハンドリングを強化
    set_error_handler(function($severity, $message, $file, $line) {
        throw new ErrorException($message, 0, $severity, $file, $line);
    });
    
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
                    // stateパラメータをデコードしてスコープを取得
                    try {
                        $state_data = json_decode(base64_decode($_GET['state']), true);
                        if (isset($state_data['scope'])) {
                            $scope_key = $state_data['scope'];
                            echo "デコードされたスコープ: " . htmlspecialchars($scope_key) . "<br>";
                        } else {
                            // 従来の形式（stateが直接スコープの場合）
                            $scope_key = $_GET['state'];
                            echo "従来形式のスコープ: " . htmlspecialchars($scope_key) . "<br>";
                        }
                    } catch (Exception $e) {
                        // デコードに失敗した場合は従来の形式として扱う
                        $scope_key = $_GET['state'];
                        echo "デコード失敗、従来形式として処理: " . htmlspecialchars($scope_key) . "<br>";
                    }
                    
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
        
        // stateパラメータからreturn_urlを復元
        if (isset($_GET['state'])) {
            try {
                $state_data = json_decode(base64_decode($_GET['state']), true);
                if (isset($state_data['return_url']) && !empty($state_data['return_url'])) {
                    $return_url = $state_data['return_url'];
                }
            } catch (Exception $e) {
                // stateの復元に失敗した場合はデフォルトを使用
                if ($debug_mode) {
                    echo "state復元エラー: " . $e->getMessage() . "<br>";
                }
            }
        }
        
        // return_urlパラメータが指定されている場合はそれを使用（従来の方法）
        if (isset($_GET['return_url'])) {
            $return_url = urldecode($_GET['return_url']);
        }
        
        // return_urlの検証と修正
        if (empty($return_url) || !filter_var($return_url, FILTER_VALIDATE_URL)) {
            // 無効なURLの場合はデフォルトに戻す
            $return_url = '../api/order_monitor.php';
        }
                
                
                if ($debug_mode) {
                    echo '<div class="debug-section">';
                    echo '<div class="debug-title"><i class="fas fa-check-circle"></i> 認証成功</div>';
                    echo '<div class="debug-content">';
                    echo '<strong>戻り先URL:</strong> ' . htmlspecialchars($return_url) . '<br>';
                    echo '<strong>URL検証:</strong> ' . (filter_var($return_url, FILTER_VALIDATE_URL) ? '有効' : '無効') . '<br>';
                    echo '<strong>スコープ:</strong> ' . htmlspecialchars($scope_key) . '<br>';
                    echo '<strong>アクセストークン:</strong> ' . htmlspecialchars(substr($token_data['access_token'], 0, 20)) . '...<br>';
                    echo '</div>';
                    echo '</div>';
                    
                    echo '<div class="message">認証が完了しました。以下のボタンで移動してください。</div>';
                    echo '<a href="' . htmlspecialchars($return_url) . '" class="btn btn-primary">';
                    echo '<i class="fas fa-arrow-left"></i> 元のページに戻る';
                    echo '</a>';
                    echo '<a href="../base_data_sync_top.php?utype=1024" class="btn btn-secondary">';
                    echo '<i class="fas fa-sync"></i> BASEデータ同期に戻る';
                    echo '</a>';
                } else {
                    // 本番環境では自動リダイレクト
                    echo '<div class="success-icon">';
                    echo '<i class="fas fa-check-circle"></i>';
                    echo '</div>';
                    echo '<div class="title">認証完了</div>';
                    echo '<div class="message">認証が完了しました。<br>元のページに戻ります...</div>';
                    echo '<div class="loading"></div><span>リダイレクト中...</span>';
                    echo '<script>setTimeout(function() { window.location.href = "' . addslashes($return_url) . '"; }, 2000);</script>';
                    echo '<div style="margin-top: 20px;">';
                    echo '<a href="' . htmlspecialchars($return_url) . '" class="btn btn-primary">';
                    echo '<i class="fas fa-arrow-left"></i> すぐに戻る';
                    echo '</a>';
                    echo '</div>';
                }
            } else {
                echo '<div class="success-icon" style="color: #dc3545;">';
                echo '<i class="fas fa-exclamation-triangle"></i>';
                echo '</div>';
                echo '<div class="title" style="color: #dc3545;">認証エラー</div>';
                echo '<div class="message">エラーが発生しました。</div>';
                echo '<div class="debug-section">';
                echo '<div class="debug-title"><i class="fas fa-bug"></i> エラー詳細</div>';
                echo '<div class="debug-content">' . htmlspecialchars($response) . '</div>';
                echo '</div>';
                echo '<a href="../api/order_monitor.php" class="btn btn-primary">';
                echo '<i class="fas fa-home"></i> 注文監視に戻る';
                echo '</a>';
            }
            
        } catch (Exception $e) {
            echo '<div class="success-icon" style="color: #dc3545;">';
            echo '<i class="fas fa-exclamation-triangle"></i>';
            echo '</div>';
            echo '<div class="title" style="color: #dc3545;">アクセストークン取得エラー</div>';
            echo '<div class="message">エラーが発生しました。</div>';
            echo '<div class="debug-section">';
            echo '<div class="debug-title"><i class="fas fa-bug"></i> エラー詳細</div>';
            echo '<div class="debug-content">' . htmlspecialchars($e->getMessage()) . '</div>';
            echo '</div>';
            echo '<a href="../api/order_monitor.php" class="btn btn-primary">';
            echo '<i class="fas fa-home"></i> 注文監視に戻る';
            echo '</a>';
        }
    } else {
        echo '<div class="success-icon" style="color: #ffc107;">';
        echo '<i class="fas fa-exclamation-triangle"></i>';
        echo '</div>';
        echo '<div class="title" style="color: #ffc107;">BASE API設定が不完全です</div>';
        echo '<div class="message">設定を確認してください。</div>';
        echo '<a href="../base_data_sync_top.php?utype=1024" class="btn btn-primary">';
        echo '<i class="fas fa-sync"></i> BASEデータ同期に戻る';
        echo '</a>';
    }
} elseif (isset($_GET['error'])) {
    echo '<div class="success-icon" style="color: #dc3545;">';
    echo '<i class="fas fa-exclamation-triangle"></i>';
    echo '</div>';
    echo '<div class="title" style="color: #dc3545;">認証エラー</div>';
    echo '<div class="message">エラーが発生しました。</div>';
    echo '<div class="debug-section">';
    echo '<div class="debug-title"><i class="fas fa-bug"></i> エラー詳細</div>';
    echo '<div class="debug-content">' . htmlspecialchars($_GET['error']) . '</div>';
    echo '</div>';
    echo '<a href="../api/order_monitor.php" class="btn btn-primary">';
    echo '<i class="fas fa-home"></i> 注文監視に戻る';
    echo '</a>';
} else {
    echo '<div class="success-icon" style="color: #ffc107;">';
    echo '<i class="fas fa-question-circle"></i>';
    echo '</div>';
    echo '<div class="title" style="color: #ffc107;">認証コードが取得できませんでした</div>';
    echo '<div class="message">認証プロセスに問題があります。</div>';
    echo '<a href="../api/order_monitor.php" class="btn btn-primary">';
    echo '<i class="fas fa-home"></i> 注文監視に戻る';
    echo '</a>';
}
?>
    </div>
</body>
</html>
<?php
} catch (Throwable $e) {
    // 全体のエラーハンドリング
    echo '<div class="success-icon" style="color: #dc3545;">';
    echo '<i class="fas fa-exclamation-triangle"></i>';
    echo '</div>';
    echo '<div class="title" style="color: #dc3545;">システムエラー</div>';
    echo '<div class="message">予期しないエラーが発生しました。</div>';
    echo '<div class="debug-section">';
    echo '<div class="debug-title"><i class="fas fa-bug"></i> エラー詳細</div>';
    echo '<div class="debug-content">';
    echo '<strong>エラー:</strong> ' . htmlspecialchars($e->getMessage()) . '<br>';
    echo '<strong>ファイル:</strong> ' . htmlspecialchars($e->getFile()) . '<br>';
    echo '<strong>行:</strong> ' . $e->getLine() . '<br>';
    echo '<strong>スタックトレース:</strong><br>';
    echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
    echo '</div>';
    echo '</div>';
    echo '<a href="../api/order_monitor.php" class="btn btn-primary">';
    echo '<i class="fas fa-home"></i> 注文監視に戻る';
    echo '</a>';
}
?>