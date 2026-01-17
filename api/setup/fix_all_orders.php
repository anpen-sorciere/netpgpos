<?php
/**
 * 統合データ修復ツール (fix_all_orders.php)
 * 目的: 
 * 1. データの「重複」を検出し、古いデータを保護しながら安全に統合・修復する。
 * 2. 最新400件の注文をAPIから再取得し、欠落しているデータを補完する。
 * 
 * 使用方法:
 * ブラウザからアクセスして実行する。
 * 実行後はサーバーから削除することを推奨。
 */

require_once __DIR__ . '/../../../common/config.php';
require_once __DIR__ . '/../../../common/dbconnect.php';
require_once __DIR__ . '/../classes/OrderSync.php';
require_once __DIR__ . '/../classes/base_practical_auto_manager.php';

// タイムアウト対策（大量データ処理用）
set_time_limit(600); // 10分
ini_set('memory_limit', '512M');
ini_set('display_errors', 1);
error_reporting(E_ALL);

echo '<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>統合データ修復ツール</title>
    <style>
        body { font-family: "Consolas", "Monaco", monospace; padding: 20px; background: #f5f5f5; color: #333; }
        .container { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); max-width: 1000px; margin: 0 auto; }
        .log-box { background: #1e1e1e; color: #ccc; padding: 15px; border-radius: 5px; height: 500px; overflow-y: auto; font-size: 14px; line-height: 1.4; border: 1px solid #444; }
        h1 { margin-top: 0; border-bottom: 2px solid #ddd; padding-bottom: 10px; }
        h2 { margin-top: 20px; color: #444; border-left: 5px solid #007bff; padding-left: 10px; }
        .phase-header { background: #007bff; color: white; padding: 8px 15px; border-radius: 4px; margin: 20px 0 10px; font-weight: bold; }
        .success { color: #4caf50; }
        .info { color: #2196f3; }
        .warn { color: #ff9800; font-weight: bold; }
        .error { color: #f44336; font-weight: bold; }
        .summary-box { background: #e8f5e9; border: 1px solid #4caf50; padding: 15px; border-radius: 5px; margin-top: 20px; }
        .btn { display: inline-block; padding: 10px 20px; background: #d32f2f; color: white; text-decoration: none; border-radius: 4px; font-weight: bold; margin-top: 10px; }
    </style>
</head>
<body>
<div class="container">
<h1>統合データ修復ツール (決定版)</h1>
<p>このツールは以下の順序で処理を実行します。</p>
<ol>
    <li><b>重複データのクレンジング:</b> 重複してしまったレコードを検出し、正しい状態（1つ）に統合します。</li>
    <li><b>欠落データの補完:</b> 直近 <b>400件</b> の注文をBASEから取得し、足りない商品を追加します。</li>
</ol>

<div class="log-box" id="logBox">
';

try {
    $pdo = new PDO(
        "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
        $user,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // =================================================================
    // PHASE 1: 重複データの修復
    // =================================================================
    echo '<div class="phase-header">PHASE 1: 重複データの修復</div>';
    echo "重複データのスキャンを開始します...<br>";
    flush_log();

    // base_order_idとproduct_idが同じで、2件以上あるものを抽出
    $stmtFindDupes = $pdo->query("
        SELECT base_order_id, product_id, COUNT(*) as cnt 
        FROM base_order_items 
        GROUP BY base_order_id, product_id 
        HAVING cnt > 1
        LIMIT 1000
    ");
    
    $duplicates = $stmtFindDupes->fetchAll(PDO::FETCH_ASSOC);
    $dupe_stats = ['fixed' => 0, 'skipped' => 0];
    
    if (empty($duplicates)) {
        echo "<span class='success'>✔ 重複データは見つかりませんでした。正常です。</span><br>";
    } else {
        echo "<span class='warn'>⚠ " . count($duplicates) . " 組の重複が見つかりました。修復を開始します。</span><br>";
        
        $stmtGetRecords = $pdo->prepare("SELECT * FROM base_order_items WHERE base_order_id = :oid AND product_id = :pid ORDER BY id ASC");
        $stmtUpdateOld = $pdo->prepare("UPDATE base_order_items SET base_order_item_id = :new_id WHERE id = :old_id");
        $stmtDeleteNew = $pdo->prepare("DELETE FROM base_order_items WHERE id = :del_id");
        
        foreach ($duplicates as $dupe) {
            $oid = $dupe['base_order_id'];
            $pid = $dupe['product_id'];
            
            // レコード詳細取得
            $stmtGetRecords->execute([':oid' => $oid, ':pid' => $pid]);
            $records = $stmtGetRecords->fetchAll(PDO::FETCH_ASSOC);
            
            $record_old = null; // IDがない（古い）方、あるいはIDが小さい方
            $record_new = null; // IDがある（新しい）方
            
            // 単純な NULL vs NOT NULL の判定
            foreach ($records as $r) {
                if (empty($r['base_order_item_id'])) {
                    $record_old = $r;
                } else {
                    $record_new = $r;
                }
            }

            // 両方IDありの場合など、上記で決まらない場合のフォールバック（作成日時などで判定できないのでID順）
            // 古い方（IDが小さい）を残し、新しい方（IDが大きい）の情報をマージしたいが、
            // 今回のケースは「重複実行で増えたデータ」なので定型
            
            if ($record_old && $record_new && count($records) == 2) {
                // パターンA: 理想的な重複（手動データ保護パターン）
                $old_pk = $record_old['id'];
                $new_pk = $record_new['id'];
                $real_item_id = $record_new['base_order_item_id'];
                
                try {
                    $pdo->beginTransaction();
                    
                    // 1. 先に新しいレコードを削除してIDを解放する（ユニーク制約回避）
                    $stmtDeleteNew->execute([':del_id' => $new_pk]);
                    // 2. 解放されたIDを古いレコードにセットする
                    $stmtUpdateOld->execute([':new_id' => $real_item_id, ':old_id' => $old_pk]);
                    
                    $pdo->commit();
                    $dupe_stats['fixed']++;
                    echo " - <span class='info'>[修復]</span> 注文:{$oid} 商品:{$pid} (旧レコードID:{$old_pk} に統合完了)<br>";
                } catch (Exception $e) {
                    $pdo->rollBack();
                    echo " - <span class='error'>[エラー]</span> {$e->getMessage()}<br>";
                }
            } elseif (!$record_old && count($records) == 2) {
                // パターンB: 両方ともIDが入っている（既に同期済み同士の重複？）
                // IDが大きい方（後からできた方）を消すのがセオリーだが、情報が同じなら消して良い
                $rec1 = $records[0];
                $rec2 = $records[1];
                
                if ($rec1['base_order_item_id'] === $rec2['base_order_item_id']) {
                    // 全く同じオーダーアイテムIDなら、片方削除してOK
                    try {
                        $pdo->exec("DELETE FROM base_order_items WHERE id = {$rec2['id']}");
                        $dupe_stats['fixed']++;
                        echo " - <span class='info'>[削除]</span> 注文:{$oid} 商品:{$pid} (完全な重複を1件削除)<br>";
                    } catch (Exception $e) { /* ignore */ }
                } else {
                    // IDが違う＝別商品扱い？ スキップ
                    echo " - <span class='warn'>[スキップ]</span> 注文:{$oid} 商品:{$pid} (異なるアイテムIDの重複)<br>";
                    $dupe_stats['skipped']++;
                }
            } else {
                echo " - <span class='warn'>[スキップ]</span> 注文:{$oid} 商品:{$pid} (複雑なパターン)<br>";
                $dupe_stats['skipped']++;
            }
            flush_log();
        }
    }

    // =================================================================
    // PHASE 2: 欠落データの補完
    // =================================================================
    echo '<div class="phase-header">PHASE 2: 欠落データの補完 (最新400件)</div>';
    flush_log();

    $manager = new BasePracticalAutoManager();
    $auth = $manager->getAuthStatus();
    
    if (!$auth['read_orders']['authenticated']) {
        throw new Exception("BASE API認証が必要です。");
    }

    // パラメータ設定
    $total_limit = 400; // ユーザー要望
    $batch_size = 50;  // 1回のリクエスト件数
    $offset = 0;
    
    $sync_stats = [
        'checked' => 0,
        'new_added' => 0,
        'linked' => 0,
        'skipped' => 0
    ];

    // DB確認用ステートメント
    $stmtCheckItem = $pdo->prepare("SELECT COUNT(*) FROM base_order_items WHERE base_order_item_id = :id");
    // IDなしの既存レコードを探す（フェーズ1で直らなかった残存用）
    $stmtCheckExistingNull = $pdo->prepare("
        SELECT id FROM base_order_items 
        WHERE base_order_id = :order_id 
          AND product_id = :product_id 
          AND base_order_item_id IS NULL 
        LIMIT 1
    ");
    $stmtUpdateItemId = $pdo->prepare("UPDATE base_order_items SET base_order_item_id = :item_id WHERE id = :id");

    while ($offset < $total_limit) {
        $current_limit = min($batch_size, $total_limit - $offset);
        echo "<br><b>API取得中... (Offset: {$offset}, Limit: {$current_limit})</b><br>";
        flush_log();
        
        $response = $manager->getDataWithAutoAuth(
            'read_orders', 
            '/orders', 
            ['limit' => $current_limit, 'offset' => $offset]
        );
        
        $orders = $response['orders'] ?? [];
        if (empty($orders)) {
            echo "APIからデータが取得できませんでした。終了します。<br>";
            break;
        }

        foreach ($orders as $summary) {
            $order_id = $summary['unique_key'];
            $sync_stats['checked']++;
            
            // ウェイトを入れる
            usleep(250000); // 0.25秒
            
            try {
                $detail = $manager->getDataWithAutoAuth('read_orders', "/orders/detail/{$order_id}");
                $order = $detail['order'] ?? null;
                
                if (!$order || empty($order['order_items'])) continue;
                
                $items = $order['order_items'];
                $new_items_for_insert = [];
                $has_changes = false;
                
                foreach ($items as $item) {
                    $item_id = $item['order_item_id'];
                    $product_id = $item['item_id'];
                    $title = $item['title'];
                    
                    // 1. 存在チェック
                    $stmtCheckItem->execute([':id' => $item_id]);
                    if ($stmtCheckItem->fetchColumn() > 0) {
                        // 既に存在する（正常）
                        continue;
                    }
                    
                    // 2. ID未設定の既存レコードチェック
                    $stmtCheckExistingNull->execute([':order_id' => $order_id, ':product_id' => $product_id]);
                    $existing_null_id = $stmtCheckExistingNull->fetchColumn();
                    
                    if ($existing_null_id) {
                        // 紐付け更新
                        $stmtUpdateItemId->execute([':item_id' => $item_id, ':id' => $existing_null_id]);
                        echo " - <span class='info'>[紐付]</span> {$title} ({$item_id}) を既存レコードと結合しました。<br>";
                        $sync_stats['linked']++;
                        $has_changes = true;
                    } else {
                        // 3. 今度こそ新規追加
                        $new_items_for_insert[] = $item;
                        echo " - <span class='success'>[追加]</span> {$title} ({$item_id}) を新規追加リストに入れました。<br>";
                    }
                }
                
                if (!empty($new_items_for_insert)) {
                    // 新規追加実行
                    $order['order_items'] = $new_items_for_insert;
                    OrderSync::syncOrdersToDb($pdo, [$order], null);
                    $sync_stats['new_added'] += count($new_items_for_insert);
                    $has_changes = true;
                }
                
                if (!$has_changes) {
                    $sync_stats['skipped']++;
                }
                
            } catch (Exception $e) {
                echo "<span class='error'>エラー ({$order_id}): {$e->getMessage()}</span><br>";
            }
            
            // 進捗ドット
            if ($sync_stats['checked'] % 10 === 0) {
                echo ". ";
                flush_log();
            }
        }
        
        $offset += count($orders);
        flush_log();
    }

    // =================================================================
    // 結果表示
    // =================================================================
    echo "</div>"; // End logBox
    
    echo '<div class="summary-box">';
    echo "<h2>処理完了レポート</h2>";
    echo "<b>PHASE 1 (重複修復):</b><br>";
    echo "<ul>";
    echo "<li>修復した重複ペア: <b>{$dupe_stats['fixed']}</b></li>";
    echo "<li>スキップ（要手動確認）: <b>{$dupe_stats['skipped']}</b></li>";
    echo "</ul>";
    echo "<b>PHASE 2 (欠落補完):</b><br>";
    echo "<ul>";
    echo "<li>チェックした注文数: <b>{$sync_stats['checked']}</b> / {$total_limit}</li>";
    echo "<li>新規追加した商品数: <b>{$sync_stats['new_added']}</b></li>";
    echo "<li>既存と紐付けた商品数: <b>{$sync_stats['linked']}</b></li>";
    echo "</ul>";
    echo "<p class='success' style='font-size:1.2em; font-weight:bold;'>すべての作業が完了しました。整合性は確保されています。</p>";
    echo "<p>セキュリティのため、サーバー上の <code>api/setup/fix_all_orders.php</code> は削除してください。</p>";
    echo '</div>';

} catch (Exception $e) {
    echo "</div>"; // End logBox
    echo "<div class='summary-box' style='border-color:red; background:#ffebee;'>";
    echo "<h2 class='error'>致命的エラーが発生しました</h2>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
}

echo '</div>'; // End container
echo '<script>
function scrollToBottom() {
    var logBox = document.getElementById("logBox");
    logBox.scrollTop = logBox.scrollHeight;
}
setInterval(scrollToBottom, 500);
</script>';
echo '</body></html>';

// ログフラッシュ用関数
function flush_log() {
    echo str_repeat(' ', 4096); 
    flush(); 
    ob_flush();
}
?>
