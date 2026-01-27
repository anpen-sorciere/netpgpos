<?php
/**
 * 動画アップロード診断ツール
 * 本番環境のPHP設定とファイルの状態を確認
 */
require_once __DIR__ . '/common/dbconnect.php';

echo "<h2>動画アップロード診断</h2>";

// 1. PHP設定確認
echo "<h3>1. PHP設定</h3>";
echo "<table border='1' cellpadding='5' cellspacing='0'>";
echo "<tr><th>設定</th><th>値</th><th>推奨</th></tr>";

$settings = [
    'upload_max_filesize' => '50M以上',
    'post_max_size' => '50M以上',
    'max_execution_time' => '120秒以上',
    'max_input_time' => '120秒以上',
    'memory_limit' => '128M以上',
];

foreach ($settings as $key => $recommended) {
    $value = ini_get($key);
    $color = '';
    // 簡易的な警告判定
    if (strpos($key, 'size') !== false || $key === 'memory_limit') {
        $bytes = return_bytes($value);
        if ($bytes < 50 * 1024 * 1024) { // 50MB未満は警告
            $color = 'background: #f8d7da;';
        }
    }
    echo "<tr style='{$color}'><td>{$key}</td><td>{$value}</td><td>{$recommended}</td></tr>";
}
echo "</table>";

function return_bytes($val) {
    $val = trim($val);
    $last = strtolower($val[strlen($val)-1]);
    $val = (int)$val;
    switch($last) {
        case 'g': $val *= 1024;
        case 'm': $val *= 1024;
        case 'k': $val *= 1024;
    }
    return $val;
}

// 2. ストレージディレクトリ確認
echo "<h3>2. ストレージディレクトリ</h3>";
$storage_dir = __DIR__ . '/api/storage/videos';
echo "<p>パス: <code>{$storage_dir}</code></p>";
echo "<p>存在: " . (file_exists($storage_dir) ? "✅ はい" : "❌ いいえ") . "</p>";
if (file_exists($storage_dir)) {
    echo "<p>書き込み可能: " . (is_writable($storage_dir) ? "✅ はい" : "❌ いいえ") . "</p>";
    
    // ファイル一覧
    $files = glob($storage_dir . '/*');
    echo "<p>ファイル数: " . count($files) . "</p>";
}

// 3. 最近アップロードされた動画の状態
echo "<h3>3. 最近の動画アップロード (最新5件)</h3>";
try {
    $pdo = connect();
    $stmt = $pdo->query("
        SELECT id, video_uuid, original_filename, file_path, file_size, mime_type, 
               created_at, expires_at
        FROM video_uploads 
        ORDER BY id DESC 
        LIMIT 5
    ");
    $videos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($videos)) {
        echo "<p>アップロードされた動画はありません</p>";
    } else {
        echo "<table border='1' cellpadding='5' cellspacing='0'>";
        echo "<tr><th>ID</th><th>ファイル名</th><th>DBサイズ</th><th>実サイズ</th><th>状態</th><th>MIMEタイプ</th><th>アップロード日時</th></tr>";
        
        foreach ($videos as $v) {
            $actual_path = $storage_dir . '/' . $v['file_path'];
            $exists = file_exists($actual_path);
            $actual_size = $exists ? filesize($actual_path) : 0;
            
            $status = '';
            $color = '';
            if (!$exists) {
                $status = "❌ ファイルなし";
                $color = 'background: #f8d7da;';
            } elseif ($actual_size == 0) {
                $status = "❌ 0バイト(破損)";
                $color = 'background: #f8d7da;';
            } elseif ($actual_size < $v['file_size']) {
                $status = "⚠️ 不完全 (" . round($actual_size / $v['file_size'] * 100, 1) . "%)";
                $color = 'background: #fff3cd;';
            } else {
                $status = "✅ 正常";
            }
            
            echo "<tr style='{$color}'>";
            echo "<td>{$v['id']}</td>";
            echo "<td>" . htmlspecialchars($v['original_filename']) . "</td>";
            echo "<td>" . number_format($v['file_size']) . " bytes</td>";
            echo "<td>" . number_format($actual_size) . " bytes</td>";
            echo "<td>{$status}</td>";
            echo "<td>{$v['mime_type']}</td>";
            echo "<td>{$v['created_at']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>DBエラー: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<hr>";
echo "<p><strong>注意:</strong> このファイルは診断後に削除してください。</p>";
