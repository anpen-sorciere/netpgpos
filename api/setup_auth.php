<?php
// BASE API 認証セットアップ画面
// 自動更新システムを利用するために、各スコープの認証を一度だけ行うための画面

require_once __DIR__ . '/../../common/config.php';
require_once __DIR__ . '/../../common/dbconnect.php';
require_once __DIR__ . '/base_practical_auto_manager.php';
session_start();

$manager = new BasePracticalAutoManager();
$scopes = [
    'read_orders' => '注文情報の読み取り (必須)',
    'read_items' => '商品情報の読み取り (必須)',
    'write_orders' => '注文ステータスの更新 (発送処理等に必要)',
    'read_users' => 'ショップ情報の取得',
    'read_users_mail' => 'メールアドレスの取得',
    'read_savings' => '残高の確認',
    'write_items' => '商品情報の更新'
];

?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BASE API 認証セットアップ</title>
    <link href="https://unpkg.com/sanitize.css" rel="stylesheet"/>
    <style>
        body { font-family: sans-serif; padding: 20px; max-width: 800px; margin: 0 auto; }
        .scope-card { border: 1px solid #ddd; padding: 15px; margin-bottom: 10px; border-radius: 5px; display: flex; justify-content: space-between; align-items: center; }
        .status-ok { color: green; font-weight: bold; }
        .status-ng { color: red; font-weight: bold; }
        .btn { padding: 8px 16px; background: #007bff; color: white; text-decoration: none; border-radius: 4px; }
        .btn:hover { background: #0056b3; }
        .note { background: #f9f9f9; padding: 15px; margin-bottom: 20px; border-left: 5px solid #007bff; }
    </style>
</head>
<body>
    <h1>BASE API 自動更新システム セットアップ</h1>
    
    <div class="note">
        <p><strong>自動更新を有効にするには、以下の手順を行ってください：</strong></p>
        <ol>
            <li>各スコープの「認証する」ボタンをクリックして、BASEの認証画面で「アプリを承認」してください。</li>
            <li>承認後、この画面（または指定された戻り先）に戻ります。全てのスコープが「認証済み」になるまで繰り返してください。</li>
            <li>全て「認証済み」になれば、今後は自動的にトークンが更新されます。</li>
        </ol>
        <p>※ 認証はデータベースに保存され、定期実行スクリプトによって維持されます。</p>
    </div>

    <?php foreach ($scopes as $key => $label): ?>
        <?php 
            $is_valid = $manager->isTokenValid($key);
            // 認証後の戻り先をこのページに設定
            $auth_url = $manager->getAuthUrl($key, 'setup_auth.php');
        ?>
        <div class="scope-card">
            <div>
                <strong><?= htmlspecialchars($key) ?></strong><br>
                <small><?= htmlspecialchars($label) ?></small>
            </div>
            <div>
                <?php if ($is_valid): ?>
                    <span class="status-ok">認証済み <i class="fas fa-check"></i></span>
                <?php else: ?>
                    <span class="status-ng">未認証</span>
                    <a href="<?= htmlspecialchars($auth_url) ?>" class="btn">認証する</a>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
    
    <hr>
    <p><a href="order_monitor.php">注文監視画面に戻る</a></p>
</body>
</html>
