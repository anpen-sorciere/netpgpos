<?php
// エラー表示を有効化
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../common/dbconnect.php';

try {
    // データベース接続
    $pdo = connect();
    
    // スーパーチャットテーブル作成SQLを読み込み
    $sql = file_get_contents('sql/superchat_table.sql');
    
    if ($sql === false) {
        throw new Exception('SQLファイルの読み込みに失敗しました。');
    }
    
    // SQLを実行
    $pdo->exec($sql);
    
    echo "スーパーチャットテーブルの作成が完了しました。\n";
    echo "テーブル名: superchat_tbl\n";
    
} catch (PDOException $e) {
    echo "データベースエラー: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "エラー: " . $e->getMessage() . "\n";
}
?>
