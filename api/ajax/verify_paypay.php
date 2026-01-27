<?php
/**
 * PayPay取引認証API
 * キャストがtransaction_idの全桁を入力して、自分のcast_idを紐付ける
 */
session_start();
require_once __DIR__ . '/../../../common/config.php';

header('Content-Type: application/json; charset=utf-8');

try {
    if (!isset($_SESSION['cast_id'])) {
        throw new Exception('ログインが必要です');
    }
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('POSTメソッドのみサポート');
    }
    
    $input = json_decode(file_get_contents('php://input'), true);
    $paypay_id = $input['paypay_id'] ?? null;
    $transaction_id = $input['transaction_id'] ?? null;
    
    if (!$paypay_id || !$transaction_id) {
        throw new Exception('paypay_idとtransaction_idは必須です');
    }
    
    $cast_id = $_SESSION['cast_id'];
    
    // DB接続
    $pdo = new PDO(
        "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
        $user,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    // 指定されたpaypay_idのレコードを取得して、transaction_idが一致するか確認
    $stmt = $pdo->prepare("
        SELECT id, transaction_id, cast_id 
        FROM paypay_sales 
        WHERE id = ?
    ");
    $stmt->execute([$paypay_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$row) {
        throw new Exception('指定されたPayPayデータが見つかりません');
    }
    
    // 既に他のキャストが認証済みの場合
    if ($row['cast_id'] !== null && $row['cast_id'] != $cast_id) {
        throw new Exception('この取引は既に他のキャストに紐付けられています');
    }
    
    // 既に自分が認証済みの場合
    if ($row['cast_id'] == $cast_id) {
        echo json_encode([
            'success' => true,
            'message' => 'この取引は既にあなたに紐付けられています'
        ]);
        exit;
    }
    
    // transaction_idが完全一致するか確認
    if ($row['transaction_id'] !== $transaction_id) {
        throw new Exception('取引番号が一致しません。正しい取引番号を入力してください。');
    }
    
    // 一致したらcast_idを更新
    $updateStmt = $pdo->prepare("
        UPDATE paypay_sales 
        SET cast_id = ?
        WHERE id = ?
    ");
    $updateStmt->execute([$cast_id, $paypay_id]);
    
    echo json_encode([
        'success' => true,
        'message' => '認証成功！取引があなたに紐付けられました。'
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
