<?php
/**
 * BASE API スコープ動的管理クラス
 */
class BaseScopeManager {
    private $client_id;
    private $client_secret;
    private $redirect_uri;
    private $api_url;
    
    // 利用可能なスコープ定義
    private $available_scopes = [
        'read_orders' => '注文情報を取得',
        'read_items' => '商品情報を取得', 
        'read_users' => 'ショップ情報を取得',
        'read_users_mail' => 'ショップのメールアドレスを取得',
        'read_savings' => '振込申請情報を取得',
        'write_items' => '商品情報を更新',
        'write_orders' => '注文情報を更新'
    ];
    
    // スコープの組み合わせ（相互排他を考慮）
    private $scope_combinations = [
        'orders_only' => ['read_orders'],
        'items_only' => ['read_items'],
        'users_only' => ['read_users'],
        'users_mail_only' => ['read_users_mail'],
        'savings_only' => ['read_savings'],
        'write_items_only' => ['write_items'],
        'write_orders_only' => ['write_orders']
    ];

    public function __construct() {
        global $base_client_id, $base_client_secret, $base_redirect_uri, $base_api_url;
        
        $this->client_id = $base_client_id;
        $this->client_secret = $base_client_secret;
        $this->redirect_uri = $base_redirect_uri;
        $this->api_url = $base_api_url;
    }

    /**
     * 指定されたスコープの認証URLを生成
     */
    public function getAuthUrl($scope_key) {
        if (!isset($this->scope_combinations[$scope_key])) {
            throw new Exception("無効なスコープキー: {$scope_key}");
        }
        
        $scopes = $this->scope_combinations[$scope_key];
        $scope_string = implode(',', $scopes);
        
        $params = [
            'response_type' => 'code',
            'client_id' => $this->client_id,
            'redirect_uri' => $this->redirect_uri,
            'scope' => $scope_string,
            'state' => $scope_key // スコープ情報をstateに保存
        ];

        return 'https://api.thebase.in/1/oauth/authorize?' . http_build_query($params);
    }

    /**
     * 現在のスコープを取得
     */
    public function getCurrentScope() {
        return $_SESSION['base_current_scope'] ?? null;
    }

    /**
     * スコープを設定
     */
    public function setCurrentScope($scope_key) {
        $_SESSION['base_current_scope'] = $scope_key;
    }

    /**
     * 利用可能なスコープ一覧を取得
     */
    public function getAvailableScopes() {
        return $this->available_scopes;
    }

    /**
     * スコープ組み合わせ一覧を取得
     */
    public function getScopeCombinations() {
        return $this->scope_combinations;
    }

    /**
     * 指定されたスコープでAPIクライアントを作成
     */
    public function createApiClient($scope_key) {
        require_once __DIR__ . '/base_api_client.php';
        
        // 複数スコープの認証状態をチェック
        if (!$this->isScopeAuthenticated($scope_key)) {
            throw new Exception("スコープ '{$scope_key}' で再認証が必要です");
        }
        
        return new BaseApiClient();
    }

    /**
     * 指定されたスコープが認証済みかチェック
     */
    public function isScopeAuthenticated($scope_key) {
        // 現在のスコープが指定されたスコープと一致するかチェック
        $current_scope = $this->getCurrentScope();
        return $current_scope === $scope_key && !$this->needsAuth();
    }

    /**
     * 複数スコープのデータを組み合わせて取得
     */
    public function getCombinedData($order_limit = 50) {
        $result = [
            'orders' => [],
            'items' => [],
            'error' => null
        ];

        try {
            // 1. 注文データを取得
            $orders_client = $this->createApiClient('orders_only');
            $orders_response = $orders_client->getOrders($order_limit);
            $result['orders'] = $orders_response;

            // 2. 商品データを取得（スコープ切り替え）
            $items_client = $this->createApiClient('items_only');
            $items_response = $items_client->getProducts(1000); // 全商品取得
            $result['items'] = $items_response;

        } catch (Exception $e) {
            $result['error'] = $e->getMessage();
        }

        return $result;
    }

    /**
     * 注文データに商品情報をマージ
     */
    public function mergeOrderWithItems($orders, $items) {
        // 商品データをIDでインデックス化
        $items_index = [];
        if (isset($items['items']) && is_array($items['items'])) {
            foreach ($items['items'] as $item) {
                $items_index[$item['item_id']] = $item;
            }
        }

        // 注文データに商品情報をマージ
        if (isset($orders['orders']) && is_array($orders['orders'])) {
            foreach ($orders['orders'] as &$order) {
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

        return $orders;
    }

    /**
     * 認証が必要かチェック
     */
    public function needsAuth() {
        if (!isset($_SESSION['base_access_token'])) {
            return true;
        }
        
        // トークンの有効期限をチェック
        if (isset($_SESSION['base_token_expires'])) {
            return time() >= $_SESSION['base_token_expires'];
        }
        
        return true;
    }
}
?>
