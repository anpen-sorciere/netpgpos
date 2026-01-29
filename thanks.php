<?php
/**
 * 動画閲覧ページ (thanks.php)
 * - HTMLページとして動画を表示
 * - 直接再生できない場合はダウンロードリンクを提供
 */

$base_dir = dirname(__DIR__);
require_once $base_dir . '/common/config.php';
require_once $base_dir . '/common/dbconnect.php';

$uuid = $_GET['id'] ?? null;
$mode = $_GET['mode'] ?? 'view'; // view or stream

if (!$uuid) {
    http_response_code(404);
    die('Video ID not found.');
}

try {
    $pdo = connect();
    $stmt = $pdo->prepare("SELECT * FROM video_uploads WHERE video_uuid = ?");
    $stmt->execute([$uuid]);
    $video = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$video) {
        http_response_code(404);
        die('Video not found or deleted.');
    }

    if (strtotime($video['expires_at']) < time()) {
        http_response_code(410);
        die('This video has expired.');
    }

    $file_path = __DIR__ . '/storage/videos/' . $video['file_path'];

    if (!file_exists($file_path)) {
        http_response_code(404);
        die('Video file missing.');
    }

    // ストリーミングモード（動画データを直接配信）
    if ($mode === 'stream') {
        $size = filesize($file_path);
        $mime = $video['mime_type'] ?: 'video/mp4';
        
        // MOV対応: video/mp4として返す
        if ($mime === 'video/quicktime') {
            $mime = 'video/mp4';
        }

        $fp = @fopen($file_path, 'rb');
        if (!$fp) {
            http_response_code(500);
            die('Could not open file.');
        }

        $start = 0;
        $end = $size - 1;

        header("Content-Type: $mime");
        header("Accept-Ranges: bytes");

        if (isset($_SERVER['HTTP_RANGE'])) {
            list(, $range) = explode('=', $_SERVER['HTTP_RANGE'], 2);
            if (strpos($range, ',') === false) {
                $range = explode('-', $range);
                $c_start = (int)$range[0];
                $c_end = (isset($range[1]) && is_numeric($range[1])) ? (int)$range[1] : $end;
                $c_end = min($c_end, $end);
                
                if ($c_start <= $c_end && $c_start < $size) {
                    $start = $c_start;
                    $end = $c_end;
                    $length = $end - $start + 1;
                    fseek($fp, $start);
                    header('HTTP/1.1 206 Partial Content');
                    header("Content-Length: $length");
                    header("Content-Range: bytes $start-$end/$size");
                }
            }
        } else {
            header("Content-Length: $size");
        }

        $buffer = 1024 * 8;
        while (!feof($fp) && ($p = ftell($fp)) <= $end) {
            if ($p + $buffer > $end) {
                $buffer = $end - $p + 1;
            }
            set_time_limit(0);
            echo fread($fp, $buffer);
            flush();
        }
        fclose($fp);
        exit;
    }

    // 表示モード（HTMLページ）
    $stream_url = "thanks.php?id=" . urlencode($uuid) . "&mode=stream";
    $download_url = "thanks.php?id=" . urlencode($uuid) . "&mode=download";
    $filename = htmlspecialchars($video['original_filename']);
    $filesize_mb = round(filesize($file_path) / 1024 / 1024, 1);
    
    // ダウンロードモード
    if ($mode === 'download') {
        $size = filesize($file_path);
        $mime = $video['mime_type'] ?: 'video/mp4';
        header("Content-Type: $mime");
        header("Content-Length: $size");
        header("Content-Disposition: attachment; filename=\"" . $video['original_filename'] . "\"");
        readfile($file_path);
        exit;
    }

} catch (PDOException $e) {
    http_response_code(500);
    die('Database Error');
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thank You Video</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 20px;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        .container {
            background: white;
            border-radius: 20px;
            padding: 30px;
            max-width: 600px;
            width: 100%;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            text-align: center;
        }
        h1 {
            color: #764ba2;
            margin-bottom: 10px;
            font-size: 1.5em;
        }
        .subtitle {
            color: #666;
            margin-bottom: 20px;
            font-size: 0.9em;
        }
        .video-wrapper {
            background: #000;
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 20px;
        }
        video {
            width: 100%;
            max-height: 400px;
        }
        .error-message {
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            display: none;
        }
        .btn {
            display: inline-block;
            padding: 15px 30px;
            border-radius: 30px;
            text-decoration: none;
            font-weight: bold;
            margin: 5px;
            transition: transform 0.2s;
        }
        .btn:hover { transform: scale(1.05); }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .btn-secondary {
            background: #f0f0f0;
            color: #333;
        }
        .file-info {
            color: #999;
            font-size: 0.8em;
            margin-top: 15px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>💝 Thank You Video</h1>
        <p class="subtitle">特別なメッセージをお届けします</p>
        
        <div class="error-message" id="errorMessage">
            <strong>⚠️ お使いのブラウザでは再生できない可能性があります</strong><br>
            下のダウンロードボタンから動画を保存してご覧ください。
        </div>
        
        <div class="video-wrapper">
            <video id="videoPlayer" controls playsinline>
                <source src="<?= $stream_url ?>" type="video/mp4">
                お使いのブラウザは動画再生に対応していません。
            </video>
        </div>
        
        <a href="<?= $download_url ?>" class="btn btn-primary">
            📥 ダウンロード
        </a>
        
        <p class="file-info">
            ファイル: <?= $filename ?> (<?= $filesize_mb ?>MB)
        </p>
    </div>
    
    <script>
        const video = document.getElementById('videoPlayer');
        const errorMsg = document.getElementById('errorMessage');
        
        video.addEventListener('error', function() {
            errorMsg.style.display = 'block';
        });
        
        // iOS Safari対応
        video.addEventListener('loadedmetadata', function() {
            if (video.duration === 0 || isNaN(video.duration)) {
                errorMsg.style.display = 'block';
            }
        });
    </script>
</body>
</html>
