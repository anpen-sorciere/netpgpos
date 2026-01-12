<?php
/**
 * BASE API 完全自動スコープ切り替えクラス
 */
class BaseAutoScopeManager {
    private $client_id;
    private $client_secret;
    private $redirect_uri;
    private $api_url;
    
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

    public function __construct() {
        global $base_client_id, $base_client_secret, $base_redirect_uri, $base_api_url;
        
        $this->client_id = $base_client_id;
        $this->client_secret = $base_client_secret;
        $this->redirect_uri = $base_redirect_uri;
        $this->api_url = $base_api_url;
    }

    /**
     * 完全自動で注文データと商品データを取得・合成
     */
    public function getCombinedOrderData($order_limit = 50) {
        $result = [
            'orders' => [],
            'items' => [],
            'merged_orders' => [],
            'error' => null,
            'auth_log' => []
        ];

        try {
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
     * 指定されたスコープで自動認証してデータを取得
     */
    private function getDataWithAutoAuth($scope_key, $method, $params = []) {
        // スコープ別のトークンをチェック
        $scope_token_key = "base_access_token_{$scope_key}";
        $scope_expires_key = "base_token_expires_{$scope_key}";
        
        // このスコープのトークンが有効かチェック
        if (!$this->isScopeTokenValid($scope_key)) {
            $this->auth_log[] = "スコープ '{$scope_key}' で新しい認証が必要です";
            throw new Exception("スコープ '{$scope_key}' で新しい認証が必要です。手動で認証してください。");
        }
        
        // このスコープのトークンを使用してAPIクライアントを作成
        $original_token = $_SESSION['base_access_token'] ?? null;
        $original_expires = $_SESSION['base_token_expires'] ?? null;
        
        try {
            // スコープ別のトークンを一時的に設定
            $_SESSION['base_access_token'] = $_SESSION[$scope_token_key];
            $_SESSION['base_token_expires'] = $_SESSION[$scope_expires_key];
            
            // APIクライアントを作成してデータを取得
            require_once __DIR__ . '/base_api_client.php';
            $api_client = new BaseApiClient();
            
            $data = call_user_func_array([$api_client, $method], $params);
            
            return $data;
            
        } finally {
            // 元のトークンに戻す
            $_SESSION['base_access_token'] = $original_token;
            $_SESSION['base_token_expires'] = $original_expires;
        }
    }

    /**
     * 自動認証を実行
     */
    private function performAutoAuth($scope_key) {
        if (!isset($this->scope_combinations[$scope_key])) {
            throw new Exception("無効なスコープキー: {$scope_key}");
        }
        
        $scopes = $this->scope_combinations[$scope_key];
        $scope_string = implode(',', $scopes);
        
        // リフレッシュトークンがある場合は使用
        if (isset($_SESSION['base_refresh_token']) && !empty($_SESSION['base_refresh_token'])) {
            try {
                $this->refreshTokenForScope($scope_string);
                return;
            } catch (Exception $e) {
                // リフレッシュに失敗した場合は新しい認証が必要
                $this->auth_log[] = "リフレッシュトークンでの認証に失敗: " . $e->getMessage();
            }
        }
        
        // 新しい認証が必要
        throw new Exception("スコープ '{$scope_key}' で新しい認証が必要です。手動で認証してください。");
    }

    /**
     * 指定されたスコープでリフレッシュトークンを使用
     */
    private function refreshTokenForScope($scope_string) {
        $data = [
            'grant_type' => 'refresh_token',
            'refresh_token' => $_SESSION['base_refresh_token'],
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
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code !== 200) {
            throw new Exception("リフレッシュトークン認証失敗: HTTP {$http_code} - {$response}");
        }
        
        $token_data = json_decode($response, true);
        
        if (!isset($token_data['access_token'])) {
            throw new Exception("アクセストークンの取得に失敗");
        }
        
        // セッションに保存
        $_SESSION['base_access_token'] = $token_data['access_token'];
        $_SESSION['base_token_expires'] = time() + ($token_data['expires_in'] ?? 3600);
        
        if (isset($token_data['refresh_token'])) {
            $_SESSION['base_refresh_token'] = $token_data['refresh_token'];
        }
        
        $this->auth_log[] = "スコープ '{$scope_string}' で自動認証成功";
    }

    /**
     * 認証が必要かチェック
     */
    private function needsAuth() {
        if (!isset($_SESSION['base_access_token'])) {
            return true;
        }
        
        if (isset($_SESSION['base_token_expires'])) {
            return time() >= $_SESSION['base_token_expires'];
        }
        
        return true;
    }

    /**
     * 指定されたスコープのトークンが有効かチェック（自動リフレッシュ付き）
     */
    private function isScopeTokenValid($scope_key) {
        $scope_token_key = "base_access_token_{$scope_key}";
        $scope_expires_key = "base_token_expires_{$scope_key}";
        $scope_refresh_key = "base_refresh_token_{$scope_key}";
        $scope_refresh_expires_key = "base_refresh_expires_{$scope_key}";
        
        if (!isset($_SESSION[$scope_token_key])) {
            return false;
        }
        
        // リフレッシュトークンの期限切れチェック
        if (isset($_SESSION[$scope_refresh_expires_key]) && time() >= $_SESSION[$scope_refresh_expires_key]) {
            $this->auth_log[] = "スコープ '{$scope_key}' のリフレッシュトークンが期限切れ。再認証が必要です。";
            $this->clearScopeToken($scope_key);
            return false;
        }
        
        // アクセストークンの期限切れチェック
        if (isset($_SESSION[$scope_expires_key])) {
            $is_valid = time() < $_SESSION[$scope_expires_key];
            
            // 期限切れの場合は自動リフレッシュを試行
            if (!$is_valid && isset($_SESSION[$scope_refresh_key])) {
                // 同時実行を防ぐためのロック
                $lock_key = "refresh_lock_{$scope_key}";
                if (isset($_SESSION[$lock_key]) && (time() - $_SESSION[$lock_key]) < 30) {
                    // 30秒以内にリフレッシュを試行済みの場合は待機
                    $this->auth_log[] = "スコープ '{$scope_key}' のリフレッシュが進行中です。待機中...";
                    return false;
                }
                
                $_SESSION[$lock_key] = time();
                $this->auth_log[] = "スコープ '{$scope_key}' のトークンが期限切れ。自動リフレッシュを試行...";
                
                try {
                    $this->refreshScopeToken($scope_key);
                    unset($_SESSION[$lock_key]); // ロック解除
                    // リフレッシュ後の再チェック
                    return isset($_SESSION[$scope_expires_key]) && time() < $_SESSION[$scope_expires_key];
                } catch (Exception $e) {
                    unset($_SESSION[$lock_key]); // ロック解除
                    $this->auth_log[] = "スコープ '{$scope_key}' の自動リフレッシュに失敗: " . $e->getMessage();
                    
                    // リフレッシュ失敗の場合はトークンをクリア
                    $this->clearScopeToken($scope_key);
                    return false;
                }
            }
            
            return $is_valid;
        }
        
        return true;
    }

    /**
     * スコープ別のトークンを保存
     */
    public function saveScopeToken($scope_key, $access_token, $expires_in, $refresh_token = null) {
        $scope_token_key = "base_access_token_{$scope_key}";
        $scope_expires_key = "base_token_expires_{$scope_key}";
        $scope_refresh_key = "base_refresh_token_{$scope_key}";
        $scope_refresh_expires_key = "base_refresh_expires_{$scope_key}";
        
        $_SESSION[$scope_token_key] = $access_token;
        $_SESSION[$scope_expires_key] = time() + $expires_in;
        
        if ($refresh_token) {
            $_SESSION[$scope_refresh_key] = $refresh_token;
            // リフレッシュトークンの期限を30日に設定（BASE APIの標準）
            $_SESSION[$scope_refresh_expires_key] = time() + (30 * 24 * 60 * 60);
        }
        
        $this->auth_log[] = "スコープ '{$scope_key}' のトークンを保存しました（アクセス期限: " . date('Y-m-d H:i:s', $_SESSION[$scope_expires_key]) . ", リフレッシュ期限: " . date('Y-m-d H:i:s', $_SESSION[$scope_refresh_expires_key]) . "）";
    }

    /**
     * スコープ別のトークンをクリア
     */
    private function clearScopeToken($scope_key) {
        $keys_to_clear = [
            "base_access_token_{$scope_key}",
            "base_token_expires_{$scope_key}",
            "base_refresh_token_{$scope_key}",
            "base_refresh_expires_{$scope_key}",
            "refresh_lock_{$scope_key}"
        ];
        
        foreach ($keys_to_clear as $key) {
            unset($_SESSION[$key]);
        }
        
        $this->auth_log[] = "スコープ '{$scope_key}' のトークンをクリアしました";
    }

    /**
     * スコープ別の認証状態を取得
     */
    public function getScopeAuthStatus() {
        $status = [];
        $combinations = $this->scope_combinations;
        
        foreach ($combinations as $key => $scope_list) {
            $status[$key] = [
                'authenticated' => $this->isScopeTokenValid($key),
                'expires' => $_SESSION["base_token_expires_{$key}"] ?? null,
                'refresh_expires' => $_SESSION["base_refresh_expires_{$key}"] ?? null,
                'has_refresh_token' => isset($_SESSION["base_refresh_token_{$key}"]),
                'is_refresh_expired' => isset($_SESSION["base_refresh_expires_{$key}"]) && time() >= $_SESSION["base_refresh_expires_{$key}"]
            ];
        }
        
        return $status;
    }

    /**
     * 指定されたスコープのトークンをリフレッシュ
     */
    private function refreshScopeToken($scope_key) {
        $scope_refresh_key = "base_refresh_token_{$scope_key}";
        
        if (!isset($_SESSION[$scope_refresh_key])) {
            throw new Exception("リフレッシュトークンが存在しません");
        }
        
        $scopes = $this->scope_combinations[$scope_key] ?? [];
        $scope_string = implode(',', $scopes);
        
        $data = [
            'grant_type' => 'refresh_token',
            'refresh_token' => $_SESSION[$scope_refresh_key],
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
        
        $token_data = json_decode($response, true);
        
        if (!isset($token_data['access_token'])) {
            throw new Exception("アクセストークンの取得に失敗: " . ($response ?: 'レスポンスが空です'));
        }
        
        // スコープ別のトークンを更新
        $this->saveScopeToken(
            $scope_key,
            $token_data['access_token'],
            $token_data['expires_in'] ?? 3600,
            $token_data['refresh_token'] ?? $_SESSION[$scope_refresh_key]
        );
        
        $this->auth_log[] = "スコープ '{$scope_key}' のトークンを自動リフレッシュしました";
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
}
?>
