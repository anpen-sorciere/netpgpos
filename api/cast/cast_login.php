<?php
session_start();
require_once __DIR__ . '/../../../common/config.php';
require_once __DIR__ . '/../../../common/dbconnect.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if ($username && $password) {
        try {
            $pdo = new PDO(
                "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
                $user,
                $password_db ?? $password, // config.phpの変数名注意 ($passwordがフォーム入力と被る)
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            // config.phpのパスワード変数が $password の場合、上記で上書きされている恐れがある。
            // しかし require_once は関数スコープでないため、$password はグローバル。
            // POSTの $password で上書きされる。
            // これを防ぐため、requireの前に退避するか、変数名を変えるか...
            // 一般的に config.php は $password = 'dbpass' としていることが多い。
            // 修正が必要。
        } catch (Exception $e) {
            // DB接続前なのでここで死ぬかも
        }
    }
}
?>
<?php
// スコープ汚染回避のため、ロジックを分離
$db_user = '';
$db_pass = '';
$db_host = '';
$db_name = '';

// Config読み込み (スコープ隔離)
call_user_func(function() use (&$db_user, &$db_pass, &$db_host, &$db_name) {
    require __DIR__ . '/../../../common/config.php';
    $db_user = $user ?? '';
    $db_pass = $password ?? '';
    $db_host = $host ?? '';
    $db_name = $dbname ?? '';
});

// ログイン処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $login_pass = $_POST['password'] ?? '';

    if ($username && $login_pass) {
        try {
            $pdo = new PDO(
                "mysql:host={$db_host};dbname={$db_name};charset=utf8mb4",
                $db_user,
                $db_pass,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );

            $stmt = $pdo->prepare("SELECT * FROM casts WHERE username = ? AND is_active = 1");
            $stmt->execute([$username]);
            $cast = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($cast && password_verify($login_pass, $cast['password_hash'])) {
                // ログイン成功
                $_SESSION['cast_id'] = $cast['id'];
                $_SESSION['cast_name'] = $cast['display_name'];
                $_SESSION['cast_username'] = $cast['username'];
                
                header('Location: cast_dashboard.php');
                exit;
            } else {
                $error = 'IDまたはパスワードが間違っています。';
            }

        } catch (PDOException $e) {
            $error = 'システムエラー: DB接続失敗';
        }
    } else {
        $error = 'IDとパスワードを入力してください。';
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>キャストログイン</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #fce4ec; display: flex; align-items: center; justify-content: center; height: 100vh; }
        .login-card { width: 100%; max-width: 400px; padding: 30px; background: white; border-radius: 15px; box-shadow: 0 4px 15px rgba(233, 30, 99, 0.2); }
        .btn-pink { background-color: #e91e63; color: white; border: none; }
        .btn-pink:hover { background-color: #d81b60; color: white; }
    </style>
</head>
<body>
    <div class="login-card">
        <h3 class="text-center mb-4" style="color: #e91e63;">Cast Login</h3>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <form method="post">
            <div class="mb-3">
                <label class="form-label">ログインID</label>
                <input type="text" name="username" class="form-control" required>
            </div>
            <div class="mb-3">
                <label class="form-label">パスワード</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <button type="submit" class="btn btn-pink w-100 py-2">ログイン</button>
        </form>
    </div>
</body>
</html>
