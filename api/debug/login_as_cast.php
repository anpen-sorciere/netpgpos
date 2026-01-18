<?php
// 管理者用: キャストなりすましログインツール
// 指定したキャストIDとしてセッションを開始し、ダッシュボードへリダイレクトします。

ini_set('display_errors', 1);
error_reporting(E_ALL);

// パス解決（複数パターン対応）
$config_paths = [
    __DIR__ . '/../../../common/config.php',
    __DIR__ . '/../../common/config.php',
];
$config_found = false;
foreach ($config_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $config_found = true;
        break;
    }
}
if (!$config_found) {
    die("Error: config.php not found. Searched: " . implode(", ", $config_paths));
}

// dbconnect.php
$db_paths = [
    __DIR__ . '/../../../common/dbconnect.php',
    __DIR__ . '/../../common/dbconnect.php',
];
foreach ($db_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        break;
    }
}

session_start();

// 簡易認証（本来は管理者セッションチェックなど入れるべきですが、デバッグ用として）
// if (!isset($_SESSION['admin_login'])) { ... }

// DB接続
try {
    $pdo = new PDO(
        "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
        $user,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    die("DB Error: " . $e->getMessage());
}

$cast_id = filter_input(INPUT_GET, 'cast_id', FILTER_VALIDATE_INT);

if (!$cast_id) {
    // キャスト一覧を取得（drop_flg=0のみ）
    $stmt = $pdo->query("SELECT cast_id, cast_name, cast_type FROM cast_mst WHERE drop_flg = 0 ORDER BY cast_type ASC, cast_yomi ASC");
    $casts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Login as Cast</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    </head>
    <body class="bg-light">
        <div class="container py-4">
            <div class="card shadow-sm">
                <div class="card-header bg-dark text-white">
                    <h5 class="m-0"><i class="fas fa-user-secret"></i> キャストなりすましログイン</h5>
                </div>
                <div class="card-body">
                    <!-- 方法1: プルダウン選択 -->
                    <form method="get">
                        <div class="mb-3">
                            <label class="form-label fw-bold">方法1: 名前から選択</label>
                            <select name="cast_id" class="form-select" onchange="if(this.value) this.form.submit();">
                                <option value="">-- キャストを選択 --</option>
                                <?php foreach ($casts as $c): ?>
                                    <option value="<?= $c['cast_id'] ?>">
                                        <?= htmlspecialchars($c['cast_name']) ?> (ID: <?= $c['cast_id'] ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </form>
                    
                    <hr>
                    
                    <!-- 方法2: ID直接入力 -->
                    <form method="get">
                        <div class="mb-3">
                            <label class="form-label fw-bold">方法2: IDを直接入力</label>
                            <div class="input-group">
                                <input type="number" name="cast_id" class="form-control" placeholder="Cast ID">
                                <button type="submit" class="btn btn-primary">ログイン</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            
            <div class="mt-3">
                <a href="../../index.php" class="btn btn-outline-secondary btn-sm">&larr; メインメニューに戻る</a>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

try {
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
