<?php
// BASE API 自動トークン更新スクリプト
// サーバーのCRONなどで定期実行（例: 1時間に1回）することを想定しています

// エラー表示設定
ini_set('display_errors', 1);
error_reporting(E_ALL);

// タイムゾーン設定
date_default_timezone_set('Asia/Tokyo');

// ディレクトリパスの解決
$base_dir = __DIR__ . '/../../';

// CLI実行時の環境変数エミュレーション
if (php_sapi_name() === 'cli' && !isset($_SERVER['HTTP_HOST'])) {
    $_SERVER['HTTP_HOST'] = 'purplelion51.sakura.ne.jp';
}

require_once __DIR__ . '/../../common/config.php';
require_once __DIR__ . '/../../common/dbconnect.php';
require_once __DIR__ . '/base_practical_auto_manager.php';

// ログ出力関数
function console_log($message) {
    echo "[" . date('Y-m-d H:i:s') . "] " . $message . "\n";
}

console_log("BASE API トークン自動更新処理を開始します");

try {
    $manager = new BasePracticalAutoManager();
    
    // 管理対象のスコープ一覧
    $scopes = [
        'read_orders', 
        'read_items', 
        'read_users', 
        'read_users_mail', 
        'read_savings', 
        'write_items', 
        'write_orders'
    ];
    
    foreach ($scopes as $scope) {
        console_log("スコープ: {$scope} の確認中...");
        
        // トークン情報を直接取得して有効期限を確認
        $token_data = $manager->getScopeToken($scope);
        
        if (!$token_data) {
            console_log("- トークンが見つかりません。スキップします。");
            continue;
        }
        
        $current_time = time();
        $access_expires_in = $token_data['access_expires'] - $current_time;
        $refresh_expires_in = $token_data['refresh_expires'] - $current_time;
        
        console_log("- アクセストークン残り時間: " . $access_expires_in . "秒");
        console_log("- リフレッシュトークン残り時間: " . floor($refresh_expires_in / 86400) . "日");
        
        // アクセストークンが残り90分以下なら更新を試みる
        // (通常のisTokenValidは期限切れまで待つが、ここでは余裕を持って更新する)
        if ($access_expires_in < 5400) { // 90分 = 5400秒
            console_log("- 更新が必要です。リフレッシュを試行します...");
            
            try {
                // 強制的にリフレッシュするために、一時的に有効期限をごまかすのではなく、
                // refreshScopeTokenを直接呼び出すのが安全だが、publicメソッドなのでそのまま呼べる
                $result = $manager->refreshScopeToken($scope);
                
                if ($result) {
                    // 更新後の確認
                    $new_token_data = $manager->getScopeToken($scope);
                    $new_refresh_expiry = floor(($new_token_data['refresh_expires'] - time()) / 86400);
                    console_log("  -> 更新成功！ リフレッシュトークン期限: 残り {$new_refresh_expiry} 日");
                } else {
                    console_log("  -> 更新結果がfalseでした");
                }
            } catch (Exception $e) {
                console_log("  -> [エラー] 更新失敗: " . $e->getMessage());
            }
        } else {
            console_log("- まだ有効期限内です。更新不要");
        }
        
        console_log("----------------------------------------");
    }
    
} catch (Exception $e) {
    console_log("致命的なエラーが発生しました: " . $e->getMessage());
}

console_log("処理終了");
