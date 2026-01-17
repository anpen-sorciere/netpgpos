<?php
/**
 * 店舗ごとのBASE API認証ツール
 * 
 * shop_mst に登録された店舗情報を元に、OAuth認証を行い
 * トークンを base_api_tokens テーブルに保存します。
 */

session_start();
require_once __DIR__ . '/../../../common/config.php';
require_once __DIR__ . '/../classes/base_practical_auto_manager.php';

// DB接続
try {
    $pdo = new PDO(
        "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
        $user,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    die("DB Connection Error: " . $e->getMessage());
}

$message = '';
$error = '';

/**
 * 処理 1: コールバック処理 (BASEからの戻り)
 */
if (isset($_GET['code']) && isset($_GET['state'])) {
    $code = $_GET['code'];
    $state_json = base64_decode($_GET['state']);
    $state_data = json_decode($state_json, true);
    
    if ($state_data && isset($state_data['shop_id'])) {
        $shop_id = $state_data['shop_id'];
        
        try {
            $manager = new BasePracticalAutoManager($shop_id);
            
            // トークン交換
            // exchangeCodeForToken は内部で saveScopeToken を呼ぶが、
            // stateに含まれる scope のキーで保存される。
            // 今回は一括認証なので、取得したトークンを主要なスコープ全てにコピーして保存する。
            
            // まずはクラスのメソッドを使ってトークンを取得（& 指定したスコープで保存）
            $token_response = $manager->exchangeCodeForToken($code, $state_data['primary_scope'] ?? 'read_orders');
            
            $access_token = $token_response['access_token'];
            $refresh_token = $token_response['refresh_token'];
            $expires_in = $token_response['expires_in'];
            
            // 他の主要スコープにも同じトークンを保存しておく
            // (1回の認証で全権限をもらう前提)
            $additional_scopes = [
                'read_items', 
                'read_users', 
                'read_users_mail', 
                'write_items', 
                'write_orders'
            ];
            
            $count = 1;
            foreach ($additional_scopes as $scope) {
                if ($scope !== ($state_data['primary_scope'] ?? '')) {
                    $manager->saveScopeToken($scope, $access_token, $refresh_token, $expires_in);
                    $count++;
                }
            }
            
            $message = "認証に成功しました！ トークンを保存しました。（{$count}つの機能を有効化）";
            
        } catch (Exception $e) {
            $error = "認証エラー: " . $e->getMessage();
        }
    } else {
        $error = "不正なリクエストです (state error)";
    }
}

/**
 * 処理 2: 認証開始リダイレクト
 */
if (isset($_POST['start_auth']) && isset($_POST['shop_id'])) {
    $shop_id = $_POST['shop_id'];
    
    try {
        $manager = new BasePracticalAutoManager($shop_id);
        
        // 要求する権限（主要なもの全部）
        // スペース区切り等ではなく、BASEの仕様に合わせて指定が必要
        // BasePracticalAutoManager::getAuthUrl は単一スコープ前提の作りだったが
        // ここではURLを自作してオーバーライドするか、getAuthUrlを改修するか。
        // Managerの改修はリスクがあるので、ここでURLを構築する。
        
        // 必要なスコープ一覧
        $scopes = [
            'read_orders',
            'read_items',
            'read_users',
            'read_users_mail',
            'write_items',
            'write_orders' // キャンセル処理などで必要になる可能性あり
        ];
        $scope_string = implode(' ', $scopes); // BASEはスペース区切り推奨 (または +)
        
        // shop_mstから設定再取得（Manager経由だとprivateなので）
        $stmt = $pdo->prepare("SELECT base_client_id, base_redirect_uri FROM shop_mst WHERE shop_id = ?");
        $stmt->execute([$shop_id]);
        $shop_conf = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$shop_conf || empty($shop_conf['base_client_id'])) {
            throw new Exception("Client IDが設定されていません。先に設定を行ってください。");
        }
        
        // stateにshop_idを埋め込む
        $state_data = [
            'shop_id' => $shop_id,
            'primary_scope' => 'read_orders', // コールバック後の主保存キー
            'time' => time()
        ];
        $state = base64_encode(json_encode($state_data));
        
        $params = [
            'response_type' => 'code',
            'client_id' => $shop_conf['base_client_id'],
            'redirect_uri' => $shop_conf['base_redirect_uri'],
            'scope' => $scope_string,
            'state' => $state
        ];
        
        $auth_url = 'https://api.thebase.in/1/oauth/authorize?' . http_build_query($params);
        
        header("Location: " . $auth_url);
        exit;
        
    } catch (Exception $e) {
        $error = "初期化エラー: " . $e->getMessage();
    }
}

