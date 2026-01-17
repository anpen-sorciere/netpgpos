<?php
/**
 * 動画閲覧用スクリプト (thanks.php)
 * - セキュリティのため、Webルート(htdocs)直下に配置
 * - DBから動画情報を取得し、storage/videos内のファイルを配信する
 * - Rangeリクエストに対応 (シークバー対応)
 */

// 1. 設定ファイル読み込み
// このファイルは netpgpos/thanks.php に配置されているため、1つ上がWebルート
$base_dir = dirname(__DIR__);
require_once $base_dir . '/common/config.php';
require_once $base_dir . '/common/dbconnect.php';

// ID取得
$uuid = $_GET['id'] ?? null;

if (!$uuid) {
    http_response_code(404);
    die('Video ID not found.');
}

// 2. DBから情報取得
try {
    $pdo = connect();
    $stmt = $pdo->prepare("SELECT * FROM video_uploads WHERE video_uuid = ?");
    $stmt->execute([$uuid]);
    $video = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$video) {
        http_response_code(404);
        die('Video not found or deleted.');
    }

    // 有効期限チェック
    if (strtotime($video['expires_at']) < time()) {
        http_response_code(410); // Gone
        die('This video has expired.');
    }

    // 3. ファイルパス特定
    // storage/videos は netpgpos/storage/videos にある
    $file_path = $base_dir . '/netpgpos/storage/videos/' . $video['file_path'];

    if (!file_exists($file_path)) {
        http_response_code(404);
        die('Video file missing.');
    }

    // 4. 動画配信 (Range対応)
    $size = filesize($file_path);
    $mime = $video['mime_type'] ?: 'video/mp4';
    $filename = $video['original_filename'] ?: 'video.mp4';

    // ダウンロードカウント加算 (非同期または単純加算)
    // Rangeリクエストのたびにカウントすると増えすぎるので、セッション制御等が必要だが
    // 簡易的に「最初のアクセス」だけカウントするか、あるいは気にせずカウントするか。
    // ここでは厳密なカウントは省略（負荷軽減のためUpdateは最後にやるか、cronで集計するか...）
    // とりあえず今回は実装しない。

    $fp = @fopen($file_path, 'rb');
    if (!$fp) {
        http_response_code(500);
        die('Could not open file.');
    }

    // Rangeヘッダー処理
    $start = 0;
    $end = $size - 1;

    header("Content-Type: $mime");
    header("Accept-Ranges: bytes");

    if (isset($_SERVER['HTTP_RANGE'])) {
        $c_start = $start;
        $c_end = $end;

        list(, $range) = explode('=', $_SERVER['HTTP_RANGE'], 2);
        if (strpos($range, ',') !== false) {
            header('HTTP/1.1 416 Requested Range Not Satisfiable');
            header("Content-Range: bytes $start-$end/$size");
            exit;
        }

        if ($range == '-') {
            $c_start = $size - substr($range, 1);
        } else {
            $range = explode('-', $range);
            $c_start = $range[0];
            $c_end = (isset($range[1]) && is_numeric($range[1])) ? $range[1] : $c_end;
        }

        $c_end = ($c_end > $end) ? $end : $c_end;

        if ($c_start > $c_end || $c_start > $size - 1 || $c_end >= $size) {
            header('HTTP/1.1 416 Requested Range Not Satisfiable');
            header("Content-Range: bytes $start-$end/$size");
            exit;
        }

        $start = $c_start;
        $end = $c_end;
        $length = $end - $start + 1;

        fseek($fp, $start);
        header('HTTP/1.1 206 Partial Content');
        header("Content-Length: $length");
        header("Content-Range: bytes $start-$end/$size");
    } else {
        header("Content-Length: $size");
    }
    
    // ダウンロードさせる場合
    // header("Content-Disposition: attachment; filename=\"$filename\""); 
    // インライン再生させる場合
    header("Content-Disposition: inline; filename=\"$filename\"");

    // バッファ出力
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

} catch (PDOException $e) {
    http_response_code(500);
    die('Database Error');
}
