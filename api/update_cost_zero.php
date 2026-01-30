<?php
require_once(__DIR__ . '/../../common/config.php');
require_once(__DIR__ . '/../../common/dbconnect.php');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid method']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$item_id = $input['item_id'] ?? null;

if (!$item_id) {
    echo json_encode(['success' => false, 'message' => 'No item_id provided']);
    exit;
}

try {
    $pdo = connect();
    $stmt = $pdo->prepare("UPDATE item_mst SET cost = 0 WHERE item_id = ?");
    $result = $stmt->execute([$item_id]);

    echo json_encode(['success' => $result]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
