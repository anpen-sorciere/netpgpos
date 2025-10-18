<?php
/**
 * BASE API 実用的完全自動化システム
 * 不明な仕様に対応した堅牢なトークン管理とスコープ切り替え
 */
class BasePracticalAutoManager {
    private $client_id;
    private $client_secret;
    private $redirect_uri;
    private $api_url;
    private $encryption_key;
    
    public function __construct() {
        global $base_client_id, $base_client_secret, $base_redirect_uri, $base_api_url;
        
        $this->client_id = $base_client_id;
        $this->client_secret = $base_client_secret;
        $this->redirect_uri = $base_redirect_uri;
        $this->api_url = $base_api_url;
        
        // 暗号化キーの取得は後で行う（エラーを避けるため）
        try {
            $this->encryption_key = $this->getEncryptionKey();
        } catch (Exception $e) {
            // 暗号化キーの取得に失敗した場合はデフォルト値を使用
            $this->encryption_key = 'default_key_' . md5($this->client_id);
        }
    }

    /**
     * 暗号化キーの取得・生成
     */
    private function getEncryptionKey() {
        global $host, $user, $password, $dbname;
        
        try {
            $pdo = new PDO(
                "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
                $user,
                $password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true
                ]
            );
            
            // 暗号化キーを取得または生成
            $sql = "SELECT value FROM system_config WHERE key_name = 'encryption_key'";
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
            $result = $stmt->fetch();
            
            if ($result) {
                return $result['value'];
            } else {
                // 新しい暗号化キーを生成
                $key = bin2hex(random_bytes(32));
                $sql = "INSERT INTO system_config (key_name, value) VALUES ('encryption_key', ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$key]);
                return $key;
            }
            
        } catch (PDOException $e) {
            // データベース接続失敗時はセッションキーを使用
            if (!isset($_SESSION['base_encryption_key'])) {
                $_SESSION['base_encryption_key'] = bin2hex(random_bytes(32));
            }
            return $_SESSION['base_encryption_key'];
        }
    }

    /**
     * データの暗号化
     */
    private function encrypt($data) {
        $iv = random_bytes(16);
        $encrypted = openssl_encrypt($data, 'AES-256-CBC', hex2bin($this->encryption_key), 0, $iv);
        return base64_encode($iv . $encrypted);
    }

    /**
     * データの復号化
     */
    private function decrypt($encrypted_data) {
        $data = base64_decode($encrypted_data);
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        return openssl_decrypt($encrypted, 'AES-256-CBC', hex2bin($this->encryption_key), 0, $iv);
    }

    /**
     * スコープ別トークンの保存
     */
    public function saveScopeToken($scope_key, $access_token, $refresh_token, $expires_in) {
        global $host, $user, $password, $dbname;
        
        try {
            $pdo = new PDO(
                "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
                $user,
                $password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true
                ]
            );
            
            $current_time = time();
            $access_expires = $current_time + $expires_in;
            $refresh_expires = $current_time + (30 * 24 * 60 * 60); // 30日
            
            $encrypted_access = $this->encrypt($access_token);
            $encrypted_refresh = $this->encrypt($refresh_token);
            
            $sql = "INSERT INTO base_api_tokens 
                    (scope_key, access_token, refresh_token, access_expires, refresh_expires, created_at, updated_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE 
                    access_token = VALUES(access_token),
                    refresh_token = VALUES(refresh_token),
                    access_expires = VALUES(access_expires),
                    refresh_expires = VALUES(refresh_expires),
                    updated_at = VALUES(updated_at)";
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $scope_key,
                $encrypted_access,
                $encrypted_refresh,
                $access_expires,
                $refresh_expires,
                $current_time,
                $current_time
            ]);
            
            $this->logSystemEvent("TOKEN_SAVED", "スコープ {$scope_key} のトークンを保存しました");
            return true;
            
        } catch (PDOException $e) {
            $this->logSystemEvent("TOKEN_SAVE_ERROR", "トークン保存エラー: " . $e->getMessage());
            return false;
        }
    }

    /**
     * スコープ別トークンの取得
     */
    public function getScopeToken($scope_key) {
        global $host, $user, $password, $dbname;
        
        try {
            $pdo = new PDO(
                "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
                $user,
                $password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true
                ]
            );
            
            $sql = "SELECT access_token, refresh_token, access_expires, refresh_expires 
                    FROM base_api_tokens 
                    WHERE scope_key = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$scope_key]);
            $result = $stmt->fetch();
            
            if (!$result) {
                return null;
            }
            
            return [
                'access_token' => $this->decrypt($result['access_token']),
                'refresh_token' => $this->decrypt($result['refresh_token']),
                'access_expires' => $result['access_expires'],
                'refresh_expires' => $result['refresh_expires']
            ];
            
        } catch (PDOException $e) {
            $this->logSystemEvent("TOKEN_GET_ERROR", "トークン取得エラー: " . $e->getMessage());
            return null;
        }
    }

    /**
     * トークンの有効性チェック（不明な仕様に対応）
     */
    public function isTokenValid($scope_key) {
        $token_data = $this->getScopeToken($scope_key);
        
        if (!$token_data) {
            return false;
        }
        
        $current_time = time();
        
        // アクセストークンの有効期限チェック
        if ($current_time >= $token_data['access_expires']) {
            $this->logSystemEvent("ACCESS_TOKEN_EXPIRED", "スコープ {$scope_key} のアクセストークンが期限切れ");
            return false;
        }
        
        // リフレッシュトークンの有効期限チェック
        if ($current_time >= $token_data['refresh_expires']) {
            $this->logSystemEvent("REFRESH_TOKEN_EXPIRED", "スコープ {$scope_key} のリフレッシュトークンが期限切れ");
            return false;
        }
        
        return true;
    }

    /**
     * リフレッシュトークンでアクセストークンを更新（不明な仕様に対応）
     */
    public function refreshScopeToken($scope_key) {
        $token_data = $this->getScopeToken($scope_key);
        
        if (!$token_data) {
            throw new Exception("スコープ {$scope_key} のトークンデータが見つかりません");
        }
        
        // 同時実行制御
        $lock_key = "refresh_lock_{$scope_key}";
        if (isset($_SESSION[$lock_key]) && $_SESSION[$lock_key] > time() - 60) {
            throw new Exception("リフレッシュ処理が既に実行中です");
        }
        
        $_SESSION[$lock_key] = time();
        
        try {
            $url = $this->api_url . '/1/oauth/token';
            
            $data = [
                'grant_type' => 'refresh_token',
                'client_id' => $this->client_id,
                'client_secret' => $this->client_secret,
                'refresh_token' => $token_data['refresh_token']
            ];
            
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => http_build_query($data),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/x-www-form-urlencoded'
                ]
            ]);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($ch);
            curl_close($ch);
            
            if ($curl_error) {
                throw new Exception("ネットワークエラー: {$curl_error}");
            }
            
            if ($http_code !== 200) {
                $error_data = json_decode($response, true);
                $error_msg = $error_data['error_description'] ?? "HTTP {$http_code}";
                throw new Exception("APIリクエストエラー: HTTP {$http_code} - {$error_msg}");
            }
            
            $response_data = json_decode($response, true);
            
            if (!isset($response_data['access_token'])) {
                throw new Exception("レスポンスにアクセストークンが含まれていません");
            }
            
            // 新しいトークンを保存
            $this->saveScopeToken(
                $scope_key,
                $response_data['access_token'],
                $response_data['refresh_token'] ?? $token_data['refresh_token'], // 不明な仕様のため、既存のリフレッシュトークンを保持
                $response_data['expires_in'] ?? 3600
            );
            
            $this->logSystemEvent("TOKEN_REFRESHED", "スコープ {$scope_key} のトークンを更新しました");
            return true;
            
        } catch (Exception $e) {
            $this->logSystemEvent("TOKEN_REFRESH_ERROR", "トークン更新エラー: " . $e->getMessage());
            throw $e;
        } finally {
            unset($_SESSION[$lock_key]);
        }
    }

    /**
     * スコープ別データ取得（自動認証・更新）
     */
    public function getDataWithAutoAuth($scope_key, $endpoint, $params = []) {
        // トークンの有効性チェック
        if (!$this->isTokenValid($scope_key)) {
            $this->logSystemEvent("AUTH_REQUIRED", "スコープ {$scope_key} で再認証が必要です");
            throw new Exception("スコープ {$scope_key} で再認証が必要です");
        }
        
        $token_data = $this->getScopeToken($scope_key);
        $access_token = $token_data['access_token'];
        
        $url = rtrim($this->api_url, '/') . '/' . ltrim($endpoint, '/');
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $access_token
            ]
        ]);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        if ($curl_error) {
            throw new Exception("ネットワークエラー: {$curl_error}");
        }
        
        if ($http_code === 401) {
            // アクセストークンが無効 - リフレッシュを試行
            try {
                $this->refreshScopeToken($scope_key);
                return $this->getDataWithAutoAuth($scope_key, $endpoint, $params); // 再帰呼び出し
            } catch (Exception $e) {
                throw new Exception("認証エラー: リフレッシュトークンも無効です");
            }
        }
        
        if ($http_code !== 200) {
            $error_data = json_decode($response, true);
            $error_msg = $error_data['error_description'] ?? "HTTP {$http_code}";
            throw new Exception("APIリクエストエラー: HTTP {$http_code} - {$error_msg}");
        }
        
        return json_decode($response, true);
    }

    /**
     * 注文データと商品データの組み合わせ取得
     */
    public function getCombinedOrderData($limit = 50) {
        $auth_log = [];
        
        try {
            // 注文データ取得
            $auth_log[] = "注文データ取得を開始...";
            $orders_data = $this->getDataWithAutoAuth('orders_only', '/orders', ['limit' => $limit]);
            $auth_log[] = "注文データ取得成功: " . count($orders_data['orders']) . "件";
            
            // 商品データ取得
            $auth_log[] = "商品データ取得を開始...";
            $items_data = $this->getDataWithAutoAuth('items_only', '/items', ['limit' => 100]);
            $auth_log[] = "商品データ取得成功: " . count($items_data['items']) . "件";
            
            // データ合成
            $auth_log[] = "データ合成を開始...";
            $merged_orders = $this->mergeOrderAndItemData($orders_data['orders'], $items_data['items']);
            $auth_log[] = "データ合成完了";
            
            return [
                'orders' => $orders_data['orders'],
                'items' => $items_data['items'],
                'merged_orders' => $merged_orders,
                'auth_log' => $auth_log
            ];
            
        } catch (Exception $e) {
            $auth_log[] = "エラー: " . $e->getMessage();
            throw new Exception($e->getMessage());
        }
    }

    /**
     * 注文データと商品データの合成
     */
    private function mergeOrderAndItemData($orders, $items) {
        $merged_orders = [];
        
        foreach ($orders as $order) {
            $merged_order = $order;
            
            // 注文の商品情報を詳細化
            if (isset($order['order_items'])) {
                foreach ($order['order_items'] as &$order_item) {
                    $item_id = $order_item['item_id'];
                    
                    // 商品情報を検索
                    foreach ($items as $item) {
                        if ($item['item_id'] == $item_id) {
                            $order_item['item_detail'] = $item;
                            break;
                        }
                    }
                }
            }
            
            $merged_orders[] = $merged_order;
        }
        
        return $merged_orders;
    }

    /**
     * システムログの記録
     */
    private function logSystemEvent($event_type, $message) {
        global $host, $user, $password, $dbname;
        
        try {
            $pdo = new PDO(
                "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
                $user,
                $password,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true
                ]
            );
            
            $sql = "INSERT INTO system_logs (event_type, message, created_at) VALUES (?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$event_type, $message, time()]);
            
        } catch (PDOException $e) {
            // ログ記録失敗は無視
        }
    }

    /**
     * 認証URLの生成
     */
    public function getAuthUrl($scope) {
        // デバッグ情報
        if (empty($this->client_id)) {
            throw new Exception("client_id が設定されていません");
        }
        if (empty($this->redirect_uri)) {
            throw new Exception("redirect_uri が設定されていません");
        }
        
        $scope_map = [
            'orders_only' => 'read_orders',
            'items_only' => 'read_items',
            'users_only' => 'read_users',
            'users_mail_only' => 'read_users_mail',
            'savings_only' => 'read_savings',
            'write_items_only' => 'write_items',
            'write_orders_only' => 'write_orders'
        ];
        
        $api_scope = $scope_map[$scope] ?? $scope;
        $state = $scope; // スコープをstateとして使用
        
        $params = [
            'response_type' => 'code',
            'client_id' => $this->client_id,
            'redirect_uri' => $this->redirect_uri,
            'scope' => $api_scope,
            'state' => $state
        ];
        
        return 'https://api.thebase.in/1/oauth/authorize?' . http_build_query($params);
    }

    /**
     * 認証状態の確認
     */
    public function getAuthStatus() {
        $scopes = ['注文のみ', 'アイテムのみ', 'users_only', 'users_mail_only', 'savings_only', 'write_items_only', 'write_orders_only'];
        $status = [];
        
        foreach ($scopes as $scope) {
            $token_data = $this->getScopeToken($scope);
            
            if ($token_data) {
                $current_time = time();
                $access_valid = $current_time < $token_data['access_expires'];
                $refresh_valid = $current_time < $token_data['refresh_expires'];
                
                $status[$scope] = [
                    'authenticated' => true,
                    'access_valid' => $access_valid,
                    'refresh_valid' => $refresh_valid,
                    'access_expires' => date('Y-m-d H:i:s', $token_data['access_expires']),
                    'refresh_expires' => date('Y-m-d H:i:s', $token_data['refresh_expires'])
                ];
            } else {
                $status[$scope] = [
                    'authenticated' => false,
                    'access_valid' => false,
                    'refresh_valid' => false
                ];
            }
        }
        
        return $status;
    }
}
?>
