<?php
/**
 * BASE API 事前認証システム
 * 初回認証を自動化するための代替案
 */
class BasePreAuthManager {
    private $client_id;
    private $client_secret;
    private $redirect_uri;
    private $api_url;
    private $pre_auth_codes;
    
    public function __construct() {
        global $base_client_id, $base_client_secret, $base_redirect_uri, $base_api_url;
        
        $this->client_id = $base_client_id;
        $this->client_secret = $base_client_secret;
        $this->redirect_uri = $base_redirect_uri;
        $this->api_url = $base_api_url;
        
        // 事前認証コードの設定（config.phpで管理）
        $this->pre_auth_codes = [
            'orders_only' => $_ENV['BASE_PRE_AUTH_CODE_ORDERS'] ?? null,
            'items_only' => $_ENV['BASE_PRE_AUTH_CODE_ITEMS'] ?? null,
            'users_only' => $_ENV['BASE_PRE_AUTH_CODE_USERS'] ?? null,
            'users_mail_only' => $_ENV['BASE_PRE_AUTH_CODE_USERS_MAIL'] ?? null,
            'savings_only' => $_ENV['BASE_PRE_AUTH_CODE_SAVINGS'] ?? null,
            'write_items_only' => $_ENV['BASE_PRE_AUTH_CODE_WRITE_ITEMS'] ?? null,
            'write_orders_only' => $_ENV['BASE_PRE_AUTH_CODE_WRITE_ORDERS'] ?? null
        ];
    }

    /**
     * 事前認証コードを使用してトークンを取得
     */
    public function getTokenWithPreAuth($scope_key) {
        $pre_auth_code = $this->pre_auth_codes[$scope_key] ?? null;
        
        if (!$pre_auth_code) {
            throw new Exception("スコープ '{$scope_key}' の事前認証コードが設定されていません");
        }
        
        $scopes = $this->getScopeList($scope_key);
        $scope_string = implode(',', $scopes);
        
        $data = [
            'grant_type' => 'authorization_code',
            'client_id' => $this->client_id,
            'client_secret' => $this->client_secret,
            'redirect_uri' => $this->redirect_uri,
            'code' => $pre_auth_code,
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
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code !== 200) {
            throw new Exception("事前認証失敗: HTTP {$http_code} - {$response}");
        }
        
        $token_data = json_decode($response, true);
        
        if (!isset($token_data['access_token'])) {
            throw new Exception("アクセストークンの取得に失敗");
        }
        
        return $token_data;
    }

    /**
     * スコープリストを取得
     */
    private function getScopeList($scope_key) {
        $scope_map = [
            'orders_only' => ['read_orders'],
            'items_only' => ['read_items'],
            'users_only' => ['read_users'],
            'users_mail_only' => ['read_users_mail'],
            'savings_only' => ['read_savings'],
            'write_items_only' => ['write_items'],
            'write_orders_only' => ['write_orders']
        ];
        
        return $scope_map[$scope_key] ?? [];
    }

    /**
     * 完全自動認証システム
     */
    public function performFullyAutomaticAuth() {
        $results = [];
        
        foreach ($this->pre_auth_codes as $scope_key => $pre_auth_code) {
            if ($pre_auth_code) {
                try {
                    $token_data = $this->getTokenWithPreAuth($scope_key);
                    
                    // データベースに保存
                    $this->saveTokenToDatabase($scope_key, $token_data);
                    
                    $results[$scope_key] = [
                        'success' => true,
                        'message' => '認証成功',
                        'expires_in' => $token_data['expires_in'] ?? 3600
                    ];
                    
                } catch (Exception $e) {
                    $results[$scope_key] = [
                        'success' => false,
                        'message' => $e->getMessage()
                    ];
                }
            } else {
                $results[$scope_key] = [
                    'success' => false,
                    'message' => '事前認証コードが設定されていません'
                ];
            }
        }
        
        return $results;
    }

    /**
     * トークンをデータベースに保存
     */
    private function saveTokenToDatabase($scope_key, $token_data) {
        global $host, $user, $password, $dbname;
        
        try {
            $pdo = new PDO(
                "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
                $user,
                $password,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            
            $access_expires = time() + ($token_data['expires_in'] ?? 3600);
            $refresh_expires = time() + (30 * 24 * 60 * 60); // 30日
            
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
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $scope_key,
                $this->encryptToken($token_data['access_token']),
                $this->encryptToken($token_data['refresh_token'] ?? ''),
                $access_expires,
                $refresh_expires
            ]);
            
        } catch (PDOException $e) {
            throw new Exception("データベース保存エラー: " . $e->getMessage());
        }
    }

    /**
     * トークンを暗号化
     */
    private function encryptToken($token) {
        $key = hash('sha256', $this->client_secret . 'salt');
        $iv = openssl_random_pseudo_bytes(16);
        $encrypted = openssl_encrypt($token, 'AES-256-CBC', $key, 0, $iv);
        return base64_encode($iv . $encrypted);
    }
}
?>