// 店舗一覧取得
$shops = $pdo->query("SELECT * FROM shop_mst ORDER BY shop_id")->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>店舗別 BASE API認証設定</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { padding: 20px; background-color: #f8f9fa; }
        .container { max-width: 900px; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .status-ok { color: green; font-weight: bold; }
        .status-ng { color: red; font-weight: bold; }
        .status-none { color: gray; }
    </style>
</head>
<body>
    <div class="container">
        <h2 class="mb-4">🏪 店舗別 BASE API連携設定</h2>
        
        <?php if ($message): ?>
            <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <div class="alert alert-info">
            <strong>設定手順:</strong><br>
            1. データベース(shop_mst)にClient ID, Secret, Redirect URIを登録してください。<br>
            2. 下記のリストから対象店舗の「認証する」ボタンを押してください。<br>
            3. BASEのログイン画面に移動し、アプリを「承認」してください。<br>
            4. 自動的にここに戻り、トークンが保存されます。
        </div>

        <table class="table table-bordered table-hover align-middle">
            <thead class="table-light">
                <tr>
                    <th>ID</th>
                    <th>店舗名</th>
                    <th>連携設定</th>
                    <th>トークン状態</th>
                    <th>アクション</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($shops as $shop): ?>
                    <?php
                        // 設定状況チェック
                        $has_config = !empty($shop['base_client_id']) && !empty($shop['base_client_secret']) && !empty($shop['base_redirect_uri']);
                        
                        // トークン状況チェック (read_ordersがあるかで判定)
                        $token_stmt = $pdo->prepare("SELECT access_expires, refresh_expires FROM base_api_tokens WHERE shop_id = ? AND scope_key = 'read_orders'");
                        $token_stmt->execute([$shop['shop_id']]);
                        $token = $token_stmt->fetch(PDO::FETCH_ASSOC);
                        
                        $token_status = '<span class="status-none">未取得</span>';
                        if ($token) {
                            if ($token['refresh_expires'] < time()) {
                                $token_status = '<span class="status-ng">期限切れ</span>';
                            } elseif ($token['access_expires'] < time()) {
                                $token_status = '<span class="text-warning">要更新(自動)</span>';
                            } else {
                                $token_status = '<span class="status-ok">連携中</span>';
                            }
                        }
                    ?>
                    <tr>
                        <td><?= $shop['shop_id'] ?></td>
                        <td>
                            <strong><?= htmlspecialchars($shop['shop_name']) ?></strong><br>
                            <small class="text-muted">
                                Active: <?= $shop['base_is_active'] ? 'ON' : 'OFF' ?>
                            </small>
                        </td>
                        <td>
                            <?php if ($has_config): ?>
                                <span class="text-success"><i class="fas fa-check"></i> 設定あり</span>
                            <?php else: ?>
                                <span class="text-danger"><i class="fas fa-times"></i> 設定不足</span>
                                <div style="font-size:0.8em; color:gray;">DBを確認してください</div>
                            <?php endif; ?>
                        </td>
                        <td><?= $token_status ?></td>
                        <td>
                            <?php if ($has_config): ?>
                                <form method="post">
                                    <input type="hidden" name="shop_id" value="<?= $shop['shop_id'] ?>">
                                    <input type="hidden" name="start_auth" value="1">
                                    <button type="submit" class="btn btn-primary btn-sm">
                                        BASE認証する
                                    </button>
                                </form>
                            <?php else: ?>
                                <button class="btn btn-secondary btn-sm" disabled>設定待ち</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <div class="mt-4">
            <a href="../../index.php" class="btn btn-outline-secondary">TOPへ戻る</a>
        </div>
    </div>
</body>
</html>
