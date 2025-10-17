<?php
// config.phpから接続設定を読み込む（絶対パス指定でどこからでも動作）
require_once '../common/config.php';

// MySQLに接続する関数（この関数のみが接続を確立する）
function connect()
{
    global $host, $user, $password, $dbname;

    try {
        $pdo = new PDO("mysql:host={$host};dbname={$dbname};charset=utf8mb4", $user, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch (PDOException $e) {
        echo 'Connection failed: ' . $e->getMessage();
        exit;
    }

    return $pdo;
}
