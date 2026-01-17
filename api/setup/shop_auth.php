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

/**
 * 処理 3: 設定保存処理 (New!)
 */
if (isset($_POST['update_config']) && isset($_POST['shop_id'])) {
    $shop_id = $_POST['shop_id'];
    $client_id = trim($_POST['base_client_id'] ?? '');
    $client_secret = trim($_POST['base_client_secret'] ?? '');
    $redirect_uri = trim($_POST['base_redirect_uri'] ?? '');
    $is_active = isset($_POST['base_is_active']) ? 1 : 0;
    
    try {
        $stmt = $pdo->prepare("UPDATE shop_mst SET base_client_id = ?, base_client_secret = ?, base_redirect_uri = ?, base_is_active = ? WHERE shop_id = ?");
        $stmt->execute([$client_id, $client_secret, $redirect_uri, $is_active, $shop_id]);
        $message = "店舗ID: {$shop_id} の設定を更新しました。";
    } catch (PDOException $e) {
        $error = "DB更新エラー: " . $e->getMessage();
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
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { padding: 20px; background-color: #f8f9fa; }
        .container { max-width: 1000px; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
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
            1. 「設定編集」ボタンを押し、BASEのClient ID, Secret, Redirect URIを入力して保存してください。<br>
            2. 「認証する」ボタンを押し、BASEの画面で承認してください。<br>
            3. 「連携中」になれば完了です。
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
                        
                        // トークン状況チェック
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
                            <?php endif; ?>
                        </td>
                        <td><?= $token_status ?></td>
                        <td>
                            <div class="d-flex gap-2">
                                <button type="button" class="btn btn-outline-secondary btn-sm" 
                                    data-bs-toggle="modal" 
                                    data-bs-target="#configModal" 
                                    data-id="<?= $shop['shop_id'] ?>"
                                    data-name="<?= htmlspecialchars($shop['shop_name']) ?>"
                                    data-cid="<?= htmlspecialchars($shop['base_client_id'] ?? '') ?>"
                                    data-sec="<?= htmlspecialchars($shop['base_client_secret'] ?? '') ?>"
                                    data-uri="<?= htmlspecialchars($shop['base_redirect_uri'] ?? '') ?>"
                                    data-active="<?= $shop['base_is_active'] ?>">
                                    <i class="fas fa-cog"></i> 設定編集
                                </button>
                                
                                <?php if ($has_config): ?>
                                    <form method="post" style="display:inline;">
                                        <input type="hidden" name="shop_id" value="<?= $shop['shop_id'] ?>">
                                        <input type="hidden" name="start_auth" value="1">
                                        <button type="submit" class="btn btn-primary btn-sm">
                                            <i class="fas fa-sign-in-alt"></i> 認証する
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <button class="btn btn-secondary btn-sm" disabled>BASE認証</button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <div class="mt-4">
            <a href="../../index.php" class="btn btn-outline-secondary">TOPへ戻る</a>
        </div>
    </div>

    <!-- 設定編集モーダル -->
    <div class="modal fade" id="configModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="post">
                    <div class="modal-header">
                        <h5 class="modal-title">BASE API設定編集 - <span id="modalShopName"></span></h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="update_config" value="1">
                        <input type="hidden" name="shop_id" id="modalShopId">
                        
                        <div class="mb-3">
                            <label class="form-label">Client ID</label>
                            <input type="text" class="form-control" name="base_client_id" id="modalClientId" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Client Secret</label>
                            <input type="text" class="form-control" name="base_client_secret" id="modalClientSecret" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Callback URI</label>
                            <input type="text" class="form-control" name="base_redirect_uri" id="modalRedirectUri" required>
                            <div class="form-text">現在推奨: <?= (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://{$_SERVER['HTTP_HOST']}" . dirname($_SERVER['SCRIPT_NAME']) . "/shop_auth.php" ?></div>
                        </div>
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" name="base_is_active" id="modalIsActive" value="1">
                            <label class="form-check-label">BASE連携を有効にする</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">キャンセル</button>
                        <button type="submit" class="btn btn-primary">保存</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const configModal = document.getElementById('configModal');
        configModal.addEventListener('show.bs.modal', event => {
            const button = event.relatedTarget;
            
            document.getElementById('modalShopId').value = button.getAttribute('data-id');
            document.getElementById('modalShopName').innerText = button.getAttribute('data-name');
            document.getElementById('modalClientId').value = button.getAttribute('data-cid');
            document.getElementById('modalClientSecret').value = button.getAttribute('data-sec');
            document.getElementById('modalRedirectUri').value = button.getAttribute('data-uri');
            document.getElementById('modalIsActive').checked = button.getAttribute('data-active') == '1';
        });
    </script>
</body>
</html>
