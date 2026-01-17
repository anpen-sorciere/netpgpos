<?php
// 本番環境のみで動作させるための簡易的な保護（本来はIP制限などが望ましいが、今回は使い捨て前提）
// 手動アクセスのためのスクリプト

require_once __DIR__ . '/../../../common/config.php';
require_once __DIR__ . '/../../../common/dbconnect.php';
require_once __DIR__ . '/../classes/OrderSync.php';
require_once __DIR__ . '/../classes/base_practical_auto_manager.php';

// エラー詳細表示
ini_set('display_errors', 1);
error_reporting(E_ALL);

// HTMLヘッダー
echo '<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>過去データ重複チェック同期</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #f0f0f0; }
        .log-box { background: #fff; padding: 20px; border: 1px solid #ccc; max-width: 900px; margin: 0 auto; height: 600px; overflow-y: auto; }
        .success { color: green; }
        .warn { color: orange; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        .info { color: blue; }
        .stats { position: sticky; top: 0; background: #eee; padding: 10px; border-bottom: 2px solid #999; margin-bottom: 20px; }
    </style>
</head>
<body>
<div class="log-box">
<h2>過去データ補完ツール（欠落商品のみ追加）</h2>
';

// パラメータ取得
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 50;
if ($limit > 100) $limit = 100; // 安全のため上限設定

$offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;

echo "<div class='stats'>設定: 最新 {$limit} 件 (Offset: {$offset}) をチェックします...</div>";

try {
    $manager = new BasePracticalAutoManager();
    $auth = $manager->getAuthStatus();
    
    if (!$auth['read_orders']['authenticated']) {
        throw new Exception("BASE API認証が必要です。order_data_ajax.phpなどで認証を済ませてください。");
    }

    // 1. 注文一覧取得
    echo "<p>注文一覧を取得中...</p>";
    flush();
    
    $response = $manager->getDataWithAutoAuth(
        'read_orders', 
        '/orders', 
        ['limit' => $limit, 'offset' => $offset]
    );
    
    $orders = $response['orders'] ?? [];
    echo "<p class='info'>" . count($orders) . " 件の注文が見つかりました。</p>";
    
    $stats = [
        'total' => count($orders),
        'synced_count' => 0,
        'skipped_count' => 0,
        'new_items_added' => 0,
        'items_already_exist' => 0
    ];
    
    // DB接続
    $pdo = new PDO(
        "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
        $user,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // 既存チェック用ステートメント
    $stmtCheckItem = $pdo->prepare("SELECT COUNT(*) FROM base_order_items WHERE base_order_item_id = :id");

    foreach ($orders as $i => $summary) {
        $order_id = $summary['unique_key'];
        echo "<hr><div>[ " . ($i + 1) . " / " . count($orders) . " ] 注文ID: <b>{$order_id}</b> の詳細確認中...</div>";
        flush();
        
        // 詳細API取得（ウェイトを入れる）
        usleep(500000); // 0.5秒待機
        
        try {
            $detail = $manager->getDataWithAutoAuth('read_orders', "/orders/detail/{$order_id}");
            $order = $detail['order'] ?? null;
            
            if (!$order) {
                echo "<div class='error'>詳細データの取得に失敗しました。</div>";
                continue;
            }
            
            $items = $order['order_items'] ?? [];
            if (empty($items)) {
                echo "<div class='warn'>商品データがありません。スキップします。</div>";
                continue;
            }
            
            // 重要: 未登録の商品だけを抽出
            $new_items = [];
            foreach ($items as $item) {
                $item_id = $item['order_item_id'];
                
                // DB存在チェック
                $stmtCheckItem->execute([':id' => $item_id]);
                $exists = $stmtCheckItem->fetchColumn();
                
                if ($exists > 0) {
                    $stats['items_already_exist']++;
//                    echo "<span style='color:grey; font-size:0.8em;'>済:{$item_id} </span>";
                } else {
                    // 未登録のみ追加リストへ
                    $new_items[] = $item;
                    $stats['new_items_added']++;
                    echo "<span class='success'>★ 追加対象発見: {$item['title']} (ID:{$item_id})</span><br>";
                }
            }
            
            if (!empty($new_items)) {
                // 未登録商品がある場合のみ同期実行
                // itemsを未登録のみに書き換えたオーダーオブジェクトを作成
                $order['order_items'] = $new_items;
                
                // 同期実行（OrderSyncはヘッダーも更新するが、それは許容範囲（最新ステータス反映））
                // アイテムはnew_itemsしか入っていないため、既存アイテムは触られない。
                OrderSync::syncOrdersToDb($pdo, [$order], null);
                
                echo "<div class='success'><b>" . count($new_items) . " 件の商品を補完しました。</b></div>";
                $stats['synced_count']++;
            } else {
                echo "<div class='info'>すべての商品は登録済みです。変更なし。</div>";
                $stats['skipped_count']++;
            }
            
        } catch (Exception $e) {
            echo "<div class='error'>エラー: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
        
        // ブラウザへの出力を強制
        if (($i % 5) === 0) {
            echo "<script>window.scrollTo(0,document.body.scrollHeight);</script>";
            flush();
        }
    }
    
    echo "<hr><h2>結果サマリー</h2>";
    echo "<ul>";
    echo "<li>チェックした注文数: <b>{$stats['total']}</b></li>";
    echo "<li>補完を実行した注文数: <b>{$stats['synced_count']}</b></li>";
    echo "<li>変更がなかった注文数: <b>{$stats['skipped_count']}</b></li>";
    echo "<li><b>追加された商品明細数: {$stats['new_items_added']}</b></li>";
    echo "<li>スキップされた既存明細数: {$stats['items_already_exist']}</li>";
    echo "</ul>";
    
    echo "<div class='success' style='padding:20px; border:2px solid green; margin-top:20px;'>
        処理が完了しました。<br>
        このファイル (api/setup/resync_past_orders.php) はセキュリティのため、確認後削除してください。
    </div>";

} catch (Exception $e) {
    echo "<div class='error'>致命的エラー: " . htmlspecialchars($e->getMessage()) . "</div>";
}

echo '</div></body></html>';
?>
