<?php
// 座席レイアウト更新API
require_once __DIR__ . '/../../../common/config.php';
require_once __DIR__ . '/../../../common/dbconnect.php';
require_once __DIR__ . '/../../../common/functions.php';

header('Content-Type: application/json; charset=UTF-8');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Method not allowed');
    }

    // JSON入力を取得
    $input = json_decode(file_get_contents('php://input'), true);

    if (empty($input['updates']) || !is_array($input['updates'])) {
        throw new Exception('Invalid data');
    }

    $pdo = connect();
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("UPDATE sheet_mst SET x_pos = :x, y_pos = :y, width = :w, height = :h, sheet_name = :name, type = :type WHERE sheet_id = :id");

    foreach ($input['updates'] as $update) {
        $stmt->execute([
            ':x' => $update['x'],
            ':y' => $update['y'],
            ':w' => $update['w'],
            ':h' => $update['h'],
            ':name' => $update['name'],
            ':type' => $update['type'] ?? 'rect',
            ':id' => $update['id']
        ]);
    }

    $pdo->commit();
    echo json_encode(['status' => 'success']);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Result Update Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
}
