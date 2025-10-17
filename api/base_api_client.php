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
            'redirect_uri' => $this->redirect_uri,
            'scope' => 'read_orders,read_products,read_shop'
        ];
        
        return 'https://api.thebase.in/1/oauth/authorize?' . http_build_query($params);
    }
    
    /**
     * APIリクエストを実行
     */
    private function makeRequest($endpoint, $method = 'GET', $data = null) {
        if (!$this->access_token) {
            throw new Exception('アクセストークンが設定されていません。');
        }
        
        $url = $this->api_url . $endpoint;
        
        $headers = [
            'Authorization: Bearer ' . $this->access_token,
            'Content-Type: application/json'
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        }
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code === 200) {
            return json_decode($response, true);
        } else {
            error_log("BASE API Error: HTTP $http_code - $response");
            throw new Exception("APIリクエストが失敗しました。HTTPコード: $http_code");
        }
    }
    
    /**
     * 注文データを取得
     */
    public function getOrders($date_from = null, $date_to = null, $limit = 100) {
        $params = ['limit' => $limit];
        
        if ($date_from) {
            $params['date_from'] = $date_from;
        }
        if ($date_to) {
            $params['date_to'] = $date_to;
        }
        
        $endpoint = 'orders?' . http_build_query($params);
        return $this->makeRequest($endpoint);
    }
    
    /**
     * 商品データを取得
     */
    public function getProducts($limit = 100) {
        $params = ['limit' => $limit];
        $endpoint = 'products?' . http_build_query($params);
        return $this->makeRequest($endpoint);
    }
    
    /**
     * ショップ情報を取得
     */
    public function getShopInfo() {
        return $this->makeRequest('shop');
    }
    
    /**
     * 特定の注文詳細を取得
     */
    public function getOrderDetail($order_id) {
        $endpoint = "orders/$order_id";
        return $this->makeRequest($endpoint);
    }
    
    /**
     * 特定の商品詳細を取得
     */
    public function getProductDetail($product_id) {
        $endpoint = "products/$product_id";
        return $this->makeRequest($endpoint);
    }
    
    /**
     * アクセストークンが有効かチェック
     */
    public function isTokenValid() {
        if (!$this->access_token) {
            return false;
        }
        
        // トークンの有効期限をチェック
        if (isset($_SESSION['base_token_expires']) && $_SESSION['base_token_expires'] < time()) {
            return false;
        }
        
        return true;
    }
    
    /**
     * 認証が必要かチェック
     */
    public function needsAuth() {
        return !$this->isTokenValid();
    }
}
