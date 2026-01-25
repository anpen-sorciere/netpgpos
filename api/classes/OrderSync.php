<?php
require_once __DIR__ . '/../../../common/config.php';
require_once __DIR__ . '/../../../common/dbconnect.php';

class OrderSync {
    /**
     * 注文データ同期関数
     * Order MonitorやCronで使用される共通ロジック
     */
    public static function syncOrdersToDb($pdo, $orders, $manager = null, $shop_id = 1) {
        if (empty($orders)) return;

        // デバッグログ用関数
        $debug_log = function($msg) {
            $log_file = __DIR__ . '/../cron/sync_log.txt';
            $timestamp = date('Y-m-d H:i:s');
            file_put_contents($log_file, "[{$timestamp}] [OrderSync Debug] {$msg}\n", FILE_APPEND);
        };

        $debug_log("Starting sync for Shop ID: {$shop_id}, Count: " . count($orders));

        // base_orders アップサート文
        $stmtOrder = $pdo->prepare("
            INSERT INTO base_orders (base_order_id, shop_id, order_date, customer_name, total_amount, status, is_surprise, surprise_date, payment_method, dispatch_status_detail, delivery_company_id, tracking_number, shipping_method, membership_rewards, updated_at)
            VALUES (:base_order_id, :shop_id, :order_date, :customer_name, :total_amount, :status, :is_surprise, :surprise_date, :payment_method, :dispatch_status_detail, :delivery_company_id, :tracking_number, :shipping_method, :membership_rewards, :updated_at)
            ON DUPLICATE KEY UPDATE
                shop_id = VALUES(shop_id),
                customer_name = VALUES(customer_name),
                total_amount = VALUES(total_amount),
                status = VALUES(status),
                is_surprise = VALUES(is_surprise),
                surprise_date = VALUES(surprise_date),
                payment_method = VALUES(payment_method),
                dispatch_status_detail = VALUES(dispatch_status_detail),
                delivery_company_id = VALUES(delivery_company_id),
                tracking_number = VALUES(tracking_number),
                shipping_method = VALUES(shipping_method),
                membership_rewards = VALUES(membership_rewards),
                updated_at = VALUES(updated_at)
        ");

        // base_order_items アップサート文
        // base_order_item_id を使用して重複を適切に処理
        $stmtItem = $pdo->prepare("
            INSERT INTO base_order_items (
                base_order_item_id, base_order_id, product_id, product_name, price, quantity, 
                cast_id, customer_name_from_option, item_surprise_date, shipping_method
            )
            VALUES (
                :base_order_item_id, :base_order_id, :product_id, :product_name, :price, :quantity, 
                :cast_id, :customer_name_from_option, :item_surprise_date, :shipping_method
            )
            ON DUPLICATE KEY UPDATE
                base_order_item_id = VALUES(base_order_item_id),
                product_id = VALUES(product_id),
                product_name = VALUES(product_name),
                price = VALUES(price),
                quantity = VALUES(quantity),
                cast_id = VALUES(cast_id),
                customer_name_from_option = VALUES(customer_name_from_option),
                item_surprise_date = VALUES(item_surprise_date),
                item_surprise_date = VALUES(item_surprise_date),
                shipping_method = VALUES(shipping_method),
                cast_handled = CASE 
                    WHEN VALUES(shipping_method) IS NOT NULL AND VALUES(shipping_method) != '' AND VALUES(cast_id) IS NULL THEN 2 
                    ELSE cast_handled 
                END
        ");
        
        // キャスト名からcast_idを検索するための準備済みステートメント
        $stmtFindCast = $pdo->prepare("
            SELECT cast_id FROM cast_mst 
            WHERE cast_name = :cast_name AND drop_flg = 0
            LIMIT 1
        ");

        foreach ($orders as $order) {
            $order_id = $order['unique_key'] ?? null;
            if (!$order_id) {
                $debug_log("Skipping order: No unique_key found. Data: " . json_encode($order));
                continue;
            }
            
            // order_itemsが含まれていない場合の処理スキップ
            if (!isset($order['order_items']) || empty($order['order_items'])) {
                $debug_log("Skipping order {$order_id}: No order_items found.");
                continue;
            }

            // データの整形
            // orderedが数値(Unixタイムスタンプ)の場合はそのまま、文字列の場合はstrtotime
            $ordered_ts = is_numeric($order['ordered']) ? (int)$order['ordered'] : strtotime($order['ordered']);
            $ordered_at = date('Y-m-d H:i:s', $ordered_ts);

            $last_name = $order['last_name'] ?? '';
            $first_name = $order['first_name'] ?? '';
            $customer_name = trim($last_name . ' ' . $first_name);
            $total_price = $order['total'] ?? 0;
            $payment_method = $order['payment'] ?? '';
            $dispatch_status = $order['dispatch_status'] ?? 'unknown';
            
            // 特典申請データ (membership_rewards)
            $membership_rewards_json = null;
            if (isset($order['membership_rewards']) && is_array($order['membership_rewards'])) {
                $membership_rewards_json = json_encode($order['membership_rewards'], JSON_UNESCAPED_UNICODE);
            }

            // サプライズ判定（オーダーレベル）
            $is_surprise = 0;
            $surprise_date = null;
            
            if (isset($order['order_items']) && is_array($order['order_items'])) {
                foreach ($order['order_items'] as $item) {
                    if (isset($item['options'])) {
                        foreach ($item['options'] as $opt) {
                            $nm = $opt['option_name'] ?? '';
                            $val = $opt['option_value'] ?? '';
                            if (mb_strpos($nm, 'サプライズ') !== false) {
                                $is_surprise = 1;
                                $surprise_date = $val;
                            }
                        }
                    }
                }
            }


            // 配送方法のマッピングを作成 (shipping_lines から)
            $shipping_method_map = [];
            if (isset($order['shipping_lines']) && is_array($order['shipping_lines'])) {
                foreach ($order['shipping_lines'] as $line) {
                    $method_name = $line['shipping_method'] ?? null;
                    if ($method_name && isset($line['order_item_ids']) && is_array($line['order_item_ids'])) {
                        foreach ($line['order_item_ids'] as $line_item_id) {
                            $shipping_method_map[$line_item_id] = $method_name;
                        }
                    }
                }
            }
            
            // 更新日時 (APIレスポンスの 'modified' または 'updated' を優先使用)
            $api_updated_at = null;
            $raw_updated = $order['modified'] ?? $order['updated'] ?? null;

            if (!empty($raw_updated)) {
                $timestamp = false;
                if (is_numeric($raw_updated)) {
                    $timestamp = (int)$raw_updated;
                } else {
                    $timestamp = strtotime($raw_updated);
                }

                if ($timestamp !== false && $timestamp > 0) {
                     $year = (int)date('Y', $timestamp);
                     // 妥当な範囲(2000年〜2099年)のみ許可
                     if ($year >= 2000 && $year <= 2099) {
                        $api_updated_at = date('Y-m-d H:i:s', $timestamp);
                     }
                }
            }
            
            if (!$api_updated_at) {
                // 取得できない、または範囲外の場合は現在時刻
                $api_updated_at = date('Y-m-d H:i:s');
            }

            // Order実行
            try {
                $stmtOrder->execute([
                    ':base_order_id' => $order_id,
                    ':shop_id' => $shop_id,
                    ':order_date' => $ordered_at,
                    ':customer_name' => $customer_name,
                    ':total_amount' => $total_price,
                    ':status' => $dispatch_status,
                    ':is_surprise' => $is_surprise,
                    ':surprise_date' => $surprise_date,
                    ':payment_method' => $payment_method,
                    ':dispatch_status_detail' => $dispatch_status,
                    ':delivery_company_id' => $order['delivery_company_id'] ?? null,
                    ':tracking_number' => $order['tracking_number'] ?? null,
                    ':shipping_method' => $order['shipping_method'] ?? null,
                    ':membership_rewards' => $membership_rewards_json, // 追加
                    ':updated_at' => $api_updated_at
                ]);
                $debug_log("Saved Order: {$order_id} (Shop: {$shop_id})");
            } catch (Exception $e) {
                // エラーを呼び出し元に伝播させてログに残す
                $debug_log("!!! Error Saving Order {$order_id}: " . $e->getMessage());
                throw $e;
            }

            // Items実行
            if (isset($order['order_items']) && is_array($order['order_items'])) {
                foreach ($order['order_items'] as $item) {
                    $base_order_item_id = $item['order_item_id'] ?? null;
                    $base_item_id = $item['item_id'] ?? 'unknown';
                    $title = $item['title'] ?? '';
                    $price = $item['price'] ?? 0;
                    $quantity = $item['amount'] ?? 1;
                    // item内のshipping_methodではなく、shipping_linesからのマッピングを使用
                    // マッピングがない場合は、item内のshipping_methodがあればそれを使う（後方互換）
                    $item_shipping_method = $shipping_method_map[$base_order_item_id] ?? $item['shipping_method'] ?? null;

                    // オプション解析
                    $item_customer = null;
                    $item_cast_name = null;
                    $item_surprise_date = null;

                    if (isset($item['options'])) {
                        foreach ($item['options'] as $opt) {
                            $nm = $opt['option_name'] ?? $opt['name'] ?? '';
                            $val = $opt['option_value'] ?? $opt['value'] ?? '';

                            if (mb_strpos($nm, 'お客様名') !== false || mb_strpos($nm, 'ニックネーム') !== false) {
                                $item_customer = $val;
                            }
                            if (mb_strpos($nm, 'キャスト名') !== false) {
                                $item_cast_name = $val;
                            }
                            if (mb_strpos($nm, 'サプライズ') !== false) {
                                $item_surprise_date = $val;
                            }
                        }
                    }
                    
                    // 商品名からキャスト名を抽出 (生誕祭/お誕生日会)
                    // 例: 【にこ生誕祭】 -> にこ
                    if (preg_match('/【(.+?)(?:生誕祭|お誕生日会)】/u', $title, $matches)) {
                        $extracted_name = trim($matches[1]);
                        if (!empty($extracted_name)) {
                            $item_cast_name = $extracted_name;
                        }
                    }
                    
                    // キャスト名からcast_idを検索
                    $cast_id = null;
                    if ($item_cast_name) {
                        try {
                            $stmtFindCast->execute([':cast_name' => $item_cast_name]);
                            $cast_row = $stmtFindCast->fetch(PDO::FETCH_ASSOC);
                            if ($cast_row) {
                                $cast_id = $cast_row['cast_id'];
                            }
                        } catch (Exception $e) {
                        }
                    }

                    try {
                        $stmtItem->execute([
                            ':base_order_item_id' => $base_order_item_id,
                            ':base_order_id' => $order_id,
                            ':product_id' => $base_item_id,
                            ':product_name' => $title,
                            ':price' => $price,
                            ':quantity' => $quantity,
                            ':cast_id' => $cast_id,
                            ':customer_name_from_option' => $item_customer,
                            ':item_surprise_date' => $item_surprise_date,
                            ':shipping_method' => $item_shipping_method // 追加
                        ]);
                    } catch (Exception $e) {
                         // 詳細なエラー情報を付加してスロー
                         throw new Exception("Item Sync Error ($order_id / $base_item_id): " . $e->getMessage());
                    }
                }
            }
        }
    }
}
?>
