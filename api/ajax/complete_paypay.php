<?php
/**
 * PayPay取引対応完了API
 * キャストが取引を「対応済み」にするためのAPI
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
    
    if (!$paypay_id) {
        throw new Exception('paypay_idは必須です');
    }
    
    $cast_id = $_SESSION['cast_id'];
    
    // DB接続
    $pdo = new PDO(
        "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
        $user,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    // 自分のcast_idが紐付いているか確認
    $stmt = $pdo->prepare("
        SELECT id, cast_id, handled_flg 
        FROM paypay_sales 
        WHERE id = ?
    ");
    $stmt->execute([$paypay_id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$row) {
        throw new Exception('指定されたPayPayデータが見つかりません');
    }
    
    // 自分のcast_idでなければ対応不可
    if ($row['cast_id'] != $cast_id) {
        throw new Exception('この取引はあなたに紐付けられていません');
    }
    
    // 既に対応済みの場合
    if ($row['handled_flg'] == 1) {
        echo json_encode([
            'success' => true,
            'message' => 'この取引は既に対応済みです'
        ]);
        exit;
    }
    
    // 対応済みフラグを更新
    $updateStmt = $pdo->prepare("
        UPDATE paypay_sales 
        SET handled_flg = 1
        WHERE id = ?
    ");
    $updateStmt->execute([$paypay_id]);
    
    echo json_encode([
        'success' => true,
        'message' => '対応完了しました！'
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
