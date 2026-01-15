<?php
/**
 * 管理者用: キャスト登録リンク生成ツール
 * admin_cast_manager.phpに追加する機能
 */
session_start();
require_once __DIR__ . '/../../../common/config.php';

$message = '';
$registration_links = [];

try {
    $pdo = new PDO(
        "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
        $user,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    // トークン生成処理
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_token'])) {
        $cast_id = $_POST['cast_id'] ?? 0;
        
        if ($cast_id) {
            // ワンタイムトークン生成（64文字のランダム文字列）
            $token = bin2hex(random_bytes(32));
            $expires_at = date('Y-m-d H:i:s', strtotime('+7 days')); // 7日間有効
            
            $stmt = $pdo->prepare("
                UPDATE cast_mst 
                SET registration_token = ?, 
                    token_expires_at = ?,
                    token_used_at = NULL
                WHERE cast_id = ?
            ");
            $stmt->execute([$token, $expires_at, $cast_id]);
            
            // 生成したリンク
            $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") 
                      . "://{$_SERVER['HTTP_HOST']}"
                      . dirname(dirname($_SERVER['SCRIPT_NAME']));
            $registration_url = $base_url . "/cast/self_register.php?token={$token}";
            
            $registration_links[$cast_id] = [
                'url' => $registration_url,
                'expires_at' => $expires_at
            ];
            
            $message = "登録リンクを生成しました。";
        }
    }
    
    // キャスト一覧取得
    $stmt = $pdo->query("
        SELECT 
            cast_id, 
            cast_name, 
            email, 
            login_enabled,
            registration_token,
            token_expires_at,
            token_used_at,
            last_login_at
        FROM cast_mst 
        WHERE drop_flg = 0 
        ORDER BY cast_id
    ");
    $casts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    die("DB Connection Error: " . $e->getMessage());
}

// 登録リンク生成関数
function generateRegistrationLink($cast_id, $token) {
    $base_url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") 
              . "://{$_SERVER['HTTP_HOST']}"
              . dirname(dirname($_SERVER['SCRIPT_NAME']));
    return $base_url . "/cast/self_register.php?token={$token}";
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>キャスト管理（セルフ登録対応）</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { padding: 20px; background-color: #f8f9fa; }
        .container { max-width: 1200px; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .table th { background-color: #f8f9fa; }
        .badge-enabled { background-color: #28a745; }
        .badge-disabled { background-color: #6c757d; }
        .badge-pending { background-color: #ffc107; }
        .registration-link { 
            background: #e7f3ff; 
            padding: 10px; 
            border-radius: 4px; 
            margin-top: 10px;
            font-size: 0.9em;
            word-break: break-all;
        }
        .copy-btn { margin-left: 10px; }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="mb-4"><i class="fas fa-users"></i> キャスト管理（セルフ登録対応）</h1>
        
        <?php if ($message): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle"></i> <?= htmlspecialchars($message) ?>
                <button type="button" class="btn close" data-bs-dismiss="alert">&times;</button>
            </div>
        <?php endif; ?>

        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <i class="fas fa-info-circle"></i> セルフ登録機能の使い方
            </div>
            <div class="card-body">
                <h5>📝 新しい登録フロー</h5>
                <ol>
                    <li><strong>管理者:</strong> キャスト名だけ登録（下の表で「登録リンク生成」ボタンをクリック）</li>
                    <li><strong>システム:</strong> ワンタイムリンクを自動生成</li>
                    <li><strong>管理者:</strong> 生成されたリンクをキャストに送る（LINEなど）</li>
                    <li><strong>キャスト:</strong> リンクを開いて自分でメール・パスワードを設定</li>
                    <li><strong>完了!</strong> キャストが即座にログイン可能に</li>
                </ol>
                <hr>
                <h5>✅ メリット</h5>
                <ul>
                    <li><i class="fas fa-lock text-success"></i> 管理者がパスワードを知らない（セキュリティ向上）</li>
                    <li><i class="fas fa-clock text-success"></i> 管理者の手間が大幅削減</li>
                    <li><i class="fas fa-user-check text-success"></i> キャストが好きなタイミングで設定可能</li>
                </ul>
                <a href="../order_monitor.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> モニターに戻る</a>
            </div>
        </div>

        <h3><i class="fas fa-list"></i> 登録済みキャスト</h3>
        <table class="table table-bordered table-hover">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>源氏名</th>
                    <th>メールアドレス</th>
                    <th>ログイン状態</th>
                    <th>最終ログイン</th>
                    <th>セルフ登録</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($casts as $cast): ?>
                <tr>
                    <td><?= $cast['cast_id'] ?></td>
                    <td><strong><?= htmlspecialchars($cast['cast_name']) ?></strong></td>
                    <td>
                        <?php if ($cast['email']): ?>
                            <i class="fas fa-envelope text-success"></i> <?= htmlspecialchars($cast['email']) ?>
                        <?php else: ?>
                            <span class="text-muted"><i class="fas fa-envelope-open text-secondary"></i> 未設定</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($cast['login_enabled']): ?>
                            <span class="badge bg-success"><i class="fas fa-check-circle"></i> 有効</span>
                        <?php elseif ($cast['registration_token'] && !$cast['token_used_at']): ?>
                            <span class="badge bg-warning text-dark"><i class="fas fa-hourglass-half"></i> 登録待ち</span>
                        <?php else: ?>
                            <span class="badge bg-secondary"><i class="fas fa-times-circle"></i> 無効</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?= $cast['last_login_at'] ? '<i class="fas fa-sign-in-alt text-info"></i> ' . date('Y/m/d H:i', strtotime($cast['last_login_at'])) : '-' ?>
                    </td>
                    <td>
                        <?php if (!$cast['email'] || !$cast['login_enabled']): ?>
                            <!-- 未登録または無効の場合: 登録リンク生成ボタン -->
                            <form method="post" style="display:inline;">
                                <input type="hidden" name="cast_id" value="<?= $cast['cast_id'] ?>">
                                <button type="submit" name="generate_token" class="btn btn-sm btn-primary">
                                    <i class="fas fa-link"></i> 登録リンク生成
                                </button>
                            </form>
                            
                            <?php if ($cast['registration_token'] && !$cast['token_used_at']): ?>
                                <?php 
                                    $link = generateRegistrationLink($cast['cast_id'], $cast['registration_token']);
                                    $is_expired = strtotime($cast['token_expires_at']) < time();
                                ?>
                                <?php if (!$is_expired): ?>
                                    <div class="registration-link mt-2">
                                        <small><strong>📎 登録リンク:</strong></small><br>
                                        <code id="link-<?= $cast['cast_id'] ?>"><?= htmlspecialchars($link) ?></code>
                                        <button class="btn btn-sm btn-outline-secondary copy-btn" onclick="copyLink(<?= $cast['cast_id'] ?>)">
                                            <i class="fas fa-copy"></i> コピー
                                        </button>
                                        <br>
                                        <small class="text-muted">
                                            <i class="fas fa-clock"></i> 有効期限: <?= date('Y/m/d H:i', strtotime($cast['token_expires_at'])) ?>
                                        </small>
                                    </div>
                                <?php else: ?>
                                    <small class="text-danger"><i class="fas fa-exclamation-triangle"></i> トークン期限切れ（再生成してください）</small>
                                <?php endif; ?>
                            <?php endif; ?>
                        <?php else: ?>
                            <!-- 既に登録済み -->
                            <span class="text-success"><i class="fas fa-check"></i> 登録完了</span>
                        <?php endif; ?>
                        
                        <?php if (isset($registration_links[$cast['cast_id']])): ?>
                            <!-- 今生成したリンクを表示 -->
                            <div class="registration-link mt-2 bg-success text-white">
                                <small><strong>✅ 新しい登録リンクを生成しました:</strong></small><br>
                                <code class="text-white" id="link-<?= $cast['cast_id'] ?>"><?= htmlspecialchars($registration_links[$cast['cast_id']]['url']) ?></code>
                                <button class="btn btn-sm btn-light copy-btn" onclick="copyLink(<?= $cast['cast_id'] ?>)">
                                    <i class="fas fa-copy"></i> コピー
                                </button>
                                <br>
                                <small>
                                    <i class="fas fa-clock"></i> 有効期限: <?= date('Y/m/d H:i', strtotime($registration_links[$cast['cast_id']]['expires_at'])) ?> (7日間)
                                </small>
                            </div>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function copyLink(castId) {
            const linkElement = document.getElementById('link-' + castId);
            const text = linkElement.textContent;
            
            navigator.clipboard.writeText(text).then(() => {
                alert('📋 リンクをクリップボードにコピーしました！\nLINEなどでキャストに送信してください。');
            }).catch(err => {
                // フォールバック: 選択してコピー
                const range = document.createRange();
                range.selectNode(linkElement);
                window.getSelection().removeAllRanges();
                window.getSelection().addRange(range);
                document.execCommand('copy');
                alert('📋 リンクをクリップボードにコピーしました！');
            });
        }
    </script>
</body>
</html>
