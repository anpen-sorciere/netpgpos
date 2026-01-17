<?php
/**
 * 動画アップロード機能 セットアップスクリプト (v2)
 */

ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h2>動画機能セットアップ開始 (v2)</h2>";

// 環境非依存のパス設定 (debug/api/netpgpos/root)
// 3階層上がルート (netpgposディレクトリがルート直下にある想定)
$root_dir = dirname(__DIR__, 3); 
$common_dir = $root_dir . '/common';

$config_path = $common_dir . '/config.php';
$dbconnect_path = $common_dir . '/dbconnect.php';

echo "Root Dir: " . $root_dir . "<br>";
echo "Config Path: " . $config_path . "<br>";

if (!file_exists($config_path)) {
    // 4階層上も試す（念の為）
    $root_dir_v2 = dirname(__DIR__, 4);
    $config_path_v2 = $root_dir_v2 . '/common/config.php';
    if (file_exists($config_path_v2)) {
        $root_dir = $root_dir_v2;
        $common_dir = $root_dir . '/common';
        $config_path = $config_path_v2;
        $dbconnect_path = $common_dir . '/dbconnect.php';
        echo "Found in parent: " . $config_path . "<br>";
    } else {
        die("❌ config.php が見つかりません。<br>Searching: $config_path OR $config_path_v2");
    }
}

require_once $config_path;
// require_once $dbconnect_path; // config内で呼ばれている場合もあるが、明示的に呼ぶ

// dbconnect.phpの読み込み
if (file_exists($dbconnect_path)) {
    require_once $dbconnect_path;
} else {
    // netpgpos内にcommonがあるパターンもケア
    $local_common = dirname(__DIR__, 2) . '/common/dbconnect.php';
    if (file_exists($local_common)) {
        require_once $local_common;
    } else {
        die("❌ dbconnect.php が見つかりません");
    }
}

echo "Config loaded.<br>";

// 1. DBテーブル作成
try {
    // connect()関数があればそれを使う、なければconfigの値で接続
    if (function_exists('connect')) {
        $pdo = connect();
    } else {
        $pdo = new PDO(
            "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
            $user,
            $password,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    }

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
// storageは netpgpos/storage に配置 (netpgposディレクトリがルート直下にある想定)
$base_dir = $root_dir . '/storage';
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
