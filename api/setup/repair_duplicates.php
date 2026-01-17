<?php
// 重複データ修復ツール
// 重複してしまった base_order_items を検出し、古いデータを優先してマージ・修復する

require_once __DIR__ . '/../../../common/config.php';
require_once __DIR__ . '/../../../common/dbconnect.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);

echo '<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>重複データ修復ツール</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #f0f0f0; }
        .log-box { background: #fff; padding: 20px; border: 1px solid #ccc; max-width: 900px; margin: 0 auto; height: 600px; overflow-y: auto; }
        .success { color: green; }
        .warn { color: orange; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        .info { color: blue; }
        .action { color: white; background: green; padding: 2px 5px; border-radius: 3px; }
        .delete { color: white; background: red; padding: 2px 5px; border-radius: 3px; }
    </style>
</head>
<body>
<div class="log-box">
<h2>重複データ修復ツール</h2>
<p>同じ注文・同じ商品で重複しているレコードを検出し、以下のルールで修復します。</p>
<ul>
 <li><b>古いデータ（IDなし）</b> と <b>新しいデータ（IDあり）</b> が重複している場合:</li>
 <li>古いデータに新しいデータのIDをコピーして紐付けます（手動データ保護）。</li>
 <li>不要になった新しいデータを削除します。</li>
</ul>
<hr>
';

try {
    $pdo = new PDO(
        "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
        $user,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // 1. 重複候補の検索
    echo "<p>重複データを検索中...</p>";
    
    // base_order_idとproduct_idが同じで、2件以上あるものを抽出
    $stmtFindDupes = $pdo->query("
        SELECT base_order_id, product_id, COUNT(*) as cnt 
        FROM base_order_items 
        GROUP BY base_order_id, product_id 
        HAVING cnt > 1
        LIMIT 500
    ");
    
    $duplicates = $stmtFindDupes->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($duplicates)) {
        echo "<h3 class='success'>重複データは見つかりませんでした。正常です。</h3>";
    } else {
        echo "<h3 class='warn'>" . count($duplicates) . " 組の重複が見つかりました。修復を開始します。</h3>";
        
        // 修復用ステートメント
        $stmtGetRecords = $pdo->prepare("SELECT * FROM base_order_items WHERE base_order_id = :oid AND product_id = :pid ORDER BY id ASC");
        
        $stmtUpdateOld = $pdo->prepare("UPDATE base_order_items SET base_order_item_id = :new_id WHERE id = :old_id");
        $stmtDeleteNew = $pdo->prepare("DELETE FROM base_order_items WHERE id = :del_id");
        
        $fixed_count = 0;
        $skip_count = 0;
        
        foreach ($duplicates as $dupe) {
            $oid = $dupe['base_order_id'];
            $pid = $dupe['product_id'];
            
            echo "<hr><div><b>注文: {$oid} / 商品: {$pid}</b> (件数: {$dupe['cnt']})</div>";
            
            // レコード詳細取得
            $stmtGetRecords->execute([':oid' => $oid, ':pid' => $pid]);
            $records = $stmtGetRecords->fetchAll(PDO::FETCH_ASSOC);
            
            // 分類
            $record_old = null; // IDがない（古い）方
            $record_new = null; // IDがある（新しい）方
            
            foreach ($records as $r) {
                if (empty($r['base_order_item_id'])) {
                    $record_old = $r;
                } else {
                    $record_new = $r;
                }
            }
            
            // パターン判定
            if ($record_old && $record_new && count($records) == 2) {
                // 理想的なパターン: 古い1件と新しい1件の重複
                $old_pk = $record_old['id'];
                $new_pk = $record_new['id'];
                $real_item_id = $record_new['base_order_item_id'];
                
                echo "<div>検知: 古いレコード(id:{$old_pk}) と 新しいレコード(id:{$new_pk}, ItemID:{$real_item_id})</div>";
                
                try {
                    $pdo->beginTransaction();
                    
                    // 1. 古いレコードにIDを移植
                    $stmtUpdateOld->execute([':new_id' => $real_item_id, ':old_id' => $old_pk]);
                    echo "<div><span class='action'>統合</span> 古いレコードに ID:{$real_item_id} をセットしました。</div>";
                    
                    // 2. 新しいレコードを削除
                    $stmtDeleteNew->execute([':del_id' => $new_pk]);
                    echo "<div><span class='delete'>削除</span> 重複していた新しいレコードを削除しました。</div>";
                    
                    $pdo->commit();
                    $fixed_count++;
                    echo "<div class='success'><b>修復完了</b></div>";
                    
                } catch (Exception $e) {
                    $pdo->rollBack();
                    echo "<div class='error'>エラー: " . htmlspecialchars($e->getMessage()) . "</div>";
                }
                
            } elseif (!$record_old && count($records) >= 2) {
                // 両方ともIDが入っている場合など（通常はないはずだが）
                // どちらを消していいかわからないためスキップ
                echo "<div class='warn'>スキップ: 両方にIDが入っているか、想定外のパターンです。手動確認が必要です。</div>";
                foreach($records as $r) {
                    echo "<div> - id:{$r['id']}, item_id:{$r['base_order_item_id']}</div>";
                }
                $skip_count++;
            } else {
                echo "<div class='warn'>スキップ: 複雑な重複パターンです。</div>";
                $skip_count++;
            }
        }
        
        echo "<hr><h2>結果</h2>";
        echo "<ul>";
        echo "<li>修復完了: <b>{$fixed_count}</b> 組</li>";
        echo "<li>スキップ: <b>{$skip_count}</b> 組</li>";
        echo "</ul>";
        
        echo "<div class='success' style='padding:20px; border:2px solid green; margin-top:20px;'>
            すべての処理が終了しました。<br>
            このファイル (api/setup/repair_duplicates.php) は利用後削除してください。
        </div>";
    }

} catch (Exception $e) {
    echo "<div class='error'>致命的エラー: " . htmlspecialchars($e->getMessage()) . "</div>";
}

echo '</div></body></html>';
?>
