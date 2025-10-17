<?php
// PHPエラーレポートを有効にする
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../common/dbconnect.php';
require_once '../common/functions.php';

header('Content-Type: application/json');

$pdo = connect();
$input = json_decode(file_get_contents('php://input'), true);

$action = $input['action'] ?? '';

try {
    switch ($action) {
        case 'register_sales':
            // データの登録処理
            $data = $input['data'] ?? [];
            $store_id = $input['store_id'] ?? null;
            if (!$data || !$store_id) {
                echo json_encode(['status' => 'error', 'message' => '必要なデータが不足しています。']);
                exit;
            }

            $success_count = 0;
            $failed_data = [];

            foreach ($data as $row) {
                $cast_name = $row['キャスト名'] ?? null;
                $product_name = $row['商品名'] ?? null;
                $quantity = $row['数量'] ?? 0;
                $order_id = $row['注文ID'] ?? null;
                $order_date_time = $row['注文日時'] ?? null;
                $note = $row['備考'] ?? ''; // NOTEカラムを復活

                if (!$cast_name || !$product_name || !$order_id || !$order_date_time || $quantity === null) {
                    $failed_data[] = $row;
                    continue;
                }

                $cast_id = get_cast_id_by_name($pdo, $cast_name);
                $product_id = get_product_id_by_name($pdo, $product_name);
                
                // 未登録のキャストや商品の場合はステータスを'pending'にする
                $status = ($cast_id === null || $product_id === null) ? 'pending' : 'confirmed';

                // 日付から集計日(eigyo_ymd)を計算
                try {
                    $dt = new DateTime($order_date_time);
                    $eigyo_ymd = $dt->format('Y-m-d');
                } catch (Exception $e) {
                    $eigyo_ymd = '0000-00-00';
                }

                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO base_sales_tbl (order_id, order_day, shop_mst, base_item_id, cast_id, quantity, note, status, eigyo_ymd)
                        VALUES (:order_id, :order_day, :shop_mst, :base_item_id, :cast_id, :quantity, :note, :status, :eigyo_ymd)
                        ON DUPLICATE KEY UPDATE 
                        shop_mst = VALUES(shop_mst), base_item_id = VALUES(base_item_id), cast_id = VALUES(cast_id), quantity = VALUES(quantity), note = VALUES(note), status = VALUES(status), eigyo_ymd = VALUES(eigyo_ymd)
                    ");

                    $stmt->bindValue(':order_id', $order_id);
                    $stmt->bindValue(':order_day', $order_date_time);
                    $stmt->bindValue(':shop_mst', $store_id);
                    $stmt->bindValue(':base_item_id', $product_id);
                    $stmt->bindValue(':cast_id', $cast_id);
                    $stmt->bindValue(':quantity', $quantity);
                    $stmt->bindValue(':note', $note); // NOTEカラムを復活
                    $stmt->bindValue(':status', $status);
                    $stmt->bindValue(':eigyo_ymd', $eigyo_ymd);
                    
                    $stmt->execute();
                    $success_count++;
                } catch (PDOException $e) {
                    // エラーが発生した場合は、その行を失敗データとして扱う
                    $failed_data[] = $row;
                    continue;
                }
            }
            
            if (!empty($failed_data) && $success_count > 0) {
                echo json_encode(['status' => 'partial_success', 'message' => '一部のデータの登録に成功しました。', 'registered_count' => $success_count, 'failed_data' => $failed_data]);
            } elseif ($success_count > 0) {
                echo json_encode(['status' => 'success', 'message' => 'データを正常に登録しました。', 'registered_count' => $success_count]);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'データの登録に失敗しました。']);
            }
            break;

        case 'fetch_sales':
            // データの取得処理（検索機能を含む）
            $params = $input['params'] ?? [];
            
            $sql = "SELECT bs.id, bs.order_id, bs.order_day, bs.shop_mst, p.item_name, p.price, c.cast_name, bs.quantity, bs.note
                    FROM base_sales_tbl bs
                    LEFT JOIN item_mst p ON bs.base_item_id = p.item_id
                    LEFT JOIN cast_mst c ON bs.cast_id = c.cast_id
                    WHERE 1=1";

            $bindings = [];

            if (!empty($params['date'])) {
                $sql .= " AND DATE(bs.order_day) = :order_day";
                $bindings[':order_day'] = $params['date'];
            }
            if (!empty($params['cast_name'])) {
                $sql .= " AND c.cast_name = :cast_name";
                $bindings[':cast_name'] = $params['cast_name'];
            }
            if (!empty($params['keyword'])) {
                $sql .= " AND (bs.order_id LIKE :keyword OR p.item_name LIKE :keyword)";
                $bindings[':keyword'] = '%' . $params['keyword'] . '%';
            }

            $sql .= " ORDER BY bs.order_day DESC, bs.order_id DESC";

            $stmt = $pdo->prepare($sql);
            foreach ($bindings as $key => &$val) {
                $stmt->bindValue($key, $val);
            }
            $stmt->execute();
            $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // 取得したデータを日本語のキーに変換
            $japanese_data = [];
            foreach ($data as $row) {
                $shop_info = get_shop_info($row['shop_mst']);
                $japanese_data[] = [
                    'id' => $row['id'],
                    '注文ID' => $row['order_id'],
                    '注文日時' => $row['order_day'],
                    '店舗名' => $shop_info['name'],
                    '商品名' => $row['item_name'],
                    '価格' => $row['price'],
                    'キャスト名' => $row['cast_name'],
                    '数量' => $row['quantity'],
                    '備考' => $row['note'] // NOTEカラムを復活
                ];
            }
            echo json_encode(['status' => 'success', 'data' => $japanese_data]);
            break;

        case 'update_sales':
            // データの更新処理
            $data = $input['data'] ?? [];
            if (empty($data['id'])) {
                echo json_encode(['status' => 'error', 'message' => '更新するIDが指定されていません。']);
                exit;
            }

            $id = $data['id'];
            $cast_name = $data['cast_name'] ?? null;
            $quantity = $data['quantity'] ?? null;
            $note = $data['note'] ?? null; // NOTEカラムを復活

            $cast_id = get_cast_id_by_name($pdo, $cast_name);
            if ($cast_id === null) {
                // キャスト名が未登録の場合はステータスを'pending'に戻す
                $status = 'pending';
            } else {
                $status = 'confirmed';
            }

            $sql = "UPDATE base_sales_tbl SET cast_id = :cast_id, quantity = :quantity, note = :note, status = :status WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':id', $id);
            $stmt->bindValue(':cast_id', $cast_id);
            $stmt->bindValue(':quantity', $quantity);
            $stmt->bindValue(':note', $note); // NOTEカラムを復活
            $stmt->bindValue(':status', $status);
            $stmt->execute();

            echo json_encode(['status' => 'success', 'message' => 'データを正常に更新しました。']);
            break;

        case 'delete_sales':
            // データの削除処理
            $data = $input['data'] ?? [];
            if (empty($data['id'])) {
                echo json_encode(['status' => 'error', 'message' => '削除するIDが指定されていません。']);
                exit;
            }

            $id = $data['id'];
            $sql = "DELETE FROM base_sales_tbl WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':id', $id);
            $stmt->execute();

            echo json_encode(['status' => 'success', 'message' => 'データを正常に削除しました。']);
            break;

        case 'delete_test_data':
            // テストデータの削除処理
            $sql = "DELETE FROM base_sales_tbl";
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
            echo json_encode(['status' => 'success', 'message' => 'テストデータが正常に削除されました。']);
            break;

        default:
            echo json_encode(['status' => 'error', 'message' => '無効なアクションです。']);
            break;
    }
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'データベースエラー: ' . $e->getMessage()]);
}

// データベースからキャストIDを名前で取得する関数
function get_cast_id_by_name($pdo, $cast_name) {
    $stmt = $pdo->prepare("SELECT cast_id FROM cast_mst WHERE cast_name = :cast_name");
    $stmt->bindValue(':cast_name', $cast_name);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? $result['cast_id'] : null;
}

// データベースから商品IDを名前で取得する関数
function get_product_id_by_name($pdo, $product_name) {
    $stmt = $pdo->prepare("SELECT item_id FROM item_mst WHERE item_name = :product_name");
    $stmt->bindValue(':product_name', $product_name);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? $result['item_id'] : null;
}
?>
