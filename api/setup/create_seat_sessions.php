<?php
require_once __DIR__ . '/../../../common/config.php';
require_once __DIR__ . '/../../../common/dbconnect.php';

try {
    $pdo = connect();
    echo "Connected successfully.<br>";

    $sql = "CREATE TABLE IF NOT EXISTS seat_sessions (
        session_id INT AUTO_INCREMENT PRIMARY KEY,
        shop_id INT NOT NULL,
        sheet_id INT NOT NULL,
        customer_name VARCHAR(100),
        people_count INT DEFAULT 1,
        start_time DATETIME NOT NULL,
        end_time DATETIME DEFAULT NULL,
        is_active TINYINT(1) DEFAULT 1,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX (shop_id),
        INDEX (sheet_id),
        INDEX (is_active)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

    $pdo->exec($sql);
    echo "Table 'seat_sessions' created/verified.<br>";

    // Migration: Check if is_new_customer exists
    $stmt = $pdo->query("SHOW COLUMNS FROM seat_sessions LIKE 'is_new_customer'");
    if ($stmt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE seat_sessions ADD COLUMN is_new_customer TINYINT(1) DEFAULT 0");
        echo "Column 'is_new_customer' added.<br>";
    } else {
        echo "Column 'is_new_customer' already exists.<br>";
    }
    
    // session_orders needs to be considered. 
    // Option B from plan: save directly to a session_orders table?
    // Or just use the existing logic where orders are finalized at checkout?
    // The user said "Calculate at the end".
    // However, if they want to "Add orders" during the stay, we need to store them somewhere linked to the session.
    // Let's create `session_orders` table as well to hold temporary orders before they become sales.
    
    $sql2 = "CREATE TABLE IF NOT EXISTS session_orders (
        id INT AUTO_INCREMENT PRIMARY KEY,
        session_id INT NOT NULL,
        item_id INT NOT NULL,
        item_name VARCHAR(255),
        price INT NOT NULL,
        quantity INT DEFAULT 1,
        cast_id INT DEFAULT 0,
        cast_name VARCHAR(100),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (session_id) REFERENCES seat_sessions(session_id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
    
    $pdo->exec($sql2);
    echo "Table 'session_orders' created successfully.<br>";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
