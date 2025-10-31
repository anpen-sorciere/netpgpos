<?php
// BASE OAuth Callback (robust, production-safe)
error_reporting(E_ALL);
ini_set('display_errors', 0);

// Setup app logs
$logBase = __DIR__ . '/../logs';
@mkdir($logBase, 0775, true);
ini_set('error_log', $logBase . '/php_errors.log');
$cbLog = $logBase . '/callback.log';
function cb_log(string $msg, array $ctx = []): void {
    global $cbLog;
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg;
    if ($ctx) { $line .= ' | ' . json_encode($ctx, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); }
    $line .= "\n";
    @file_put_contents($cbLog, $line, FILE_APPEND);
}

// Send safe error page (no details to user)
function send_error_and_exit(string $title = '認証エラー', string $message = '予期しないエラーが発生しました。') {
    http_response_code(500);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!DOCTYPE html><html lang="ja"><head><meta charset="UTF-8"><title>BASE認証</title>';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
    echo '<style>body{font-family:Segoe UI,Arial;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;background:#f8f9fa}';
    echo '.card{background:#fff;border:1px solid #dee2e6;border-radius:8px;max-width:560px;width:90%;padding:24px;text-align:center;box-shadow:0 10px 30px rgba(0,0,0,.08)}';
    echo '.title{font-size:20px;font-weight:700;color:#dc3545;margin-bottom:8px}.msg{color:#6c757d;margin-bottom:16px}';
    echo '.btn{display:inline-block;background:#007bff;color:#fff;text-decoration:none;padding:10px 18px;border-radius:6px}';
    echo '</style></head><body><div class="card">';
    echo '<div class="title">' . htmlspecialchars($title) . '</div>'; 
    echo '<div class="msg">' . htmlspecialchars($message) . '</div>';
    echo '<a class="btn" href="../api/order_monitor.php">注文監視に戻る</a>';
    echo '</div></body></html>';
    exit;
}

try {
    // Load config
    $config = __DIR__ . '/../../common/config.php';
    if (!file_exists($config)) {
        cb_log('CONFIG_NOT_FOUND', ['path' => $config]);
        send_error_and_exit('設定エラー', '設定ファイルが見つかりません。');
    }
    require_once $config;
    require_once __DIR__ . '/../../common/dbconnect.php';

    // Basic validation
    if (!isset($_GET['code'])) {
        cb_log('MISSING_CODE', ['GET' => $_GET]);
        send_error_and_exit('認証コードなし', '認証コードが取得できませんでした。');
    }
    $code = (string)$_GET['code'];

    // Decode state
    $scopeKey = null;
    $returnUrl = '../api/order_monitor.php';
    $rawState = $_GET['state'] ?? null;
    if ($rawState) {
        try {
            $decoded = json_decode(base64_decode($rawState), true);
            if (is_array($decoded)) {
                $scopeKey = $decoded['scope'] ?? null;
                $returnUrl = $decoded['return_url'] ?? $returnUrl;
            } else {
                // legacy: state holds scope directly
                $scopeKey = $rawState;
            }
        } catch (Throwable $e) {
            $scopeKey = $rawState; // fallback legacy
            cb_log('STATE_DECODE_FAIL', ['error' => $e->getMessage(), 'raw' => $rawState]);
        }
    }
    if (!filter_var($returnUrl, FILTER_VALIDATE_URL)) {
        $returnUrl = 'https://purplelion51.sakura.ne.jp/netpgpos/api/order_monitor.php';
    }

    // Token exchange
    if (empty($base_client_id) || empty($base_client_secret) || empty($base_redirect_uri)) {
        cb_log('CONFIG_MISSING_KEYS');
        send_error_and_exit('設定エラー', 'クライアント設定が不完全です。');
    }

    $tokenUrl = 'https://api.thebase.in/1/oauth/token';
    $post = [
        'grant_type' => 'authorization_code',
        'client_id' => $base_client_id,
        'client_secret' => $base_client_secret,
        'redirect_uri' => $base_redirect_uri,
        'code' => $code,
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $tokenUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    $resp = curl_exec($ch);
    $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $cerr = curl_error($ch);
    curl_close($ch);
    cb_log('TOKEN_RESPONSE', ['http' => $http, 'curl_error' => $cerr ?: null, 'snippet' => substr((string)$resp, 0, 200)]);

    if ($http !== 200) {
        send_error_and_exit('トークン取得失敗', 'BASEからのトークン取得に失敗しました。');
    }

    $token = json_decode((string)$resp, true);
    if (!is_array($token) || empty($token['access_token'])) {
        cb_log('TOKEN_PARSE_FAIL', ['resp' => $resp]);
        send_error_and_exit('トークン解析失敗', 'トークン応答の解析に失敗しました。');
    }

    // Session save
    if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
    $_SESSION['base_access_token'] = $token['access_token'];
    $_SESSION['base_refresh_token'] = $token['refresh_token'] ?? '';
    $_SESSION['base_token_expires'] = time() + (int)($token['expires_in'] ?? 3600);
    if ($scopeKey) { $_SESSION['base_current_scope'] = $scopeKey; }

    // Persist by scope
    try {
        require_once __DIR__ . '/base_practical_auto_manager.php';
        $mgr = new BasePracticalAutoManager();
        if ($scopeKey) {
            $mgr->saveScopeToken(
                $scopeKey,
                $token['access_token'],
                $token['refresh_token'] ?? '',
                (int)($token['expires_in'] ?? 3600)
            );
            cb_log('DB_SAVE_OK', ['scope' => $scopeKey]);
        } else {
            cb_log('DB_SAVE_SKIPPED_NO_SCOPE');
        }
    } catch (Throwable $e) {
        cb_log('DB_SAVE_ERROR', ['error' => $e->getMessage()]);
        // 続行（ユーザーは遷移できるようにする）
    }

    // Redirect back
    cb_log('REDIRECT', ['to' => $returnUrl]);
    header('Location: ' . $returnUrl, true, 302);
    exit;

} catch (Throwable $e) {
    cb_log('CALLBACK_FATAL', ['error' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()]);
    send_error_and_exit('システムエラー', '処理中にエラーが発生しました。');
}