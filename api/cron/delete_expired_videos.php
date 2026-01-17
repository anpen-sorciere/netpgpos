<?php
/**
 * 動画自動削除スクリプト
 * 有効期限(expires_at)が過ぎた動画ファイルを削除する
 * Cronで1日1回程度実行推奨
 */

require_once __DIR__ . '/../../../common/config.php';
require_once __DIR__ . '/../../../common/dbconnect.php';

// CLI実行のみ許可（念のため）
if (php_sapi_name() !== 'cli' && !isset($_GET['force'])) {
    die('CLI access only');
}

echo "Starting video cleanup...\n";

try {
    $pdo = connect();
    
    // 期限切れの動画を取得
    $stmt = $pdo->prepare("SELECT * FROM video_uploads WHERE expires_at < NOW()");
    $stmt->execute();
    $videos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $deleted_count = 0;
    
    // storage/videos のパス (netpgpos/storage/videos)
    $storage_dir = __DIR__ . '/../../storage/videos/';
    
    foreach ($videos as $video) {
        $file_path = $storage_dir . $video['file_path'];
        
        // ファイル削除
        if (file_exists($file_path)) {
            if (unlink($file_path)) {
                echo "Deleted file: {$video['file_path']}\n";
            } else {
                echo "Failed to delete file: {$video['file_path']}\n";
            }
        } else {
             echo "File not found: {$video['file_path']}\n";
        }
        
        // DB削除
        $del_stmt = $pdo->prepare("DELETE FROM video_uploads WHERE id = ?");
        $del_stmt->execute([$video['id']]);
        
        $deleted_count++;
    }
    
    echo "Cleanup finished. Deleted {$deleted_count} video(s).\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
