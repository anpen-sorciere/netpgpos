<?php
require_once(__DIR__ . '/../common/config.php');
require_once(__DIR__ . '/../common/dbconnect.php');

try {
    $pdo = connect();
    // staff_id
    try {
        $pdo->exec("ALTER TABLE seat_sessions ADD COLUMN staff_id INT DEFAULT 0");
        echo "Added staff_id.\n";
    } catch (PDOException $e) {
        echo "staff_id might already exist.\n";
    }

    // issuer_id
    try {
        $pdo->exec("ALTER TABLE seat_sessions ADD COLUMN issuer_id INT DEFAULT 0");
        echo "Added issuer_id.\n";
    } catch (PDOException $e) {
        echo "issuer_id might already exist.\n";
    }

} catch (PDOException $e) {
    echo "Connection Error: " . $e->getMessage();
}
