<?php
// 管理者用: キャストなりすましログインツール
// 指定したキャストIDとしてセッションを開始し、ダッシュボードへリダイレクトします。

require_once __DIR__ . '/../../../common/config.php';
require_once __DIR__ . '/../../../common/dbconnect.php';

session_start();

// 簡易認証（本来は管理者セッションチェックなど入れるべきですが、デバッグ用として）
// if (!isset($_SESSION['admin_login'])) { ... }

$cast_id = filter_input(INPUT_GET, 'cast_id', FILTER_VALIDATE_INT);

if (!$cast_id) {
    // キャストID入力フォームを表示
    ?>
    <!DOCTYPE html>
    <html>
    <head><meta charset="utf-8"><title>Login as Cast</title></head>
    <body style="padding:20px; font-family:sans-serif;">
        <h2>キャストなりすましログイン</h2>
        <form method="get">
            <label>Cast ID: <input type="number" name="cast_id" value="38"></label>
            <button type="submit">ダッシュボードを開く</button>
        </form>
    </body>
    </html>
    <?php
    exit;
}

try {
    $pdo = new PDO(
        "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
        $user,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // キャスト情報取得
    $stmt = $pdo->prepare("SELECT * FROM cast_mst WHERE cast_id = ?");
    $stmt->execute([$cast_id]);
    $cast = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$cast) {
        die("Error: Cast ID {$cast_id} not found.");
    }

    // セッション偽装
    $_SESSION['cast_id'] = $cast['cast_id'];
    $_SESSION['cast_name'] = $cast['cast_name'];
    $_SESSION['shop_id'] = $cast['shop_id']; // もし必要なら

    // ダッシュボードへリダイレクト
    // api/debug/login_as_cast.php -> api/cast/cast_dashboard.php
    header("Location: ../cast/cast_dashboard.php");
    exit;

} catch (PDOException $e) {
    die("DB Error: " . $e->getMessage());
}
