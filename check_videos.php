<?php
/**
 * 動画ファイル整合性チェック
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/common/dbconnect.php';

echo "<h2>動画ファイル整合性チェック</h2>";

$storage_dir = __DIR__ . '/storage/videos';

try {
    $pdo = connect();
    $stmt = $pdo->query("SELECT * FROM video_uploads ORDER BY id DESC LIMIT 5");
    $videos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' cellpadding='8' style='font-family: monospace;'>";
    echo "<tr><th>ID</th><th>UUID</th><th>元ファイル名</th><th>DB登録サイズ</th><th>実サイズ</th><th>ヘッダ確認</th><th>直接リンク</th></tr>";
    
    foreach ($videos as $v) {
        $file = $storage_dir . '/' . $v['file_path'];
        $exists = file_exists($file);
        $actual_size = $exists ? filesize($file) : 0;
        
        // MOV/MP4ファイルの先頭を確認（ftyp atom があるべき）
        $header_check = '❌ 不明';
        if ($exists && $actual_size > 12) {
            $fp = fopen($file, 'rb');
            $header = fread($fp, 12);
            fclose($fp);
            
            // ftyp atom を探す (バイト4-7が "ftyp" であるべき)
            $ftyp_pos = strpos($header, 'ftyp');
            if ($ftyp_pos !== false) {
                $header_check = '✅ 有効なMOV/MP4';
            } else {
                // 最初の8バイトをHEXで表示
                $header_check = '⚠️ 不正: ' . bin2hex(substr($header, 0, 8));
            }
        } elseif (!$exists) {
            $header_check = '❌ ファイルなし';
        }
        
        // サイズ比較
        $size_status = '';
        if ($actual_size == 0) {
            $size_status = '❌ 0バイト';
        } elseif ($actual_size == $v['file_size']) {
            $size_status = '✅ 一致';
        } else {
            $diff = round(($actual_size / $v['file_size']) * 100, 1);
            $size_status = "⚠️ {$diff}%";
        }
        
        $db_size_mb = round($v['file_size'] / 1024 / 1024, 2);
        $actual_size_mb = round($actual_size / 1024 / 1024, 2);
        
        // 直接アクセスリンク（PHP bypass）
        $direct_link = $exists ? "/netpgpos/storage/videos/" . $v['file_path'] : '-';
        
        echo "<tr>";
        echo "<td>{$v['id']}</td>";
        echo "<td>" . substr($v['video_uuid'], 0, 8) . "...</td>";
        echo "<td>" . htmlspecialchars($v['original_filename']) . "</td>";
        echo "<td>{$db_size_mb} MB</td>";
        echo "<td>{$actual_size_mb} MB ({$size_status})</td>";
        echo "<td>{$header_check}</td>";
        echo "<td><a href='{$direct_link}' target='_blank'>直接DL</a></td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<h3>説明</h3>";
    echo "<ul>";
    echo "<li><strong>ヘッダ確認</strong>: MOV/MP4ファイルは先頭に 'ftyp' アトムが必要です</li>";
    echo "<li><strong>直接リンク</strong>: PHPを経由せず直接ファイルにアクセス（これで再生できればPHPの問題）</li>";
    echo "</ul>";
    
} catch (Exception $e) {
    echo "エラー: " . $e->getMessage();
}
?>
