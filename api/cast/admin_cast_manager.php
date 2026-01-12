<?php
/**
 * キャストアカウント管理 (管理者用)
 */
session_start();
require_once __DIR__ . '/../../../common/config.php';
require_once __DIR__ . '/../../../common/dbconnect.php';

// ★簡易認証 (本来は管理者ログインが必要だが、今回は簡易実装)
// 必要ならここに管理者チェックを入れる

$message = '';

try {
    $pdo = new PDO(
        "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
        $user,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // 追加処理
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
        $username = $_POST['username'] ?? '';
        $password_raw = $_POST['password'] ?? '';
        $display_name = $_POST['display_name'] ?? '';

        if ($username && $password_raw && $display_name) {
            $hash = password_hash($password_raw, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO casts (username, password_hash, display_name) VALUES (?, ?, ?)");
            try {
                $stmt->execute([$username, $hash, $display_name]);
                $message = "キャスト「{$display_name}」を追加しました。";
            } catch (PDOException $e) {
                $message = "エラー: " . $e->getMessage();
            }
        } else {
            $message = "全項目を入力してください。";
        }
    }

    // 削除処理
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
        $id = $_POST['id'] ?? 0;
        if ($id) {
            $stmt = $pdo->prepare("DELETE FROM casts WHERE id = ?");
            $stmt->execute([$id]);
            $message = "ID {$id} を削除しました。";
        }
    }

    // リスト取得
    $stmt = $pdo->query("SELECT * FROM casts ORDER BY created_at DESC");
    $casts = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    die("DB Connection Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>キャスト管理 - BASE API Monitor</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { padding: 20px; background-color: #f8f9fa; }
        .container { max-width: 800px; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .table th { background-color: #f8f9fa; }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="mb-4">キャストアカウント管理</h1>
        
        <?php if ($message): ?>
            <div class="alert alert-info"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <div class="card mb-4">
            <div class="card-header">新規登録</div>
            <div class="card-body">
                <form method="post">
                    <input type="hidden" name="action" value="add">
                    <div class="mb-3">
                        <label class="form-label">ログインIID (Username)</label>
                        <input type="text" name="username" class="form-control" required placeholder="例: cast_hanako">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">パスワード</label>
                        <input type="text" name="password" class="form-control" required placeholder="初期パスワード">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">表示名 (Display Name)</label>
                        <input type="text" name="display_name" class="form-control" required placeholder="例: 花子 (BASEのオプション値と一致させる)">
                        <div class="form-text">これが注文データの「キャスト名」と照合されます。</div>
                    </div>
                    <button type="submit" class="btn btn-primary">登録</button>
                    <a href="../order_monitor.php" class="btn btn-secondary ms-2">モニターに戻る</a>
                </form>
            </div>
        </div>

        <h3>登録済みキャスト</h3>
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>ログインID</th>
                    <th>表示名</th>
                    <th>登録日</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($casts as $cast): ?>
                <tr>
                    <td><?= $cast['id'] ?></td>
                    <td><?= htmlspecialchars($cast['username']) ?></td>
                    <td>
                        <?= htmlspecialchars($cast['display_name']) ?>
                        <a href="cast_dashboard.php?test_cast=<?= urlencode($cast['display_name']) ?>" target="_blank" class="btn btn-xs btn-outline-info ms-2" style="font-size:0.75rem">Dashboard Debug</a>
                    </td>
                    <td><?= $cast['created_at'] ?></td>
                    <td>
                        <form method="post" style="display:inline" onsubmit="return confirm('本当に削除しますか？');">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $cast['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-danger">削除</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
