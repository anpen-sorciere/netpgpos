<?php
require_once __DIR__ . '/../../common/config.php';

try {
    $pdo = new PDO(
        "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
        $user,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    echo "<pre>";
    
    // base_api_tokens の構造を表示
    echo "--- structure of base_api_tokens ---\n";
    $stmt = $pdo->query("SHOW CREATE TABLE base_api_tokens");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    echo htmlspecialchars($row['Create Table']);
    
    echo "\n\n--- columns of base_api_tokens ---\n";
    $stmt = $pdo->query("SHOW COLUMNS FROM base_api_tokens");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $col) {
        print_r($col);
    }
    
    echo "</pre>";

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
