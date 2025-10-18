<?php
/**
 * データベーステーブル個別作成スクリプト
 * SQLファイルの分割処理を避けて、個別にCREATE文を実行
 */
session_start();
require_once __DIR__ . '/../config.php';

echo "<h1>データベーステーブル個別作成</h1>";

try {
    // 独立したPDO接続を作成
    $pdo = new PDO(
        "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
        $user,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
    
    echo "<h2>1. テーブル個別作成</h2>";
    
    // 必要なテーブルとCREATE文
    $tables = [
        'system_config' => "
            CREATE TABLE IF NOT EXISTS system_config (
                id INT AUTO_INCREMENT PRIMARY KEY,
                key_name VARCHAR(100) UNIQUE NOT NULL,
                value TEXT NOT NULL,
                created_at INT NOT NULL,
                updated_at INT NOT NULL
            )
        ",
        'base_api_tokens' => "
            CREATE TABLE IF NOT EXISTS base_api_tokens (
                id INT AUTO_INCREMENT PRIMARY KEY,
                scope_key VARCHAR(50) UNIQUE NOT NULL,
                access_token TEXT NOT NULL,
                refresh_token TEXT NOT NULL,
                access_expires INT NOT NULL,
                refresh_expires INT NOT NULL,
                created_at INT NOT NULL,
                updated_at INT NOT NULL,
                INDEX idx_scope_key (scope_key),
                INDEX idx_access_expires (access_expires),
                INDEX idx_refresh_expires (refresh_expires)
            )
        ",
        'system_logs' => "
            CREATE TABLE IF NOT EXISTS system_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                event_type VARCHAR(50) NOT NULL,
                message TEXT NOT NULL,
                created_at INT NOT NULL,
                INDEX idx_event_type (event_type),
                INDEX idx_created_at (created_at)
            )
        ",
        'api_rate_limits' => "
            CREATE TABLE IF NOT EXISTS api_rate_limits (
                id INT AUTO_INCREMENT PRIMARY KEY,
                scope_key VARCHAR(50) NOT NULL,
                limit_type ENUM('hour', 'day') NOT NULL,
                request_count INT NOT NULL DEFAULT 0,
                reset_time INT NOT NULL,
                created_at INT NOT NULL,
                updated_at INT NOT NULL,
                UNIQUE KEY unique_scope_limit (scope_key, limit_type),
                INDEX idx_reset_time (reset_time)
            )
        ",
        'error_statistics' => "
            CREATE TABLE IF NOT EXISTS error_statistics (
                id INT AUTO_INCREMENT PRIMARY KEY,
                error_type VARCHAR(100) NOT NULL,
                error_message TEXT NOT NULL,
                scope_key VARCHAR(50),
                http_code INT,
                occurred_at INT NOT NULL,
                INDEX idx_error_type (error_type),
                INDEX idx_scope_key (scope_key),
                INDEX idx_occurred_at (occurred_at)
            )
        "
    ];
    
    $created_count = 0;
    $skipped_count = 0;
    
    foreach ($tables as $table_name => $create_sql) {
        echo "<h3>{$table_name} テーブル</h3>";
        
        try {
            // テーブル存在確認
            $check_sql = "SHOW TABLES LIKE ?";
            $stmt = $pdo->prepare($check_sql);
            $stmt->execute([$table_name]);
            
            if ($stmt->fetch()) {
                echo "✓ {$table_name}: 既に存在します<br>";
                $skipped_count++;
            } else {
                // テーブル作成
                $pdo->exec($create_sql);
                echo "✓ {$table_name}: 作成成功<br>";
                $created_count++;
            }
            $stmt->closeCursor();
            
        } catch (PDOException $e) {
            echo "✗ {$table_name}: 作成失敗 - " . $e->getMessage() . "<br>";
        }
    }
    
    echo "<h2>2. 作成結果</h2>";
    echo "新規作成: {$created_count} テーブル<br>";
    echo "既存スキップ: {$skipped_count} テーブル<br>";
    
    echo "<h2>3. 初期データの挿入</h2>";
    
    // system_configの初期データ
    try {
        $insert_sql = "INSERT IGNORE INTO system_config (key_name, value, created_at, updated_at) VALUES (?, ?, ?, ?)";
        $stmt = $pdo->prepare($insert_sql);
        $stmt->execute(['encryption_key', '', time(), time()]);
        echo "✓ system_config: 初期データ挿入完了<br>";
    } catch (PDOException $e) {
        echo "⚠ system_config: 初期データ挿入失敗 - " . $e->getMessage() . "<br>";
    }
    
    echo "<h2>4. 最終確認</h2>";
    
    $all_tables = ['system_config', 'base_api_tokens', 'system_logs', 'api_rate_limits', 'error_statistics'];
    $existing_tables = 0;
    
    foreach ($all_tables as $table_name) {
        try {
            $check_sql = "SHOW TABLES LIKE ?";
            $stmt = $pdo->prepare($check_sql);
            $stmt->execute([$table_name]);
            
            if ($stmt->fetch()) {
                echo "✓ {$table_name}: 存在<br>";
                $existing_tables++;
                
                // レコード数確認
                $count_sql = "SELECT COUNT(*) as count FROM {$table_name}";
                $count_stmt = $pdo->prepare($count_sql);
                $count_stmt->execute();
                $count_result = $count_stmt->fetch();
                $count_stmt->closeCursor();
                
                echo "&nbsp;&nbsp;レコード数: " . $count_result['count'] . "<br>";
            } else {
                echo "✗ {$table_name}: 不存在<br>";
            }
            $stmt->closeCursor();
            
        } catch (PDOException $e) {
            echo "✗ {$table_name}: 確認エラー - " . $e->getMessage() . "<br>";
        }
    }
    
    echo "<h2>5. 結果</h2>";
    if ($existing_tables === count($all_tables)) {
        echo "<span style='color: green;'>✓ すべてのテーブルが正常に作成されました！</span><br>";
        echo '<a href="test_practical_auto.php" style="background: #007cba; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">認証テストに進む</a><br>';
    } else {
        echo "<span style='color: red;'>✗ 一部のテーブルが作成されていません</span><br>";
    }
    
} catch (PDOException $e) {
    echo "<span style='color: red;'>データベース接続エラー: " . htmlspecialchars($e->getMessage()) . "</span><br>";
}
?>
