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
            'scope' => 'read_orders,read_products'
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

        $url = $this->api_url . $endpoint;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
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

        if ($http_code !== 200) {
            throw new Exception('APIリクエストエラー: HTTP ' . $http_code . ' - ' . $response);
        }

        return json_decode($response, true);
    }

    /**
     * 注文データを取得
     */
    public function getOrders($limit = 100, $offset = 0) {
        $endpoint = "orders?limit={$limit}&offset={$offset}";
        return $this->makeRequest($endpoint);
    }

    /**
     * 商品データを取得
     */
    public function getProducts($limit = 100, $offset = 0) {
        // BASE APIの正しい商品エンドポイントを試行
        $endpoints = [
            "items?limit={$limit}&offset={$offset}",
            "products?limit={$limit}&offset={$offset}",
            "goods?limit={$limit}&offset={$offset}"
        ];
        
        foreach ($endpoints as $endpoint) {
            try {
                return $this->makeRequest($endpoint);
            } catch (Exception $e) {
                if (strpos($e->getMessage(), '404') !== false) {
                    continue; // 次のエンドポイントを試行
                }
                throw $e; // 404以外のエラーは再スロー
            }
        }
        
        throw new Exception('商品データのエンドポイントが見つかりませんでした');
    }

    /**
     * ショップ情報を取得
     */
    public function getShopInfo() {
        return $this->makeRequest('shop');
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
            return time() < $_SESSION['base_token_expires'];
        }

        return true;
    }
}
?>
