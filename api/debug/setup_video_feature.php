<?php
/**
 * 動画アップロード機能 セットアップスクリプト (v2)
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h2>動画機能セットアップ開始 (v2)</h2>";

// 絶対パスで指定
$common_dir = 'C:/xampp/htdocs/common';
$config_path = $common_dir . '/config.php';
$dbconnect_path = $common_dir . '/dbconnect.php';

echo "Config Path: " . $config_path . "<br>";

if (!file_exists($config_path)) {
    die("❌ config.php が見つかりません: $config_path");
}

require_once $config_path;

echo "Config loaded. Host: " . ($host ?? 'UNDEFINED') . "<br>";

require_once $dbconnect_path;

// 1. DBテーブル作成
try {
    echo "Connecting to DB...<br>";
    $pdo = new PDO(
        "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
        $user,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    echo "DB Connected.<br>";

    $sql = "CREATE TABLE IF NOT EXISTS video_uploads (
        id INT AUTO_INCREMENT PRIMARY KEY,
        video_uuid VARCHAR(64) NOT NULL UNIQUE COMMENT 'URL用ID',
        original_filename VARCHAR(255) NOT NULL,
        file_path VARCHAR(255) NOT NULL COMMENT 'storage/videos/からの相対パス',
        cast_id INT NOT NULL,
        file_size INT,
        mime_type VARCHAR(50),
        downloads INT DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        expires_at DATETIME NOT NULL COMMENT '自動削除日時',
        INDEX idx_video_uuid (video_uuid),
        INDEX idx_expires_at (expires_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

    $pdo->exec($sql);
    echo "✅ DBテーブル `video_uploads` を作成（または確認）しました。<br>";

} catch (PDOException $e) {
    echo "❌ DBエラー: " . $e->getMessage() . "<br>";
    exit;
}

// 2. ディレクトリ作成
$base_dir = 'C:/xampp/htdocs/netpgpos/storage';
$video_dir = $base_dir . '/videos';

echo "Base Dir: $base_dir<br>";

if (!file_exists($base_dir)) {
    if (mkdir($base_dir, 0777, true)) {
        echo "✅ `storage` ディレクトリを作成しました。<br>";
    } else {
        echo "❌ `storage` ディレクトリの作成に失敗しました。<br>";
    }
}

if (!file_exists($video_dir)) {
    if (mkdir($video_dir, 0777, true)) {
        echo "✅ `storage/videos` ディレクトリを作成しました。<br>";
    } else {
        echo "❌ `storage/videos` ディレクトリの作成に失敗しました。<br>";
    }
} else {
    echo "ℹ️ `storage/videos` は既に存在します。<br>";
}

// 3. .htaccess配置
$htaccess_path = $video_dir . '/.htaccess';
$htaccess_content = "Order Deny,Allow\nDeny from all\n";

if (file_put_contents($htaccess_path, $htaccess_content)) {
    echo "✅ `.htaccess` を設置しました（直接アクセス禁止）。<br>";
} else {
    echo "❌ `.htaccess` の設置に失敗しました。<br>";
}

echo "<hr>";
echo "<strong>セットアップ完了！</strong><br>";
