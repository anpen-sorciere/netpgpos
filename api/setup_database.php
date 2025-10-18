<?php
/**
 * データベースセットアップ確認・実行スクリプト
 */
session_start();
require_once __DIR__ . '/../config.php';

echo "<h1>データベースセットアップ確認</h1>";

try {
    // 独立したPDO接続を作成（dbconnect.phpを使用しない）
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
    
    echo "<h2>1. テーブル存在確認</h2>";
    
    // 必要なテーブル一覧
    $required_tables = [
        'system_config',
        'base_api_tokens',
        'system_logs',
        'api_rate_limits',
        'error_statistics'
    ];
    
    $existing_tables = [];
    $missing_tables = [];
    
    foreach ($required_tables as $table) {
        $sql = "SHOW TABLES LIKE ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$table]);
        
        if ($stmt->fetch()) {
            $existing_tables[] = $table;
            echo "✓ {$table}: 存在<br>";
        } else {
            $missing_tables[] = $table;
            echo "✗ {$table}: 不存在<br>";
        }
        $stmt->closeCursor(); // クエリをクリーンアップ
    }
    
    echo "<h2>2. テーブル作成</h2>";
    
    if (!empty($missing_tables)) {
        echo "不足しているテーブルを作成します...<br>";
        
        // SQLファイルを読み込み
        $sql_file = __DIR__ . '/database_practical_setup.sql';
        if (file_exists($sql_file)) {
            $sql_content = file_get_contents($sql_file);
            
            // SQL文を分割して実行（OPTIMIZE TABLEを除外）
            $sql_statements = explode(';', $sql_content);
            
            foreach ($sql_statements as $sql) {
                $sql = trim($sql);
                if (!empty($sql) && !preg_match('/^--/', $sql) && !preg_match('/^OPTIMIZE/i', $sql)) {
                    try {
                        $pdo->exec($sql);
                        echo "✓ SQL実行成功: " . substr($sql, 0, 50) . "...<br>";
                        
                        // テーブル作成の確認
                        if (preg_match('/CREATE TABLE.*?(\w+)/i', $sql, $matches)) {
                            $table_name = $matches[1];
                            echo "&nbsp;&nbsp;テーブル {$table_name} を作成しました<br>";
                        }
                        
                    } catch (PDOException $e) {
                        echo "⚠ SQL実行警告: " . $e->getMessage() . "<br>";
                        echo "&nbsp;&nbsp;失敗したSQL: " . substr($sql, 0, 100) . "...<br>";
                    }
                }
            }
            
            // OPTIMIZE TABLEは無効化（バッファリングエラー回避）
            echo "<br>テーブル最適化はスキップしました（バッファリングエラー回避）<br>";
            
            echo "<br>テーブル作成完了！<br>";
            
            // テーブル作成後の再確認
            echo "<h3>テーブル作成後の確認</h3>";
            foreach ($required_tables as $table) {
                $sql = "SHOW TABLES LIKE ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$table]);
                
                if ($stmt->fetch()) {
                    echo "✓ {$table}: 作成成功<br>";
                } else {
                    echo "✗ {$table}: 作成失敗<br>";
                }
                $stmt->closeCursor();
            }
            
        } else {
            echo "❌ SQLファイルが見つかりません: {$sql_file}<br>";
        }
    } else {
        echo "すべてのテーブルが存在します。<br>";
    }
    
    echo "<h2>3. テーブル構造確認（簡略版）</h2>";
    
    foreach ($required_tables as $table) {
        echo "<h3>{$table} テーブル</h3>";
        try {
            // 簡略版：テーブル存在確認のみ
            $sql = "SHOW TABLES LIKE ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$table]);
            
            if ($stmt->fetch()) {
                echo "✓ {$table}: テーブルが存在します<br>";
                
                // 行数のみ確認
                $count_sql = "SELECT COUNT(*) as count FROM {$table}";
                $count_stmt = $pdo->prepare($count_sql);
                $count_stmt->execute();
                $count_result = $count_stmt->fetch(PDO::FETCH_ASSOC);
                $count_stmt->closeCursor();
                
                echo "&nbsp;&nbsp;レコード数: " . $count_result['count'] . "<br>";
            } else {
                echo "✗ {$table}: テーブルが存在しません<br>";
            }
            $stmt->closeCursor();
            
        } catch (PDOException $e) {
            echo "テーブル確認エラー: " . $e->getMessage() . "<br>";
        }
    }
    
    echo "<h2>4. テストデータ確認（簡略版）</h2>";
    
    // base_api_tokensテーブルのデータ確認（簡略版）
    try {
        $sql = "SELECT COUNT(*) as count FROM base_api_tokens";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();
        
        echo "base_api_tokensテーブルのレコード数: " . $result['count'] . "<br>";
        
        if ($result['count'] > 0) {
            echo "✓ トークンデータが保存されています<br>";
        } else {
            echo "⚠ トークンデータは保存されていません<br>";
        }
        
    } catch (PDOException $e) {
        echo "トークンデータ確認エラー: " . $e->getMessage() . "<br>";
    }
    
    echo "<h2>5. 次のステップ</h2>";
    echo '<a href="test_practical_auto.php" style="background: #007cba; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">認証テストに戻る</a><br>';
    
} catch (PDOException $e) {
    echo "<span style='color: red;'>データベース接続エラー: " . htmlspecialchars($e->getMessage()) . "</span><br>";
}
?>
