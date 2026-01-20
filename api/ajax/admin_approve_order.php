<?php
/**
 * 管理者承認実行API
 * キャスト対応済みの注文を承認し、BASE APIを実行する
 */
session_start();
require_once __DIR__ . '/../../../common/config.php';
require_once __DIR__ . '/../classes/base_practical_auto_manager.php';

header('Content-Type: application/json; charset=utf-8');

try {
    // 簡易管理者認証（order_monitor.phpと同様）
    // 本来は厳密な権限チェックが必要だが、現状のセッション構成に依存
    if (!isset($_SESSION['utype'])) {
        throw new Exception('管理者権限が必要です');
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('POSTメソッドのみサポート');
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $order_id = $input['order_id'] ?? null;
    $cast_id = $input['cast_id'] ?? null; // どのキャストの対応を承認するか
    $shop_id = $input['shop_id'] ?? 1; // shop_idを追加（デフォルト1、必須にすべきだが互換性維持）
    $item_id = $input['item_id'] ?? null; // 特定の商品行（base_order_items.id）のみを対象にする場合

    // 配送情報
    $delivery_company_id = $input['delivery_company_id'] ?? null;
    $tracking_number = $input['tracking_number'] ?? null;

    if (!$order_id) {
        throw new Exception('注文IDは必須です');
    }

    // DB接続
    $pdo = new PDO(
        "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
        $user,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // 注文とキャスト対応状況の取得
    // 特定のキャストの承認待ちアイテムがあるか確認
    $sql = "
        SELECT oi.*, o.customer_name 
        FROM base_order_items oi
        INNER JOIN base_orders o ON oi.base_order_id = o.base_order_id
        WHERE oi.base_order_id = ? 
        AND oi.cast_handled = 1 
    ";
    $params = [$order_id];
    
    // cast_idが指定されていれば絞り込み
    if ($cast_id) {
        $sql .= " AND oi.cast_id = ?";
        $params[] = $cast_id;
    }

    // item_idが指定されていればさらに絞り込み（行単位の承認）
    if ($item_id) {
        $sql .= " AND oi.id = ?";
        $params[] = $item_id;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($items)) {
        throw new Exception('承認待ちの対象商品が見つかりません');
    }

    // BASE API連携用マネージャー (shop_idを渡して初期化)
    $manager = new BasePracticalAutoManager($shop_id);
    $auth_status = $manager->getAuthStatus();
    if (!isset($auth_status['write_orders']['authenticated']) || !$auth_status['write_orders']['authenticated']) {
        throw new Exception('BASE API認証が必要です（管理者側）');
    }

    // テンプレート情報の取得とメッセージ構築
    // 1つの注文に複数の商品が含まれる場合、まとめて1通送るか、別々に送るか？
    // 現状の仕様では「注文単位」でステータス変更するため、まとめて処理するのが自然。
    // ただし、テンプレートが異なる場合は結合する。

    $messages = [];
    $processed_items = [];

    foreach ($items as $item) {
        // リクエストでテンプレート指定があればそれを使用、なければキャストが選んだものを使用
        $use_template_id = $input['template_id'] ?? $item['cast_handled_template_id'];

        if (!$use_template_id) continue;

        $stmt = $pdo->prepare("SELECT * FROM reply_message_templates WHERE id = ?");
        $stmt->execute([$use_template_id]);
        $tmpl = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($tmpl) {
            // 変数置換
            $msg = $tmpl['template_body'];
            $msg = str_replace('{customer_name}', $item['customer_name_from_option'] ?: $items[0]['customer_name'], $msg);
            $msg = str_replace('{product_name}', $item['product_name'], $msg);
            $msg = str_replace('{order_id}', $order_id, $msg);
            
            // キャスト名を取得
            $stmt_cast = $pdo->prepare("SELECT cast_name FROM cast_mst WHERE cast_id = ?");
            $stmt_cast->execute([$item['cast_id']]);
            $cast_name = $stmt_cast->fetchColumn();
            $msg = str_replace('{cast_name}', $cast_name, $msg);

            // 配送情報の変数置換
            $delivery_companies = [
                1 => 'ヤマト運輸', 2 => '佐川急便', 3 => '日本郵便',
                4 => '西濃運輸', 5 => '福山通運', 6 => 'その他'
            ];
            $delivery_company_name = isset($delivery_companies[$delivery_company_id]) ? $delivery_companies[$delivery_company_id] : '';
            $msg = str_replace('{delivery_company}', $delivery_company_name, $msg);
            $msg = str_replace('{tracking_number}', $tracking_number ?? '', $msg);

            // 動画添付チェック
            // order_item_id = $item['id'] (base_order_items.id)
            $stmt_video = $pdo->prepare("
                SELECT video_uuid FROM video_uploads 
                WHERE order_item_id = ? 
                AND expires_at > NOW() 
                ORDER BY created_at DESC LIMIT 1
            ");
            $stmt_video->execute([$item['id']]);
            $video_uuid = $stmt_video->fetchColumn();

            if ($video_uuid) {
                 $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http";
                 $host_name = $_SERVER['HTTP_HOST'];
                 $video_url = "{$protocol}://{$host_name}/thanks.php?id={$video_uuid}";
                 
                 // {video_url} 変数がテンプレートにあれば置換
                 if (strpos($msg, '{video_url}') !== false) {
                     $msg = str_replace('{video_url}', $video_url, $msg);
                 } else {
                     // 変数がなければ従来どおり末尾に追記
                     $video_msg = "\n\n【お礼動画】\n以下のURLより動画をご覧いただけます(視聴期限:7日間)\n" . $video_url;
                     $msg .= $video_msg;
                 }
            } else {
                 // 動画がない場合は{video_url}変数を空文字に置換
                 $msg = str_replace('{video_url}', '', $msg);
            }

            $messages[] = $msg;
            $processed_items[] = $item['product_name'];
        }
    }

    if (empty($messages) && empty($input['custom_message'])) {
        throw new Exception('送信するメッセージを作成できませんでした（テンプレート未設定など）');
    }

    // メッセージ結合（改行で区切る）またはカスタムメッセージ使用
    if (!empty($input['custom_message'])) {
        $final_message = $input['custom_message'];
    } elseif (!empty($messages)) {
        $final_message = implode("\n\n--------------------------------\n\n", $messages);
    } else {
        throw new Exception('送信するメッセージがありません');
    }

    // プレビューモードならここで終了
    if (!empty($input['preview'])) {
        $response = [
            'success' => true,
            'preview' => true,
            'message' => $final_message,
            'processed_items' => $processed_items
        ];

        // 初回表示時（配送情報の自動取得）
        if (!empty($input['init_fetch'])) {
            try {
                // BASE APIから注文詳細を取得
                // $managerは上で初期化済み
                $api_order_detail = $manager->makeApiRequest('read_orders', '/orders/detail/' . $order_id);
                
                if (!empty($api_order_detail['order'])) {
                    // 配送方法の推定
                    // BASEのレスポンスには delivery_type (配送方法名) などが含まれると想定
                    // delivery_company_id は発送済みでないと入っていない可能性が高いが、念のためチェック
                    
                    $suggested_delivery = [
                        'company_id' => '',
                        'tracking_number' => ''
                    ];

                    $order_info = $api_order_detail['order'];

                    // 1. delivery_company_id があればそれを使う
                    if (!empty($order_info['delivery_company_id'])) {
                        $suggested_delivery['company_id'] = $order_info['delivery_company_id'];
                    } 
                    // 2. shipping_method または delivery_type (配送方法名) から推定
                    $dtype = null;
                    
                    // (A) order.shipping_method
                    if (!empty($order_info['shipping_method'])) {
                        $dtype = $order_info['shipping_method'];
                    } 
                    // (B) order.order_items[].shipping_method (アイテムごとの指定)
                    // orderレベルがnullの場合（shipping_lines利用時など）はここを見る必要があるかも
                    elseif (!empty($order_info['order_items']) && is_array($order_info['order_items'])) {
                         foreach ($order_info['order_items'] as $itm) {
                             if (!empty($itm['shipping_method'])) {
                                 $dtype = $itm['shipping_method'];
                                 break; // とりあえず最初の有効なものを使う
                             }
                         }
                    }
                    
                    // (C) delivery_type (フォールバック)
                    if (!$dtype && !empty($order_info['delivery_type'])) {
                        $dtype = $order_info['delivery_type'];
                    }

                    if ($dtype) {
                        // 生の配送方法名を保存（フロントエンド表示用）
                        $suggested_delivery['raw_delivery_type_name'] = $dtype;

                        if (strpos($dtype, 'ヤマト') !== false || strpos($dtype, '宅急便') !== false || strpos($dtype, 'ネコポス') !== false || strpos($dtype, 'コンパクト') !== false) {
                            $suggested_delivery['company_id'] = 1; // ヤマト
                        } elseif (strpos($dtype, '佐川') !== false || strpos($dtype, '飛脚') !== false) {
                            $suggested_delivery['company_id'] = 2; // 佐川
                        } elseif (strpos($dtype, '郵便') !== false || strpos($dtype, 'レターパック') !== false || strpos($dtype, '定形外') !== false || strpos($dtype, 'ゆうパック') !== false || strpos($dtype, 'クリックポスト') !== false || strpos($dtype, 'スマートレター') !== false || strpos($dtype, 'ゆうメール') !== false || strpos($dtype, 'ゆうパケット') !== false) {
                            $suggested_delivery['company_id'] = 3; // 日本郵便
                        } elseif (strpos($dtype, '西濃') !== false || strpos($dtype, 'カンガルー') !== false) {
                            $suggested_delivery['company_id'] = 4; // 西濃
                        } elseif (strpos($dtype, '福山') !== false) {
                            $suggested_delivery['company_id'] = 5; // 福山
                        }
                    }

                    // 追跡番号 (発送済みなどで入っていれば)
                    if (!empty($order_info['tracking_number'])) {
                        $suggested_delivery['tracking_number'] = $order_info['tracking_number'];
                    }

                    $response['suggested_delivery'] = $suggested_delivery;
                    $response['debug_raw_order'] = $order_info; // デバッグ用：全データを含める
                }
            } catch (Exception $e) {
                // APIエラーでもプレビュー自体は返せるようにする（エラーはログに吐くなど）
                // ここでは配送情報の取得失敗として扱う
                $response['suggested_delivery_error'] = $e->getMessage();
            }
        }

        echo json_encode($response);
        exit;
    }

    // BASE API実行 (ステータス更新 & メッセージ送信)
    // BASE APIから最新の注文詳細を取得（order_item_idを取得するため）
    $api_order_detail = $manager->makeApiRequest('read_orders', '/orders/detail/' . $order_id);
    
    if (empty($api_order_detail['order']['order_items'])) {
        throw new Exception('BASEから注文詳細を取得できませんでした');
    }

    $api_items = $api_order_detail['order']['order_items'];

    // BASE API実行 (ステータス更新 & メッセージ送信)
    // order_item_idごとにリクエストを送る必要があるためループ処理
    foreach ($items as $index => $item) {
        // cast_handled = 1 の商品のみ対象（SQLで絞り込んでいるが念のため）
        if (!$item['cast_handled']) continue;

        // DBのbase_order_item_id (BASEのorder_item_id) と一致するAPIのitemを探す
        $target_order_item_id = null;
        
        foreach ($api_items as $api_item) {
            // base_order_item_id が一致するものを探す（最も確実）
            if (isset($item['base_order_item_id']) && $api_item['order_item_id'] == $item['base_order_item_id']) {
                $target_order_item_id = $api_item['order_item_id'];
                
                // 既に発送済みでないかチェック（念の為）
                if ($api_item['status'] === 'dispatched') {
                    // 既に発送済みの場合はスキップ
                    $target_order_item_id = 'ALREADY_DISPATCHED';
                }
                break;
            }
            
            // 下位互換性/フォールバック: base_order_item_idがない古いデータの場合は product_id で判定
            // ただし重複リスクがあるため、これは「まだ発送されていない」ものに限るなどの配慮が必要だが、
            // 新規データは必ず base_order_item_id を持つのでここでは単純なマッチングのみ残す
            if (empty($item['base_order_item_id']) && isset($item['product_id']) && $api_item['item_id'] == $item['product_id']) {
                 // 重複回避ロジックが必要だが、まずは既存動作維持（リスクあり）
                 $target_order_item_id = $api_item['order_item_id'];
                 if ($api_item['status'] === 'dispatched') {
                    $target_order_item_id = 'ALREADY_DISPATCHED';
                 }
                 break;
            }
        }

        if ($target_order_item_id === 'ALREADY_DISPATCHED') {
            continue;
        }

        // マッチする商品が見つからない場合
        if (!$target_order_item_id) {
            // エラーログを残しつつ、次の商品へ
            throw new Exception('商品「' . $item['product_name'] . '」のID特定に失敗しました。詳細ID(order_item_id)が不一致です。');
        }

        $update_data = [
            'order_item_id' => $target_order_item_id,
            'status' => 'dispatched',
            'add_comment' => ($index === 0) ? $final_message : '', // メッセージは最初の商品にのみ添付（重複回避）
            'delivery_company_id' => $delivery_company_id, // 配送会社ID
            'tracking_number' => $tracking_number // 追跡番号
        ];
        
        $manager->makeApiRequest('write_orders', '/orders/edit_status', $update_data, 'POST');
    }

    // 履歴記録 (cast_order_completionsへの保存)
    // 複数の商品がある場合は代表して最初の1件分のログを残すか、または商品ごとに残すか。
    // 元々 cast_complete_order.php では order_id 単位で1レコードだったようなのでそれに合わせる。
    // ただし items 分ループして template_id が異なる可能性考慮が必要だが、
    // 現状はまとめて送っているので、使用した template_id（の配列など）を記録したいところ。
    // シンプルに、ここで承認した情報を記録する。
    
    // itemsの最初の要素の情報を使う（定型文は結合されているが、メインの識別として）
    $main_item = $items[0]; 
    $tmpl_id_log = $main_item['cast_handled_template_id'];
    
    // テンプレート名取得（まだ取得してない場合のため再取得）
    $stmt_tmpl = $pdo->prepare("SELECT template_name FROM reply_message_templates WHERE id = ?");
    $stmt_tmpl->execute([$tmpl_id_log]);
    $tmpl_name_log = $stmt_tmpl->fetchColumn();

    $stmt_log = $pdo->prepare("
        INSERT INTO cast_order_completions 
        (base_order_id, cast_id, completed_at, template_id, template_name, reply_message, base_status_after, success)
        VALUES (?, ?, NOW(), ?, ?, ?, 'dispatched', TRUE)
    ");
    $stmt_log->execute([
        $order_id,
        $cast_id ?: $main_item['cast_id'], // 指定なければitemから
        $tmpl_id_log,
        $tmpl_name_log,
        $final_message
    ]);

    // 注文全体のステータスを判定
    $total_items = count($api_items);
    $handled_count = 0;
    $all_dispatched_or_cancelled = true;

    foreach ($api_items as $api_item) {
        $is_this_item_handled = false;
        
        // 今回承認したアイテムかどうかチェック
        foreach ($items as $db_item) {
            // base_order_item_id (APIのorder_item_id) で厳密に判定
            if (isset($db_item['base_order_item_id']) && $api_item['order_item_id'] == $db_item['base_order_item_id']) {
                $is_this_item_handled = true;
                break;
            }
            // フォールバック: base_order_item_idがない場合は product_id で判定（ただし重複のリスクあり）
            // 新規データでは必ずIDがあるはずなので、else if等は使わず、IDがない場合のみこちらのリスクある判定を行う
            if (empty($db_item['base_order_item_id']) && isset($db_item['product_id']) && $api_item['item_id'] == $db_item['product_id']) {
                $is_this_item_handled = true;
                break;
            }
        }

        // 既にAPI上で発送済み/キャンセル済み、または今回承認したアイテムなら「対応完了」とみなす
        if ($is_this_item_handled || $api_item['status'] === 'dispatched' || $api_item['status'] === 'cancelled') {
            $handled_count++;
        } else {
            $all_dispatched_or_cancelled = false;
        }
    }

    $new_status = 'ordered';
    
    // 全て対応済みなら「dispatched」
    // (部分的な発送の場合は、BASE上はまだ ordered のままなので ordered とする)
    if ($all_dispatched_or_cancelled) {
        $new_status = 'dispatched';
    }

    // DB更新 (ステータス更新)
    // 1. 承認したアイテムの cast_handled を 2 (承認完了) に更新
    foreach ($items as $item) {
        // cast_handled = 1 のものだけを 2 にする（二重更新防止）
        if ($item['cast_handled'] == 1) {
            $pdo->prepare("UPDATE base_order_items SET cast_handled = 2 WHERE id = ?")->execute([$item['id']]);
        }
    }

    // 2. base_ordersのステータス更新
    $pdo->prepare("UPDATE base_orders SET status = ?, updated_at = NOW() WHERE base_order_id = ?")->execute([$new_status, $order_id]);

    // ログ記録などがあればここで行う

    echo json_encode([
        'success' => true,
        'message' => '承認し、BASEへの反映を完了しました。',
        'processed_items' => $processed_items
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
