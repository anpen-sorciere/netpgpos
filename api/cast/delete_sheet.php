<?php
// 座席削除API
require_once __DIR__ . '/../../../common/config.php';
require_once __DIR__ . '/../../../common/dbconnect.php';
require_once __DIR__ . '/../../../common/functions.php';

header('Content-Type: application/json; charset=UTF-8');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Method not allowed');
    }

    $input = json_decode(file_get_contents('php://input'), true);

    if (empty($input['sheet_id']) || empty($input['shop_id'])) {
        throw new Exception('Sheet ID and Shop ID are required');
    }

    $pdo = connect();
    $stmt = $pdo->prepare("DELETE FROM sheet_mst WHERE sheet_id = ? AND shop_id = ?");
    $stmt->execute([$input['sheet_id'], $input['shop_id']]);

    if ($stmt->rowCount() > 0) {
        echo json_encode(['status' => 'success']);
    } else {
        throw new Exception('Delete failed or sheet not found');
    }

} catch (Exception $e) {
    error_log("Delete Sheet Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
