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
            
            echo json_encode(['status' => 'success', 'sessions' => $sessions]);
            break;

        case 'get_session_details':
            $session_id = $input['session_id'];
            // Fetch session orders
            $stmt = $pdo->prepare("SELECT * FROM session_orders WHERE session_id = ? ORDER BY created_at ASC");
            $stmt->execute([$session_id]);
            $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode(['status' => 'success', 'orders' => $orders]);
            break;

        case 'checkin':
            $sheet_id = $input['sheet_id'];
            $name = $input['customer_name'] ?? '';
            $people = $input['people_count'] ?? 1;
            
            // 入店時刻の指定があればそれを使用、なければ現在時刻
            $start_time = $input['start_time'] ?? date('Y-m-d H:i:s');
            $is_new_customer = $input['is_new_customer'] ?? 0;
            
            // 既にアクティブならエラー
            $check = $pdo->prepare("SELECT session_id FROM seat_sessions WHERE sheet_id = ? AND is_active = 1");
            $check->execute([$sheet_id]);
            if ($check->fetch()) throw new Exception('Seat is already occupied');
            
            $stmt = $pdo->prepare("INSERT INTO seat_sessions (shop_id, sheet_id, customer_name, people_count, start_time, is_new_customer) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$shop_id, $sheet_id, $name, $people, $start_time, $is_new_customer]);
            
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
            $is_new_customer = $input['is_new_customer'] ?? 0;
            
            // 1. Fetch Session
            $stmt = $pdo->prepare("SELECT * FROM seat_sessions WHERE session_id = ?");
            $stmt->execute([$session_id]);
            $session = $stmt->fetch(PDO::FETCH_ASSOC);
            if(!$session) throw new Exception('Session not found');

            // 2. Fetch Orders
            $stmt = $pdo->prepare("SELECT * FROM session_orders WHERE session_id = ?");
            $stmt->execute([$session_id]);
            $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // 3. Generate Receipt ID and Times
            $now = new DateTime();
            
            // Check if custom time provided
            if (!empty($input['checkout_time'])) {
                // Input format YYYY-MM-DDTHH:mm
                // Replace T with space
                $customTimeStr = str_replace('T', ' ', $input['checkout_time']);
                $calcTime = new DateTime($customTimeStr);
            } else {
                $calcTime = $now;
            }

            $receipt_id = intval($now->format('ymdHis')); // ID uses actual creation time to avoid collision? Or usage time? Usually ID is unique, so creation time is safer. Or use calcTime. Let's keep ID as creation time for uniqueness.
            
            // Dates for Record
            $receipt_day = $calcTime->format('Ymd');
            $start = new DateTime($session['start_time']);
            $in_date = $start->format('Ymd');
            $in_time = $start->format('Hi');
            $out_date = $calcTime->format('Ymd');
            $out_time = $calcTime->format('Hi');
            
            $pdo->beginTransaction();

            // 4. Insert Receipt
            $ins = $pdo->prepare("INSERT INTO receipt_tbl (receipt_id, shop_id, sheet_no, receipt_day, in_date, in_time, out_date, out_time, customer_name, issuer_id, staff_id, payment_type, adjust_price, rep_id, is_new_customer) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
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
                $adjust_price,
                0, // rep_id
                $is_new_customer
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
            $debugLog = "Input: " . print_r($input, true);
            $endTime = 'NOW()';
            $params = [$session_id];
            
            if (!empty($input['checkout_time'])) {
                $endTime = '?';
                $formattedTime = str_replace('T', ' ', $input['checkout_time']) . ':00'; // Append seconds
                $params = array_merge([$formattedTime], $params);
                $updSql = "UPDATE seat_sessions SET is_active = 0, end_time = ? WHERE session_id = ?";
                $debugLog .= "\nUsing Custom Time: $formattedTime";
            } else {
                $updSql = "UPDATE seat_sessions SET is_active = 0, end_time = NOW() WHERE session_id = ?";
                $debugLog .= "\nUsing NOW() - Input empty";
            }
            
            file_put_contents(__DIR__ . '/debug_checkout.txt', $debugLog . "\nSQL: $updSql\nParams: " . print_r($params, true) . "\n-----------------\n", FILE_APPEND);

            $upd = $pdo->prepare($updSql);
            $upd->execute($params);
            
            $pdo->commit();
            
            echo json_encode(['status' => 'success', 'receipt_id' => $receipt_id]);
            break;
            
        case 'get_session_details':
             $session_id = $input['session_id'];
             $stmt = $pdo->prepare("SELECT * FROM seat_sessions WHERE session_id = ?");
             $stmt->execute([$session_id]);
             $session = $stmt->fetch(PDO::FETCH_ASSOC);
             
             // Get Orders with Cast Name
             $ord = $pdo->prepare("
                SELECT o.*, i.item_name, i.price, c.cast_name 
                FROM session_orders o
                LEFT JOIN item_mst i ON o.item_id = i.item_id
                LEFT JOIN cast_mst c ON o.cast_id = c.cast_id
                WHERE o.session_id = ?
             ");
             $ord->execute([$session_id]);
             $orders = $ord->fetchAll(PDO::FETCH_ASSOC);
             
             echo json_encode(['status' => 'success', 'session' => $session, 'orders' => $orders]);
             break;

        case 'cancel_session':
            $session_id = $input['session_id'];
            
            // Check session exists and is active
            $stmt = $pdo->prepare("SELECT * FROM seat_sessions WHERE session_id = ? AND is_active = 1");
            $stmt->execute([$session_id]);
            $session = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if(!$session) throw new Exception('Active session not found');
            
            // Log cancellation (optional, but good for safety)
            $logMsg = "Session Cancelled: ID={$session_id}, Sheet={$session['sheet_id']}, Customer={$session['customer_name']}";
            error_log($logMsg);

            // Deactivate session
            $upd = $pdo->prepare("UPDATE seat_sessions SET is_active = 0, end_time = NOW() WHERE session_id = ?");
            $upd->execute([$session_id]);
            
            echo json_encode(['status' => 'success']);
            break;

        case 'delete_session_order':
            $order_id = $input['order_id'];
            $session_id = $input['session_id'];
            
            // Log deletion
            error_log("Deleting Session Order: ID={$order_id}, Session={$session_id}");
            
            // Delete only if session matches (security check)
            $del = $pdo->prepare("DELETE FROM session_orders WHERE id = ? AND session_id = ?");
            $del->execute([$order_id, $session_id]);
            
            if ($del->rowCount() > 0) {
                echo json_encode(['status' => 'success']);
            } else {
                throw new Exception('Order item not found or already deleted');
            }
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
