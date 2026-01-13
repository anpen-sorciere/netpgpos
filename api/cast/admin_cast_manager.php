<?php
/**
 * キャストアカウント管理 (管理者用)
 * cast_mstテーブルのログイン情報を管理
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

    // 追加/更新処理
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save') {
        $cast_id = $_POST['cast_id'] ?? 0;
        $email = $_POST['email'] ?? '';
        $password_raw = $_POST['password'] ?? '';
        $login_enabled = isset($_POST['login_enabled']) ? 1 : 0;

        if ($cast_id && $email) {
            // パスワードが入力されている場合のみ更新
            if ($password_raw) {
                $hash = password_hash($password_raw, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE cast_mst SET email = ?, password = ?, login_enabled = ? WHERE cast_id = ?");
                $stmt->execute([$email, $hash, $login_enabled, $cast_id]);
                $message = "キャスト ID:{$cast_id} のログイン情報を更新しました。";
            } else {
                // パスワードなしの場合はemailとlogin_enabledのみ更新
                $stmt = $pdo->prepare("UPDATE cast_mst SET email = ?, login_enabled = ? WHERE cast_id = ?");
                $stmt->execute([$email, $login_enabled, $cast_id]);
                $message = "キャスト ID:{$cast_id} のログイン情報を更新しました。（パスワードは変更なし）";
            }
        } else {
            $message = "キャストIDとメールアドレスを入力してください。";
        }
    }

    // ログイン無効化処理
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'disable') {
        $cast_id = $_POST['cast_id'] ?? 0;
        if ($cast_id) {
            $stmt = $pdo->prepare("UPDATE cast_mst SET login_enabled = 0 WHERE cast_id = ?");
            $stmt->execute([$cast_id]);
            $message = "ID {$cast_id} のログインを無効化しました。";
        }
    }

    // リスト取得（在籍中のキャストのみ表示）
    $stmt = $pdo->query("SELECT * FROM cast_mst WHERE drop_flg = 0 ORDER BY cast_id");
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
        .container { max-width: 1000px; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .table th { background-color: #f8f9fa; }
        .badge-enabled { background-color: #28a745; }
        .badge-disabled { background-color: #6c757d; }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="mb-4">キャストログイン管理</h1>
        
        <?php if ($message): ?>
            <div class="alert alert-info"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>

        <div class="card mb-4">
            <div class="card-header">使い方</div>
            <div class="card-body">
                <ul>
                    <li>既存のキャスト（cast_mst）にログイン機能を追加します。</li>
                    <li>メールアドレスとパスワードを設定し、「ログイン有効」にチェックを入れて保存してください。</li>
                    <li>キャストは設定したメールアドレスでログインできるようになります。</li>
                </ul>
                <a href="../order_monitor.php" class="btn btn-secondary btn-sm">モニターに戻る</a>
            </div>
        </div>

        <h3>登録済みキャスト</h3>
        <table class="table table-bordered table-hover">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>源氏名</th>
                    <th>メールアドレス</th>
                    <th>ログイン</th>
                    <th>最終ログイン</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($casts as $cast): ?>
                <tr>
                    <td><?= $cast['cast_id'] ?></td>
                    <td><?= htmlspecialchars($cast['cast_name']) ?></td>
                    <td>
                        <form method="post" class="d-inline">
                            <input type="hidden" name="action" value="save">
                            <input type="hidden" name="cast_id" value="<?= $cast['cast_id'] ?>">
                            <input type="email" name="email" class="form-control form-control-sm d-inline" style="width:200px" 
                                   value="<?= htmlspecialchars($cast['email'] ?? '') ?>" placeholder="メールアドレス" required>
                    </td>
                    <td>
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="login_enabled" 
                                   <?= $cast['login_enabled'] ? 'checked' : '' ?>>
                            <label class="form-check-label">
                                <span class="badge <?= $cast['login_enabled'] ? 'badge-enabled' : 'badge-disabled' ?>">
                                    <?= $cast['login_enabled'] ? '有効' : '無効' ?>
                                </span>
                            </label>
                        </div>
                    </td>
                    <td><?= $cast['last_login_at'] ? date('Y/m/d H:i', strtotime($cast['last_login_at'])) : '-' ?></td>
                    <td>
                        <input type="password" name="password" class="form-control form-control-sm mb-1" 
                               placeholder="新しいパスワード（変更時のみ）" style="width:150px">
                        <button type="submit" class="btn btn-sm btn-primary">保存</button>
                        </form>
                        <?php if ($cast['login_enabled']): ?>
                        <form method="post" class="d-inline ms-1" onsubmit="return confirm('ログインを無効化しますか？');">
                            <input type="hidden" name="action" value="disable">
                            <input type="hidden" name="cast_id" value="<?= $cast['cast_id'] ?>">
                            <button type="submit" class="btn btn-sm btn-warning">無効化</button>
                        </form>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
