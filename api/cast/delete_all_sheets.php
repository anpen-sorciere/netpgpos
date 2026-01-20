<?php
// 座席全削除API
require_once __DIR__ . '/../../../common/config.php';
require_once __DIR__ . '/../../../common/dbconnect.php';
require_once __DIR__ . '/../../../common/functions.php';

header('Content-Type: application/json; charset=UTF-8');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Method not allowed');
    }

    $input = json_decode(file_get_contents('php://input'), true);

    if (empty($input['shop_id'])) {
        throw new Exception('Shop ID is required');
    }

    $pdo = connect();
    $stmt = $pdo->prepare("DELETE FROM sheet_mst WHERE shop_id = ?");
    $stmt->execute([$input['shop_id']]);

    echo json_encode(['status' => 'success']);

} catch (Exception $e) {
    error_log("Delete All Sheets Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
}
