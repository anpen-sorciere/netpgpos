<?php
/**
 * BASE API ワンタイムセットアップシステム
 * 初回認証を最小限の手動操作で完了し、以降は完全自動化
 */
class BaseOneTimeSetup {
    private $client_id;
    private $client_secret;
    private $redirect_uri;
    private $api_url;
    
    public function __construct() {
        global $base_client_id, $base_client_secret, $base_redirect_uri, $base_api_url;
        
        $this->client_id = $base_client_id;
        $this->client_secret = $base_client_secret;
        $this->redirect_uri = $base_redirect_uri;
        $this->api_url = $base_api_url;
    }

    /**
     * ワンタイムセットアップ用の認証URLを生成
     */
    public function getOneTimeSetupUrl($scope_key) {
        $scope_map = [
            'orders_only' => 'read_orders',
            'items_only' => 'read_items',
            'users_only' => 'read_users',
            'users_mail_only' => 'read_users_mail',
            'savings_only' => 'read_savings',
            'write_items_only' => 'write_items',
            'write_orders_only' => 'write_orders'
        ];
        
        $scope = $scope_map[$scope_key] ?? '';
        
        $params = [
            'response_type' => 'code',
            'client_id' => $this->client_id,
            'redirect_uri' => $this->redirect_uri,
            'scope' => $scope,
            'state' => $scope_key
        ];

        return 'https://api.thebase.in/1/oauth/authorize?' . http_build_query($params);
    }

    /**
     * セットアップ状況をチェック
     */
    public function checkSetupStatus() {
        global $host, $user, $password, $dbname;
        
        try {
            $pdo = new PDO(
                "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
                $user,
                $password,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            
            $sql = "SELECT scope_key, access_expires, refresh_expires FROM base_api_tokens";
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
            $tokens = $stmt->fetchAll();
            
            $status = [];
            $scope_keys = ['orders_only', 'items_only', 'users_only', 'users_mail_only', 'savings_only', 'write_items_only', 'write_orders_only'];
            
            foreach ($scope_keys as $scope_key) {
                $token_data = array_filter($tokens, function($t) use ($scope_key) {
                    return $t['scope_key'] === $scope_key;
                });
                
                if (!empty($token_data)) {
                    $token = array_values($token_data)[0];
                    $current_time = time();
                    $access_valid = $current_time < $token['access_expires'];
                    $refresh_valid = $current_time < $token['refresh_expires'];
                    
                    $status[$scope_key] = [
                        'setup' => true,
                        'access_valid' => $access_valid,
                        'refresh_valid' => $refresh_valid,
                        'access_expires' => $token['access_expires'],
                        'refresh_expires' => $token['refresh_expires']
                    ];
                } else {
                    $status[$scope_key] = [
                        'setup' => false,
                        'access_valid' => false,
                        'refresh_valid' => false
                    ];
                }
            }
            
            return $status;
            
        } catch (PDOException $e) {
            throw new Exception("データベース接続エラー: " . $e->getMessage());
        }
    }

    /**
     * 最小限のセットアップで動作可能かチェック
     */
    public function isMinimalSetupComplete() {
        $status = $this->checkSetupStatus();
        
        // orders_only と items_only が設定されていれば最小限の動作が可能
        $orders_ok = isset($status['orders_only']['setup']) && $status['orders_only']['setup'];
        $items_ok = isset($status['items_only']['setup']) && $status['items_only']['setup'];
        
        return $orders_ok && $items_ok;
    }

    /**
     * セットアップ完了後の自動運用テスト
     */
    public function testAutomaticOperation() {
        if (!$this->isMinimalSetupComplete()) {
            throw new Exception("最小限のセットアップが完了していません");
        }
        
        try {
            require_once __DIR__ . '/classes/base_ultimate_scope_manager.php';
            $ultimate_manager = new BaseUltimateScopeManager();
            $result = $ultimate_manager->getCombinedOrderData(5);
            
            return [
                'success' => true,
                'orders_count' => count($result['orders']),
                'items_count' => count($result['items']),
                'merged_count' => count($result['merged_orders']),
                'auth_log' => $result['auth_log']
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
}
?>
