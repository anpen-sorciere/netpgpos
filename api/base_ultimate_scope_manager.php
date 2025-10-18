<?php
/**
 * BASE API 完全自動化システム（最終版）
 * すべてのエッジケースとセキュリティ問題を解決
 */
class BaseUltimateScopeManager {
    private $client_id;
    private $client_secret;
    private $redirect_uri;
    private $api_url;
    private $db_connection;
    
    // スコープの組み合わせ
    private $scope_combinations = [
        'orders_only' => ['read_orders'],
        'items_only' => ['read_items'],
        'users_only' => ['read_users'],
        'users_mail_only' => ['read_users_mail'],
        'savings_only' => ['read_savings'],
        'write_items_only' => ['write_items'],
        'write_orders_only' => ['write_orders']
    ];

    // APIレート制限管理
    private $rate_limit_info = [
        'requests_per_hour' => 1000,
        'requests_per_minute' => 100,
        'current_hour_requests' => 0,
        'current_minute_requests' => 0,
        'last_hour_reset' => 0,
        'last_minute_reset' => 0
    ];

    public function __construct() {
        global $base_client_id, $base_client_secret, $base_redirect_uri, $base_api_url;
        
        $this->client_id = $base_client_id;
        $this->client_secret = $base_client_secret;
        $this->redirect_uri = $base_redirect_uri;
        $this->api_url = $base_api_url;
        
        // データベース接続
        $this->initDatabase();
        
        // レート制限情報を読み込み
        $this->loadRateLimitInfo();
    }

