<?php
/**
 * 過去注文のcast_id一括紐付けバッチ
 * 
 * 対象: ステータスが「未対応」(ordered)の注文のみ
 * 処理: BASE API詳細取得 → キャスト名抽出 → cast_id紐付け → DB更新
 * 
 * ⚠️ 注意: このスクリプトは一回限りの実行用です
 */

set_time_limit(0); // 実行時間制限を解除
ini_set('memory_limit', '512M'); // メモリ制限を緩和



// パス解決の試行
$config_path = __DIR__ . '/../../../common/config.php';
if (!file_exists($config_path)) {
    // 別のパスも試す（ローカル環境など）
    $config_path = __DIR__ . '/../../common/config.php';
}

if (!file_exists($config_path)) {
    die("❌ Error: config.php not found. Searched at: " . realpath(__DIR__ . '/../../../common/') . " and " . realpath(__DIR__ . '/../../common/'));
}

require_once $config_path;
require_once __DIR__ . '/../classes/base_practical_auto_manager.php';

// 実行確認
$confirm = isset($_GET['confirm']) && $_GET['confirm'] === 'yes';
$dry_run = !isset($_GET['execute']) || $_GET['execute'] !== 'true';
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50; // デフォルト50件制限

echo '<h2>過去注文のcast_id一括紐付けバッチ</h2>';
echo '<pre>';


if (!$confirm) {
    echo "⚠️ ======================================\n";
    echo "⚠️  このスクリプトは過去データを更新します\n";
    echo "⚠️ ======================================\n\n";
    echo "実行する前に以下を確認してください:\n";
    echo "1. ✅ 差分同期が正常に動作している\n";
    echo "2. ✅ 新規注文でcast_id紐付けが成功している\n";
    echo "3. ✅ キャストダッシュボードで表示を確認済み\n\n";
    
    echo "確認が完了したら以下のURLで実行してください:\n\n";
    echo "【ドライラン（実際には更新しない）】\n";
    echo "http://localhost/netpgpos/api/setup/backfill_cast_id_for_pending_orders.php?confirm=yes\n\n";
    
    echo "【本番実行（実際にDBを更新）】\n";
    echo "http://localhost/netpgpos/api/setup/backfill_cast_id_for_pending_orders.php?confirm=yes&execute=true\n";
    
    echo '</pre>';
    exit;
}

echo "=== 過去注文のcast_id一括紐付けバッチ ===\n";
echo "モード: " . ($dry_run ? "ドライラン（更新なし）" : "本番実行（DB更新）") . "\n";
echo "開始時刻: " . date('Y-m-d H:i:s') . "\n\n";

