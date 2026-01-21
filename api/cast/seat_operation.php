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
            $payment_type = $input['payment_type'] ?? 1; // Default Cash
            $adjust_price = $input['adjust_price'] ?? 0;
            $staff_id = $input['staff_id'] ?? 0;
            $issuer_id = $input['issuer_id'] ?? 0;
            
            // 1. Fetch Session
            $stmt = $pdo->prepare("SELECT * FROM seat_sessions WHERE session_id = ?");
            $stmt->execute([$session_id]);
            $session = $stmt->fetch(PDO::FETCH_ASSOC);
            if(!$session) throw new Exception('Session not found');

            // 2. Fetch Orders
            $stmt = $pdo->prepare("SELECT * FROM session_orders WHERE session_id = ?");
            $stmt->execute([$session_id]);
            $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // 3. Generate Receipt ID
            $now = new DateTime();
            $receipt_id = intval($now->format('ymdHis'));
            
            // Dates
            $receipt_day = $now->format('Ymd');
            $start = new DateTime($session['start_time']);
            $in_date = $start->format('Ymd');
            $in_time = $start->format('Hi');
            $out_date = $now->format('Ymd');
            $out_time = $now->format('Hi');
            
            $pdo->beginTransaction();
            
            // 4. Insert Receipt
            $ins = $pdo->prepare("INSERT INTO receipt_tbl (receipt_id, shop_id, sheet_no, receipt_day, in_date, in_time, out_date, out_time, customer_name, issuer_id, staff_id, payment_type, adjust_price) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $ins->execute([
                $receipt_id,
                $shop_id,
                intval($session['sheet_id']),
                $receipt_day,
                $in_date,
                $in_time,
                $out_date,
                $out_time,
                $session['customer_name'],
                $issuer_id,
                $staff_id,
                $payment_type,
                $adjust_price
            ]);
            
            // 5. Insert Details
            $insDetail = $pdo->prepare("INSERT INTO receipt_detail_tbl (shop_id, receipt_id, receipt_day, item_id, quantity, price, cast_id, cast_back_price) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            
            foreach($orders as $order) {
                // Get item info for back price
                $itemSt = $pdo->prepare("SELECT back_price FROM item_mst WHERE item_id = ?");
                $itemSt->execute([$order['item_id']]);
                $itemData = $itemSt->fetch(PDO::FETCH_ASSOC);
                $back = ($itemData['back_price'] ?? 0) * $order['quantity'];
                
                $insDetail->execute([
                    $shop_id,
                    $receipt_id,
                    $receipt_day,
                    $order['item_id'],
                    $order['quantity'], 
                    $order['price'], 
                    $order['cast_id'],
                    $back
                ]);
            }

            // 6. Close Session
            $upd = $pdo->prepare("UPDATE seat_sessions SET is_active = 0, end_time = NOW() WHERE session_id = ?");
            $upd->execute([$session_id]);
            
            $pdo->commit();
            
            echo json_encode(['status' => 'success', 'receipt_id' => $receipt_id]);
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
