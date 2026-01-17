require_once 'c:/xampp/htdocs/common/config.php';

try {
    $pdo = new PDO(
        "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
        $user,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM base_orders WHERE status = '対応中'");
    echo "Count: " . $stmt->fetchColumn() . "\n";
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>
