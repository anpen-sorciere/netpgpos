<?php
// 環境に応じて設定ファイルを選択
if (file_exists(__DIR__ . '/config_local.php') && ($_SERVER['HTTP_HOST'] === 'localhost' || strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false)) {
    // ローカル環境
    require_once __DIR__ . '/config_local.php';
} else {
    // 本番環境
    require_once __DIR__ . '/config.php';
}

// MySQLに接続する関数（この関数のみが接続を確立する）
function connect()
{
    global $host, $user, $password, $dbname, $disable_db_connection;

    // データベース接続が無効化されている場合
    if (isset($disable_db_connection) && $disable_db_connection) {
        echo '<div style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 10px; margin: 10px; border-radius: 5px;">';
        echo '<strong>開発モード:</strong> データベース接続が無効化されています。MySQLを起動してから config_local.php で $disable_db_connection = false; に設定してください。';
        echo '</div>';
        return null;
    }

    try {
        $pdo = new PDO("mysql:host={$host};dbname={$dbname};charset=utf8mb4", $user, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        echo '<div style="background: #f8d7da; border: 1px solid #f5c6cb; padding: 10px; margin: 10px; border-radius: 5px;">';
        echo '<strong>データベース接続エラー:</strong> ' . $e->getMessage();
        echo '<br><strong>解決方法:</strong> XAMPPコントロールパネルでMySQLを起動してください。';
        echo '</div>';
        return null;
    }

    return $pdo;
}