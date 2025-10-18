<?php
/**
 * BASE API連携クラス
 */
class BaseApiClient {
    private $client_id;
    private $client_secret;
    private $redirect_uri;
    private $api_url;
    private $access_token;

    public function __construct() {
        global $base_client_id, $base_client_secret, $base_redirect_uri, $base_api_url;

        $this->client_id = $base_client_id;
        $this->client_secret = $base_client_secret;
        $this->redirect_uri = $base_redirect_uri;
        $this->api_url = $base_api_url;

        // セッションからアクセストークンを取得
        if (isset($_SESSION['base_access_token'])) {
            $this->access_token = $_SESSION['base_access_token'];
        }
    }

    /**
     * OAuth認証URLを生成
     */
    public function getAuthUrl() {
        $params = [
            'response_type' => 'code',
            'client_id' => $this->client_id,
            'redirect_uri' => 'https://purplelion51.sakura.ne.jp/netpgpos/api/base_callback_debug.php',
            'scope' => 'read_orders,read_items'
        ];

        return 'https://api.thebase.in/1/oauth/authorize?' . http_build_query($params);
    }

    /**
     * APIリクエストを実行
     */
    public function makeRequest($endpoint, $method = 'GET', $data = null) {
        if (!$this->access_token) {
            throw new Exception('アクセストークンが設定されていません');
        }

        // トークンの有効性をチェック（リフレッシュトークンエンドポイント以外）
        if ($endpoint !== 'oauth/token' && !$this->isTokenValid()) {
            throw new Exception('アクセストークンが無効です。再認証が必要です。');
        }

        $url = $this->api_url . $endpoint;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->access_token,
            'Content-Type: application/json'
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        }

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false) {
            throw new Exception('APIリクエストに失敗しました');
        }

        $decoded_response = json_decode($response, true);
        
        // トークン無効エラーの特別処理
        if ($http_code === 400 && isset($decoded_response['error']) && $decoded_response['error'] === 'invalid_request') {
            // セッションをクリア
            unset($_SESSION['base_access_token']);
            unset($_SESSION['base_refresh_token']);
            unset($_SESSION['base_token_expires']);
            throw new Exception('アクセストークンが無効です。再認証が必要です。');
        }
        
        if ($http_code !== 200) {
            $error_message = 'APIリクエストエラー: HTTP ' . $http_code;
            if (isset($decoded_response['error_description'])) {
                $error_message .= ' - ' . $decoded_response['error_description'];
            } elseif (isset($decoded_response['error'])) {
                $error_message .= ' - ' . $decoded_response['error'];
            } else {
                $error_message .= ' - ' . $response;
            }
            throw new Exception($error_message);
        }

        return $decoded_response;
    }

    /**
     * 注文データを取得
     */
    public function getOrders($limit = 100, $offset = 0) {
        $endpoint = "orders?limit={$limit}&offset={$offset}";
        return $this->makeRequest($endpoint);
    }
    
    /**
     * 注文詳細を取得
     */
    public function getOrderDetail($unique_key) {
        $endpoint = "orders/detail/{$unique_key}";
        return $this->makeRequest($endpoint);
    }
    
    /**
     * 注文ステータスを更新
     */
    public function updateOrderStatus($unique_key, $status) {
        $endpoint = "orders/edit_status";
        $data = [
            'unique_key' => $unique_key,
            'status' => $status
        ];
        return $this->makeRequest($endpoint, 'POST', $data);
    }

    /**
     * 商品データを取得
     */
    public function getProducts($limit = 100, $offset = 0) {
        $endpoint = "items?limit={$limit}&offset={$offset}";
        return $this->makeRequest($endpoint);
    }
    
    /**
     * 商品詳細を取得
     */
    public function getProductDetail($item_id) {
        $endpoint = "items/detail/{$item_id}";
        return $this->makeRequest($endpoint);
    }

    /**
     * ショップ情報を取得
     */
    public function getShopInfo() {
        $endpoint = "users/me";
        return $this->makeRequest($endpoint);
    }

    /**
     * 認証が必要かチェック
     */
    public function needsAuth() {
        return !$this->isTokenValid();
    }

    /**
     * トークンが有効かチェック
     */
    private function isTokenValid() {
        if (!$this->access_token) {
            return false;
        }

        // トークンの有効期限をチェック
        if (isset($_SESSION['base_token_expires'])) {
            $is_valid = time() < $_SESSION['base_token_expires'];
            
            // 期限切れの場合は自動的にリフレッシュを試行
            if (!$is_valid && isset($_SESSION['base_refresh_token'])) {
                $this->refreshAccessToken();
                // リフレッシュ後の再チェック
                return isset($_SESSION['base_token_expires']) && time() < $_SESSION['base_token_expires'];
            }
            
            return $is_valid;
        }

        return true;
    }
    
    /**
     * アクセストークンをリフレッシュ
     */
    private function refreshAccessToken() {
        if (!isset($_SESSION['base_refresh_token'])) {
            return false;
        }
        
        try {
            $data = [
                'grant_type' => 'refresh_token',
                'refresh_token' => $_SESSION['base_refresh_token'],
                'client_id' => $this->client_id,
                'client_secret' => $this->client_secret
            ];
            
            $response = $this->makeRequest('oauth/token', 'POST', $data);
            
            if (isset($response['access_token'])) {
                $_SESSION['base_access_token'] = $response['access_token'];
                $_SESSION['base_token_expires'] = time() + ($response['expires_in'] ?? 3600);
                
                if (isset($response['refresh_token'])) {
                    $_SESSION['base_refresh_token'] = $response['refresh_token'];
                }
                
                $this->access_token = $response['access_token'];
                return true;
            }
        } catch (Exception $e) {
            // リフレッシュに失敗した場合は認証が必要
            unset($_SESSION['base_access_token']);
            unset($_SESSION['base_refresh_token']);
            unset($_SESSION['base_token_expires']);
        }
        
        return false;
    }
}
?>