try {
    // DB接続
    $pdo = new PDO(
        "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
        $user,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
    
    // BASE API Manager
    $manager = new BasePracticalAutoManager();
    
    // ステップ1: 対象注文の抽出
    echo "=== STEP 1: 対象注文の抽出 ===\n";
    echo "条件: status = 'ordered' (未対応) かつ cast_id紐付けなし\n\n";
    
    
    $stmt = $pdo->prepare("
        SELECT DISTINCT o.base_order_id
        FROM base_orders o
        WHERE o.status = 'ordered'
        AND o.base_order_id IN (
            SELECT base_order_id 
            FROM base_order_items 
            WHERE cast_id IS NULL
        )
        ORDER BY o.order_date DESC
        LIMIT :limit
    ");
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    
    $target_order_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $total_orders = count($target_order_ids);
    
    echo "✅ 対象注文: {$total_orders}件\n\n";
    
    if ($total_orders === 0) {
        echo "処理対象の注文がありません。\n";
        echo '</pre>';
        exit;
    }
    
    // ステップ2: 詳細API取得とcast_id紐付け
    echo "=== STEP 2: 詳細API取得とcast_id紐付け ===\n";
    
    $processed = 0;
    $success_count = 0;
    $skip_count = 0;
    $error_count = 0;
    
    // cast_mst事前読み込み
    $stmt = $pdo->query("SELECT cast_id, cast_name FROM cast_mst WHERE drop_flg = 0");
    $cast_map = [];
    while ($row = $stmt->fetch()) {
        $cast_map[$row['cast_name']] = $row['cast_id'];
    }
    
    echo "登録キャスト数: " . count($cast_map) . "名\n\n";
    
    foreach ($target_order_ids as $val) {
        $order_id = trim($val); // 空白除去
        $processed++;
        
        try {
            // 詳細API取得
            try {
                $detail_response = $manager->getDataWithAutoAuth('read_orders', "/1/orders/detail/{$order_id}");
            } catch (Exception $e) {
                if (strpos($e->getMessage(), '404') !== false) {
                    echo "[{$processed}/{$total_orders}] {$order_id}: ⚠️ BASE上に存在しません (404) - スキップ\n";
                    $error_count++;
                    continue;
                }
                throw $e; // その他のエラーは再スロー
            }
            
            if (!isset($detail_response['order']['order_items'])) {
                echo "[{$processed}/{$total_orders}] {$order_id}: ⚠️ order_items なし - スキップ\n";
                $skip_count++;
                continue;
            }
            
            $order_items = $detail_response['order']['order_items'];
            $updated_items = 0;
            
            // 各商品のoptions解析
            foreach ($order_items as $item) {
                if (!isset($item['options']) || empty($item['options'])) {
                    continue;
                }
                
                $item_id = $item['item_id'] ?? null;
                $cast_name = null;
                $customer_name = null;
                $surprise_date = null;
                
                // options解析
                foreach ($item['options'] as $opt) {
                    $opt_name = $opt['option_name'] ?? '';
                    $opt_value = $opt['option_value'] ?? '';
                    
                    if (mb_strpos($opt_name, 'キャスト名') !== false) {
                        $cast_name = $opt_value;
                    }
                    if (mb_strpos($opt_name, 'お客様名') !== false || mb_strpos($opt_name, 'ニックネーム') !== false) {
                        $customer_name = $opt_value;
                    }
                    if (mb_strpos($opt_name, 'サプライズ') !== false) {
                        $surprise_date = $opt_value;
                    }
                }
                
                // cast_id検索
                $cast_id = null;
                if ($cast_name && isset($cast_map[$cast_name])) {
                    $cast_id = $cast_map[$cast_name];
                }
                
                // DB更新
                if ($cast_id !== null || $customer_name !== null || $surprise_date !== null) {
                    if (!$dry_run) {
                        $update_stmt = $pdo->prepare("
                            UPDATE base_order_items 
                            SET 
                                cast_id = :cast_id,
                                customer_name_from_option = :customer_name,
                                item_surprise_date = :surprise_date
                            WHERE base_order_id = :order_id
                            AND product_id = :product_id
                        ");
                        
                        $update_stmt->execute([
                            ':cast_id' => $cast_id,
                            ':customer_name' => $customer_name,
                            ':surprise_date' => $surprise_date,
                            ':order_id' => $order_id,
                            ':product_id' => $item_id
                        ]);
                        
                        $updated_items++;
                    } else {
                        // ドライラン: 表示のみ
                        if ($cast_name) {
                            echo "[DRY-RUN] {$order_id} - {$item_id}: キャスト名={$cast_name} (ID:{$cast_id})\n";
                            $updated_items++;
                        }
                    }
                }
            }
            
            if ($updated_items > 0) {
                $success_count++;
                if (!$dry_run) {
                    echo "[{$processed}/{$total_orders}] {$order_id}: ✅ {$updated_items}件更新\n";
                }
            } else {
                echo "[{$processed}/{$total_orders}] {$order_id}: - キャスト情報なし\n";
                $skip_count++;
            }
            
            // API制限対策: 100件ごとに1秒待機
            if ($processed % 100 === 0) {
                echo "--- {$processed}件処理完了。1秒待機 ---\n";
                sleep(1);
            }
            
        } catch (Exception $e) {
            echo "[{$processed}/{$total_orders}] {$order_id}: ❌ エラー - " . $e->getMessage() . "\n";
            $error_count++;
        }
    }
    
    // 結果サマリー
    echo "\n=== 処理完了 ===\n";
    echo "処理済み注文: {$processed}件\n";
    echo "成功: {$success_count}件\n";
    echo "スキップ: {$skip_count}件\n";
    echo "エラー: {$error_count}件\n";
    echo "終了時刻: " . date('Y-m-d H:i:s') . "\n\n";
    
    if ($dry_run) {
        echo "⚠️ これはドライランです。実際のDB更新は行われていません。\n";
        echo "本番実行するには以下のURLを開いてください:\n";
        echo "http://localhost/netpgpos/api/setup/backfill_cast_id_for_pending_orders.php?confirm=yes&execute=true\n";
    } else {
        echo "✅ DB更新が完了しました。\n";
        echo "キャストダッシュボードで過去の未対応注文も表示されるようになります。\n";
    }
    
} catch (Exception $e) {
    echo "❌ 致命的エラー: " . $e->getMessage() . "\n";
    echo "ファイル: " . $e->getFile() . "\n";
    echo "行: " . $e->getLine() . "\n";
}

echo '</pre>';
?>
