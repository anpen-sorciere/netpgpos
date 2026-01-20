<?php
require_once __DIR__ . '/../../../common/dbconnect.php';
try {
    $pdo = connect();
    $stmt = $pdo->query("DESCRIBE receipt_tbl");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $col) {
        if ($col['Field'] === 'sheet_no') {
            print_r($col);
        }
    }
} catch (Exception $e) {
    echo $e->getMessage();
}