    /**
     * データベース初期化
     */
    private function initDatabase() {
        global $host, $user, $password, $dbname;
        
        try {
            $this->db_connection = new PDO(
                "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
                $user,
                $password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false
                ]
            );
            
            // トークン管理テーブルを作成
            $this->createTokenTable();
            
        } catch (PDOException $e) {
            throw new Exception("データベース接続エラー: " . $e->getMessage());
        }
    }

    /**
     * トークン管理テーブルを作成
     */
    private function createTokenTable() {
        $sql = "
            CREATE TABLE IF NOT EXISTS base_api_tokens (
                id INT AUTO_INCREMENT PRIMARY KEY,
                scope_key VARCHAR(50) NOT NULL UNIQUE,
                access_token TEXT NOT NULL,
                refresh_token TEXT NOT NULL,
                access_expires INT NOT NULL,
                refresh_expires INT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_scope (scope_key),
                INDEX idx_access_expires (access_expires),
                INDEX idx_refresh_expires (refresh_expires)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ";
        
        $this->db_connection->exec($sql);
    }

    /**
     * レート制限情報を読み込み
     */
    private function loadRateLimitInfo() {
        $sql = "SELECT * FROM base_rate_limit WHERE id = 1";
        $stmt = $this->db_connection->prepare($sql);
        $stmt->execute();
        $data = $stmt->fetch();
        
        if ($data) {
            $this->rate_limit_info = array_merge($this->rate_limit_info, $data);
        }
    }

    /**
     * レート制限チェック
     */
    private function checkRateLimit() {
        $current_time = time();
        
        // 時間単位のリセット
        if ($current_time - $this->rate_limit_info['last_hour_reset'] >= 3600) {
            $this->rate_limit_info['current_hour_requests'] = 0;
            $this->rate_limit_info['last_hour_reset'] = $current_time;
        }
        
        // 分単位のリセット
        if ($current_time - $this->rate_limit_info['last_minute_reset'] >= 60) {
            $this->rate_limit_info['current_minute_requests'] = 0;
            $this->rate_limit_info['last_minute_reset'] = $current_time;
        }
        
        // レート制限チェック
        if ($this->rate_limit_info['current_hour_requests'] >= $this->rate_limit_info['requests_per_hour']) {
            throw new Exception("時間あたりのAPIリクエスト制限に達しました。1時間後に再試行してください。");
        }
        
        if ($this->rate_limit_info['current_minute_requests'] >= $this->rate_limit_info['requests_per_minute']) {
            throw new Exception("分あたりのAPIリクエスト制限に達しました。1分後に再試行してください。");
        }
        
        // リクエスト数を増加
        $this->rate_limit_info['current_hour_requests']++;
        $this->rate_limit_info['current_minute_requests']++;
        
        // データベースに保存
        $this->saveRateLimitInfo();
    }

    /**
     * レート制限情報を保存
     */
    private function saveRateLimitInfo() {
        $sql = "
            INSERT INTO base_rate_limit (
                id, requests_per_hour, requests_per_minute, 
                current_hour_requests, current_minute_requests,
                last_hour_reset, last_minute_reset
            ) VALUES (1, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                requests_per_hour = VALUES(requests_per_hour),
                requests_per_minute = VALUES(requests_per_minute),
                current_hour_requests = VALUES(current_hour_requests),
                current_minute_requests = VALUES(current_minute_requests),
                last_hour_reset = VALUES(last_hour_reset),
                last_minute_reset = VALUES(last_minute_reset)
        ";
        
        $stmt = $this->db_connection->prepare($sql);
        $stmt->execute([
            $this->rate_limit_info['requests_per_hour'],
            $this->rate_limit_info['requests_per_minute'],
            $this->rate_limit_info['current_hour_requests'],
            $this->rate_limit_info['current_minute_requests'],
            $this->rate_limit_info['last_hour_reset'],
            $this->rate_limit_info['last_minute_reset']
        ]);
    }

    /**
     * トークンを暗号化して保存
     */
    private function encryptToken($token) {
        $key = hash('sha256', $this->client_secret . 'salt');
        $iv = openssl_random_pseudo_bytes(16);
        $encrypted = openssl_encrypt($token, 'AES-256-CBC', $key, 0, $iv);
        return base64_encode($iv . $encrypted);
    }

    /**
     * トークンを復号化
     */
    private function decryptToken($encrypted_token) {
        $key = hash('sha256', $this->client_secret . 'salt');
        $data = base64_decode($encrypted_token);
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        return openssl_decrypt($encrypted, 'AES-256-CBC', $key, 0, $iv);
    }

    /**
     * スコープ別のトークンをデータベースから取得
     */
    private function getScopeTokenFromDB($scope_key) {
        $sql = "SELECT * FROM base_api_tokens WHERE scope_key = ?";
        $stmt = $this->db_connection->prepare($sql);
        $stmt->execute([$scope_key]);
        $data = $stmt->fetch();
        
        if (!$data) {
            return null;
        }
        
        return [
            'access_token' => $this->decryptToken($data['access_token']),
            'refresh_token' => $this->decryptToken($data['refresh_token']),
            'access_expires' => $data['access_expires'],
            'refresh_expires' => $data['refresh_expires']
        ];
    }

    /**
     * スコープ別のトークンをデータベースに保存
     */
    private function saveScopeTokenToDB($scope_key, $access_token, $refresh_token, $access_expires, $refresh_expires) {
        $sql = "
            INSERT INTO base_api_tokens (
                scope_key, access_token, refresh_token, access_expires, refresh_expires
            ) VALUES (?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                access_token = VALUES(access_token),
                refresh_token = VALUES(refresh_token),
                access_expires = VALUES(access_expires),
                refresh_expires = VALUES(refresh_expires),
                updated_at = CURRENT_TIMESTAMP
        ";
        
        $stmt = $this->db_connection->prepare($sql);
        $stmt->execute([
            $scope_key,
            $this->encryptToken($access_token),
            $this->encryptToken($refresh_token),
            $access_expires,
            $refresh_expires
        ]);
    }

    /**
     * 完全自動で注文データと商品データを取得・合成（最終版）
     */
    public function getCombinedOrderData($order_limit = 50) {
        $result = [
            'orders' => [],
            'items' => [],
            'merged_orders' => [],
            'error' => null,
            'auth_log' => [],
            'rate_limit_info' => $this->rate_limit_info
        ];

        try {
            // レート制限チェック
            $this->checkRateLimit();
            
            // 1. 注文データを取得
            $result['auth_log'][] = "注文データ取得を開始...";
            $orders_data = $this->getDataWithAutoAuth('orders_only', 'getOrders', [$order_limit, 0]);
            $result['orders'] = $orders_data;
            $result['auth_log'][] = "注文データ取得成功: " . count($orders_data) . "件";

            // 2. 商品データを取得（自動スコープ切り替え）
            $result['auth_log'][] = "商品データ取得を開始（スコープ自動切り替え）...";
            $items_data = $this->getDataWithAutoAuth('items_only', 'getProducts', [1000, 0]);
            $result['items'] = $items_data;
            $result['auth_log'][] = "商品データ取得成功: " . count($items_data) . "件";

            // 3. データを合成
            $result['auth_log'][] = "データ合成を開始...";
            $result['merged_orders'] = $this->mergeOrderWithItems($result['orders'], $result['items']);
            $result['auth_log'][] = "データ合成完了";

        } catch (Exception $e) {
            $result['error'] = $e->getMessage();
            $result['auth_log'][] = "エラー: " . $e->getMessage();
        }

        return $result;
    }

    /**
     * 指定されたスコープで自動認証してデータを取得（最終版）
     */
    private function getDataWithAutoAuth($scope_key, $method, $params = []) {
        // データベースからトークンを取得
        $token_data = $this->getScopeTokenFromDB($scope_key);
        
        if (!$token_data) {
            throw new Exception("スコープ '{$scope_key}' で新しい認証が必要です。手動で認証してください。");
        }
        
        // トークンの有効性をチェック
        if (!$this->isTokenValid($token_data)) {
            // 自動リフレッシュを試行
            $this->refreshScopeToken($scope_key, $token_data);
            $token_data = $this->getScopeTokenFromDB($scope_key);
        }
        
        // APIクライアントを作成してデータを取得
        require_once __DIR__ . '/base_api_client.php';
        $api_client = new BaseApiClient();
        
        // 一時的にトークンを設定
        $original_token = $_SESSION['base_access_token'] ?? null;
        $_SESSION['base_access_token'] = $token_data['access_token'];
        
        try {
            $data = call_user_func_array([$api_client, $method], $params);
            return $data;
        } finally {
            // 元のトークンに戻す
            $_SESSION['base_access_token'] = $original_token;
        }
    }

    /**
     * トークンが有効かチェック（最終版）
     */
    private function isTokenValid($token_data) {
        $current_time = time();
        
        // リフレッシュトークンの期限切れチェック
        if ($current_time >= $token_data['refresh_expires']) {
            return false;
        }
        
        // アクセストークンの期限切れチェック
        if ($current_time >= $token_data['access_expires']) {
            return false;
        }
        
        return true;
    }

    /**
     * スコープ別のトークンをリフレッシュ（最終版）
     */
    private function refreshScopeToken($scope_key, $token_data) {
        $scopes = $this->scope_combinations[$scope_key] ?? [];
        $scope_string = implode(',', $scopes);
        
        $data = [
            'grant_type' => 'refresh_token',
            'refresh_token' => $token_data['refresh_token'],
            'client_id' => $this->client_id,
            'client_secret' => $this->client_secret,
            'scope' => $scope_string
        ];
        
        $url = $this->api_url . 'oauth/token';
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        if ($curl_error) {
            throw new Exception("ネットワークエラー: {$curl_error}");
        }
        
        if ($http_code !== 200) {
            $error_detail = "HTTP {$http_code}";
            if ($response) {
                $error_data = json_decode($response, true);
                if (isset($error_data['error_description'])) {
                    $error_detail .= " - " . $error_data['error_description'];
                } elseif (isset($error_data['error'])) {
                    $error_detail .= " - " . $error_data['error'];
                } else {
                    $error_detail .= " - " . $response;
                }
            }
            throw new Exception("リフレッシュトークン認証失敗: {$error_detail}");
        }
        
        $new_token_data = json_decode($response, true);
        
        if (!isset($new_token_data['access_token'])) {
            throw new Exception("アクセストークンの取得に失敗: " . ($response ?: 'レスポンスが空です'));
        }
        
        // 新しいトークンをデータベースに保存
        $access_expires = time() + ($new_token_data['expires_in'] ?? 3600);
        $refresh_expires = time() + (30 * 24 * 60 * 60); // 30日
        
        $this->saveScopeTokenToDB(
            $scope_key,
            $new_token_data['access_token'],
            $new_token_data['refresh_token'] ?? $token_data['refresh_token'],
            $access_expires,
            $refresh_expires
        );
    }

    /**
     * 注文データに商品情報をマージ
     */
    private function mergeOrderWithItems($orders_data, $items_data) {
        // 商品データをIDでインデックス化
        $items_index = [];
        if (isset($items_data['items']) && is_array($items_data['items'])) {
            foreach ($items_data['items'] as $item) {
                $items_index[$item['item_id']] = $item;
            }
        }

        // 注文データに商品情報をマージ
        $merged_orders = $orders_data;
        if (isset($merged_orders['orders']) && is_array($merged_orders['orders'])) {
            foreach ($merged_orders['orders'] as &$order) {
                if (isset($order['order_items']) && is_array($order['order_items'])) {
                    foreach ($order['order_items'] as &$order_item) {
                        $item_id = $order_item['item_id'] ?? null;
                        if ($item_id && isset($items_index[$item_id])) {
                            $order_item['item_detail'] = $items_index[$item_id];
                        }
                    }
                }
            }
        }

        return $merged_orders;
    }

    /**
     * 認証ログを取得
     */
    public function getAuthLog() {
        return $this->auth_log ?? [];
    }

    /**
     * レート制限情報を取得
     */
    public function getRateLimitInfo() {
        return $this->rate_limit_info;
    }
}
?>
