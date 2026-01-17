<?php
session_start();
// DB接続準備
require_once __DIR__ . '/../../../common/config.php';
require_once __DIR__ . '/../../../common/dbconnect.php';

// 自動ログイン情報クリア
if (isset($_SESSION['cast_id'])) {
    try {
        $pdo = new PDO(
            "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
            $user,
            $password,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        // DB上のトークンを削除
        $stmt = $pdo->prepare("UPDATE cast_mst SET remember_token = NULL, remember_expires = NULL WHERE cast_id = ?");
        $stmt->execute([$_SESSION['cast_id']]);
    } catch (Exception $e) { /* ignore */ }
}

// Cookie削除
if (isset($_COOKIE['cast_remember_token'])) {
    $cookie_path = dirname($_SERVER['SCRIPT_NAME']);
    // path指定を合わせないと消えないことがあるため注意
    // ログイン時と同じパスを指定する
    $secure = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on');
    setcookie('cast_remember_token', '', time() - 3600, $cookie_path, '', $secure, true);
}

$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();
header('Location: cast_login.php');
exit;
?>
