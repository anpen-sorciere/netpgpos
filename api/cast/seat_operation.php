<?php
// 座席オペレーションAPI (Check-in, Order, Checkout)
require_once __DIR__ . '/../../../common/config.php';
require_once __DIR__ . '/../../../common/dbconnect.php';
require_once __DIR__ . '/../../../common/functions.php';

header('Content-Type: application/json; charset=UTF-8');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Method not allowed');
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) throw new Exception('Invalid JSON');
    
    $action = $input['action'] ?? '';
    $shop_id = $input['shop_id'] ?? 0;
    
    if (!$shop_id) throw new Exception('Shop ID required');
    
    $pdo = connect();
    
    switch ($action) {
        case 'get_status':
            // 全座席の現在ステータス取得
            $stmt = $pdo->prepare("
                SELECT s.*, 
                       (SELECT SUM(price * quantity) FROM session_orders WHERE session_id = s.session_id) as current_order_total 
                FROM seat_sessions s 
                WHERE s.shop_id = ? AND s.is_active = 1
            ");
            $stmt->execute([$shop_id]);
            $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // 各セッションの詳細オーダーも取得する場合
            /*
            foreach($sessions as &$sess) {
                $oH = $pdo->prepare("SELECT * FROM session_orders WHERE session_id = ?");
                $oH->execute([$sess['session_id']]);
                $sess['orders'] = $oH->fetchAll(PDO::FETCH_ASSOC);
            }
            */
            
            echo json_encode(['status' => 'success', 'sessions' => $sessions]);
            break;

        case 'checkin':
            $sheet_id = $input['sheet_id'];
            $name = $input['customer_name'] ?? 'Guest';
            $people = $input['people_count'] ?? 1;
            
            // 入店時刻の指定があればそれを使用、なければ現在時刻
            $start_time = $input['start_time'] ?? date('Y-m-d H:i:s');
            
            // 既にアクティブならエラー
            $check = $pdo->prepare("SELECT session_id FROM seat_sessions WHERE sheet_id = ? AND is_active = 1");
            $check->execute([$sheet_id]);
            if ($check->fetch()) throw new Exception('Seat is already occupied');
            
            $stmt = $pdo->prepare("INSERT INTO seat_sessions (shop_id, sheet_id, customer_name, people_count, start_time) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$shop_id, $sheet_id, $name, $people, $start_time]);
            
            echo json_encode(['status' => 'success', 'session_id' => $pdo->lastInsertId()]);
            break;

        case 'add_order':
            $session_id = $input['session_id'];
            $items = $input['items'] ?? []; // Array of {item_id, name, price, qty, cast_id...}
            
            if(empty($items)) throw new Exception('No items to add');
            
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("INSERT INTO session_orders (session_id, item_id, item_name, price, quantity, cast_id, cast_name) VALUES (?, ?, ?, ?, ?, ?, ?)");
            
            foreach($items as $item) {
                $stmt->execute([
                    $session_id,
                    $item['id'],
                    $item['name'],
                    $item['price'],
                    $item['qty'],
                    $item['castId'] ?? 0,
                    $item['castName'] ?? ''
                ]);
            }
            $pdo->commit();
            echo json_encode(['status' => 'success']);
            break;

        case 'checkout':
            $session_id = $input['session_id'];
            
            // トランザクション処理（売上テーブルへの移動など）は本来複雑だが、
            // ここでは既存の receipt_input_tablet.php の submitReceipt 相当のことをやりたい。
            // しかし、submitReceipt は form post で receipt_check.php に投げる仕様。
            // 完全にAPI化するか、あるいは「仮締め」して is_active=0 にするか。
            // Plan: Calculate total, return it for confirmation? Or finalize immediately?
            // "Confirm amount -> Seat becomes Empty"
            
            // 簡易実装: active=0 にして、売上データ(sales table)へ移行する。
            // salesテーブルの構造に合わせてInsertが必要。
            // 既存の functions.php の register_sales() 的なものを呼べればベストだが...
            // ここでは session を閉じるだけに止めて、売上登録は別途実装が必要かも。
            // いったん「席を空ける」処理のみ実装。
            
            $pdo->beginTransaction();
            $upd = $pdo->prepare("UPDATE seat_sessions SET is_active = 0, end_time = NOW() WHERE session_id = ?");
            $upd->execute([$session_id]);
            $pdo->commit();
            
            echo json_encode(['status' => 'success']);
            break;
            
        case 'get_session_details':
             $session_id = $input['session_id'];
             $stmt = $pdo->prepare("SELECT * FROM seat_sessions WHERE session_id = ?");
             $stmt->execute([$session_id]);
             $session = $stmt->fetch(PDO::FETCH_ASSOC);
             
             $ord = $pdo->prepare("SELECT * FROM session_orders WHERE session_id = ?");
             $ord->execute([$session_id]);
             $orders = $ord->fetchAll(PDO::FETCH_ASSOC);
             
             echo json_encode(['status' => 'success', 'session' => $session, 'orders' => $orders]);
             break;

        default:
            throw new Exception('Unknown action');
    }

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Seat Operation Error: " . $e->getMessage());
    http_response_code(400); // Bad Request
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
