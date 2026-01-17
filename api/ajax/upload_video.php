<?php
/**
 * 動画アップロードAPI
 * - MP4/MOV等の動画ファイルを受け取る
 * - storage/videos に保存する
 * - DBにメタデータを保存する
 * - 閲覧用URLを返す
 */

session_start();
require_once __DIR__ . '/../../../common/config.php';
require_once __DIR__ . '/../classes/base_practical_auto_manager.php';
require_once __DIR__ . '/../../../common/dbconnect.php';

header('Content-Type: application/json; charset=utf-8');

try {
    // 認証チェック (デバッグ用: 一時的に緩和)
    // セッションからcast_idを取得できる場合はそれを使う
    // if (!isset($_SESSION['utype']) && !isset($_SESSION['cast_id'])) {
    //     throw new Exception('認証が必要です');
    // }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('POSTメソッドのみ許可されています');
    }

    // パラメータ取得
    $cast_id = $_POST['cast_id'] ?? null;
    $order_item_id = $_POST['order_item_id'] ?? null; // 任意（紐付け用）

    // cast_idがPOSTになければセッションから取得（フォールバック）
    if (!$cast_id && isset($_SESSION['cast_id'])) {
        $cast_id = $_SESSION['cast_id'];
    }

    if (!$cast_id) {
        throw new Exception('キャストIDが不明です');
    }

    // ファイルアップロードチェック
    if (!isset($_FILES['video_file']) || $_FILES['video_file']['error'] !== UPLOAD_ERR_OK) {
        $msg = 'ファイルアップロードエラー';
        if (isset($_FILES['video_file'])) {
            switch ($_FILES['video_file']['error']) {
                case UPLOAD_ERR_INI_SIZE: $msg .= ': ファイルサイズが大きすぎます(php.ini)'; break;
                case UPLOAD_ERR_FORM_SIZE: $msg .= ': ファイルサイズが大きすぎます(HTML)'; break;
                case UPLOAD_ERR_PARTIAL: $msg .= ': アップロードが中断されました'; break;
                case UPLOAD_ERR_NO_FILE: $msg .= ': ファイルが選択されていません'; break;
            }
        }
        throw new Exception($msg);
    }

    $file = $_FILES['video_file'];
    
    // 拡張子/MimeTypeチェック
    $allowed_types = ['video/mp4', 'video/quicktime', 'video/x-m4v'];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime_type = $finfo->file($file['tmp_name']);

    if (!in_array($mime_type, $allowed_types)) {
        // 拡張子も一応チェック
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['mp4', 'mov', 'm4v'])) {
             throw new Exception('許可されていないファイル形式です: ' . $mime_type);
        }
    }

    // ファイル名生成 (推測不可能なハッシュ)
    $uuid = bin2hex(random_bytes(16)); // 32文字
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $save_filename = $uuid . '.' . $ext;
    
    // 保存先
    $storage_dir = __DIR__ . '/../../storage/videos';
    if (!file_exists($storage_dir)) {
        mkdir($storage_dir, 0777, true);
    }
    
    $save_path = $storage_dir . '/' . $save_filename;

    // 移動
    if (!move_uploaded_file($file['tmp_name'], $save_path)) {
        throw new Exception('ファイルの保存に失敗しました');
    }

    // DB保存
    $pdo = connect(); // dbconnect.phpの関数
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO video_uploads 
            (video_uuid, original_filename, file_path, cast_id, order_item_id, file_size, mime_type, expires_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        // 有効期限: 7日後
        $expires_at = date('Y-m-d H:i:s', strtotime('+7 days'));
        
        $stmt->execute([
            $uuid,
            $file['name'],
            $save_filename, 
            $cast_id,
            $order_item_id, // 追加
            $file['size'],
            $mime_type,
            $expires_at
        ]);
        
    } catch (PDOException $e) {
        // DB保存失敗時はファイルも削除
        unlink($save_path);
        throw new Exception('DB登録エラー: ' . $e->getMessage());
    }

    // 成功レスポンス
    // 生成されるURL: https://.../thanks.php?id=uuid
    // ドメインは config.php の $base_redirect_uri から推定するか、リクエストから構築
    
    // $base_redirect_uri = "https://purplelion51.sakura.ne.jp/netpgpos/api/base_callback_debug.php";
    // ここからドメインとルートパスを抽出する
    // 例: https://purplelion51.sakura.ne.jp/thanks.php
    
    $url_base = "https://" . $_SERVER['HTTP_HOST'];
    $video_url = $url_base . "/thanks.php?id=" . $uuid;

    echo json_encode([
        'success' => true,
        'message' => 'アップロード完了',
        'video_url' => $video_url,
        'original_name' => $file['name'],
        'expires_at' => $expires_at
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
