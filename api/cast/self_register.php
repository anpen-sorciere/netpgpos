<?php
/**
 * キャストセルフ登録画面
 * ワンタイムリンクで開き、メール・パスワードを自分で設定
 */
session_start();
require_once __DIR__ . '/../../../common/config.php';

$error = '';
$success = '';
$cast = null;
$token = $_GET['token'] ?? '';

try {
    $pdo = new PDO(
        "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
        $user,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    // トークン検証
    if ($token) {
        $stmt = $pdo->prepare("
            SELECT * FROM cast_mst 
            WHERE registration_token = ? 
            AND drop_flg = 0
        ");
        $stmt->execute([$token]);
        $cast = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$cast) {
            $error = '無効な登録リンクです。管理者に新しいリンクを発行してもらってください。';
        } elseif ($cast['token_used_at']) {
            $error = 'このリンクは既に使用済みです。ログイン画面からログインしてください。';
        } elseif (strtotime($cast['token_expires_at']) < time()) {
            $error = 'このリンクの有効期限が切れています。管理者に新しいリンクを発行してもらってください。';
        }
    } else {
        $error = 'トークンが指定されていません。';
    }
    
    // 登録処理
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $cast && !$error) {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $password_confirm = $_POST['password_confirm'] ?? '';
        
        // バリデーション
        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = '有効なメールアドレスを入力してください。';
        } elseif (!$password || strlen($password) < 8) {
            $error = 'パスワードは8文字以上で入力してください。';
        } elseif ($password !== $password_confirm) {
            $error = 'パスワードが一致しません。';
        } else {
            // 既存のメールアドレスチェック
            $stmt = $pdo->prepare("SELECT cast_id FROM cast_mst WHERE email = ? AND cast_id != ?");
            $stmt->execute([$email, $cast['cast_id']]);
            if ($stmt->fetch()) {
                $error = 'このメールアドレスは既に使用されています。';
            } else {
                // 登録処理
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("
                    UPDATE cast_mst 
                    SET 
                        email = ?,
                        password = ?,
                        login_enabled = 1,
                        token_used_at = NOW(),
                        registration_token = NULL
                    WHERE cast_id = ?
                ");
                $stmt->execute([$email, $password_hash, $cast['cast_id']]);
                
                $success = true;
            }
        }
    }
    
} catch (PDOException $e) {
    $error = 'システムエラーが発生しました。管理者に連絡してください。';
    error_log("Self Registration Error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>キャストポータル - 登録</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Arial', sans-serif;
        }
        .registration-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            padding: 40px;
            max-width: 500px;
            width: 100%;
        }
        .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        .logo i {
            font-size: 60px;
            color: #667eea;
        }
        .logo h1 {
            margin-top: 15px;
            color: #333;
            font-size: 28px;
            font-weight: bold;
        }
        .success-icon {
            font-size: 80px;
            color: #28a745;
            text-align: center;
            margin-bottom: 20px;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 12px;
            font-size: 16px;
            font-weight: bold;
        }
        .btn-primary:hover {
            opacity: 0.9;
        }
        .form-label {
            font-weight: 600;
            color: #555;
        }
        .requirement {
            font-size: 0.85em;
            color: #666;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="registration-card">
        <?php if ($success): ?>
            <!-- 登録完了画面 -->
            <div class="success-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <h2 class="text-center text-success mb-4">登録完了！</h2>
            <p class="text-center">
                <strong><?= htmlspecialchars($cast['cast_name']) ?></strong> さん、<br>
                ようこそキャストポータルへ！
            </p>
            <p class="text-center text-muted">
                登録したメールアドレスとパスワードで<br>
                ログインできます。
            </p>
            <div class="d-grid gap-2 mt-4">
                <a href="cast_login.php" class="btn btn-primary btn-lg">
                    <i class="fas fa-sign-in-alt"></i> ログイン画面へ
                </a>
            </div>
        <?php elseif ($error): ?>
            <!-- エラー画面 -->
            <div class="logo">
                <i class="fas fa-exclamation-triangle text-danger"></i>
                <h1>エラー</h1>
            </div>
            <div class="alert alert-danger">
                <i class="fas fa-times-circle"></i> <?= htmlspecialchars($error) ?>
            </div>
            <div class="d-grid gap-2">
                <a href="cast_login.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left"></i> ログイン画面へ戻る
                </a>
            </div>
        <?php else: ?>
            <!-- 登録フォーム -->
            <div class="logo">
                <i class="fas fa-user-plus"></i>
                <h1>キャスト登録</h1>
            </div>
            
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> 
                <strong><?= htmlspecialchars($cast['cast_name']) ?></strong> さん<br>
                メールアドレスとパスワードを設定してください。
            </div>
            
            <form method="post">
                <div class="mb-3">
                    <label class="form-label">
                        <i class="fas fa-envelope"></i> メールアドレス
                    </label>
                    <input 
                        type="email" 
                        name="email" 
                        class="form-control form-control-lg" 
                        placeholder="your-email@example.com"
                        required
                        autocomplete="email"
                    >
                    <div class="requirement">
                        <i class="fas fa-info-circle"></i> ログイン時に使用します
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">
                        <i class="fas fa-lock"></i> パスワード
                    </label>
                    <input 
                        type="password" 
                        name="password" 
                        class="form-control form-control-lg" 
                        placeholder="8文字以上"
                        required
                        minlength="8"
                        autocomplete="new-password"
                    >
                    <div class="requirement">
                        <i class="fas fa-shield-alt"></i> 8文字以上で設定してください
                    </div>
                </div>
                
                <div class="mb-4">
                    <label class="form-label">
                        <i class="fas fa-lock"></i> パスワード（確認）
                    </label>
                    <input 
                        type="password" 
                        name="password_confirm" 
                        class="form-control form-control-lg" 
                        placeholder="もう一度入力"
                        required
                        minlength="8"
                        autocomplete="new-password"
                    >
                </div>
                
                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="fas fa-check"></i> 登録する
                    </button>
                </div>
            </form>
            
            <div class="text-center mt-4">
                <small class="text-muted">
                    <i class="fas fa-lock"></i> 入力した情報は安全に保管されます
                </small>
            </div>
        <?php endif; ?>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
