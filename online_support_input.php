<?php
// online_support_input.php

// AJAXリクエスト処理（既存データ取得）- 最初に処理して出力を制御
if (isset($_GET['ajax']) && $_GET['ajax'] == '1' && isset($_GET['cast_id']) && isset($_GET['online_ym'])) {
    // すべての出力バッファをクリア
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // 出力バッファを開始して、すべての出力をキャプチャ
    ob_start();
    
    // エラー表示を抑制（最初に実行）
    $old_error_reporting = error_reporting(0);
    $old_display_errors = ini_set('display_errors', 0);
    $old_html_errors = ini_set('html_errors', 0);
    
    try {
        // 共通関数の読み込み（エラーを抑制）
        @require_once(__DIR__ . '/../common/config.php');
        @require_once(__DIR__ . '/../common/dbconnect.php');
        @require_once(__DIR__ . '/../common/functions.php');
        
        // 読み込み中に出力されたものをすべてクリア
        ob_clean();
        
        // データベース接続
        $pdo = @connect();
        
        if (!$pdo) {
            throw new Exception('Database connection failed');
        }
        
        $ajax_cast_id = (int)$_GET['cast_id'];
        $ajax_online_ym_input = $_GET['online_ym'];
        // YYYY-MM形式をYYYYMM形式に変換
        $ajax_online_ym = str_replace('-', '', $ajax_online_ym_input);
        
        $sql_ajax = "SELECT * FROM online_month WHERE cast_id = :cast_id AND online_ym = :online_ym";
        $stmt_ajax = $pdo->prepare($sql_ajax);
        $stmt_ajax->bindValue(':cast_id', $ajax_cast_id, PDO::PARAM_INT);
        $stmt_ajax->bindValue(':online_ym', $ajax_online_ym, PDO::PARAM_STR);
        $stmt_ajax->execute();
        $ajax_data = $stmt_ajax->fetch(PDO::FETCH_ASSOC);
        
        @disconnect($pdo);
        
        // 出力バッファをクリアしてからJSONを出力
        ob_clean();
        
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-cache, must-revalidate');
        
        if ($ajax_data) {
            echo json_encode([
                'success' => true,
                'data' => [
                    'online_amount' => (int)$ajax_data['online_amount'],
                    'is_paid' => (int)$ajax_data['is_paid'],
                    'paid_date' => ($ajax_data['paid_date'] && $ajax_data['paid_date'] != '0000-00-00') ? $ajax_data['paid_date'] : null
                ],
                'debug' => [
                    'cast_id' => $ajax_cast_id,
                    'online_ym' => $ajax_online_ym,
                    'found' => true
                ]
            ], JSON_UNESCAPED_UNICODE);
        } else {
            echo json_encode([
                'success' => false,
                'data' => null,
                'debug' => [
                    'cast_id' => $ajax_cast_id,
                    'online_ym' => $ajax_online_ym,
                    'online_ym_input' => $ajax_online_ym_input,
                    'found' => false
                ]
            ], JSON_UNESCAPED_UNICODE);
        }
    } catch (Exception $e) {
        // エラーが発生した場合もJSONで返す
        ob_clean();
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-cache, must-revalidate');
        echo json_encode([
            'success' => false,
            'error' => 'Database error occurred',
            'data' => null
        ], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        // PHP 7+ のエラーもキャッチ
        ob_clean();
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-cache, must-revalidate');
        echo json_encode([
            'success' => false,
            'error' => 'Error occurred',
            'data' => null
        ], JSON_UNESCAPED_UNICODE);
    } finally {
        // エラー設定を復元
        if ($old_error_reporting !== false) {
            error_reporting($old_error_reporting);
        }
        if ($old_display_errors !== false) {
            ini_set('display_errors', $old_display_errors);
        }
        if ($old_html_errors !== false) {
            ini_set('html_errors', $old_html_errors);
        }
    }
    exit;
}

// 出力バッファを開始（BOMを防ぐため）
ob_start();

// 共通関数の読み込み
require_once(__DIR__ . '/../common/config.php');
require_once(__DIR__ . '/../common/dbconnect.php');
require_once(__DIR__ . '/../common/functions.php');

// セッション開始
session_start();

// HTMLエスケープ処理 (functions.phpに存在しないため、このファイルに定義)
if (!function_exists('h')) {
    function h($str)
    {
        return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
    }
}

// データベース接続
$pdo = connect();

// 変数初期化
$id = null;
$cast_id = '';
$online_ym = date('Y-m'); // YYYY-MM形式（HTMLのmonth input用）
// GETパラメータまたはPOST後の値で年月を取得
if (isset($_GET['filter_month'])) {
    $online_ym = $_GET['filter_month'];
} elseif (isset($_POST['online_ym'])) {
    $online_ym = $_POST['online_ym'];
}
// GETパラメータでcast_idが渡されている場合（年月変更時に保持）
if (isset($_GET['cast_id']) && !isset($_POST['cast_id'])) {
    $cast_id = (int)$_GET['cast_id'];
}
$online_amount = '';
$is_paid = 0;
$paid_date = date('Y-m-d');
$action = 'create';
$message = '';
$online_data = [];
$filter_month_ym = str_replace('-', '', $online_ym); // フィルタ用のYYYYMM形式

// GETパラメータでcast_idと年月が渡されている場合、既存データを読み込む（編集モードでない場合のみ、GETリクエスト時のみ）
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $action === 'create') {
    // cast_idが空の場合はGETパラメータから取得を試みる
    if (empty($cast_id) && isset($_GET['cast_id']) && $_GET['cast_id'] !== '') {
        $cast_id = (int)$_GET['cast_id'];
    }
    
    // cast_idが数値で、filter_month_ymが存在する場合、既存データを読み込む
    if (!empty($cast_id) && is_numeric($cast_id) && $cast_id > 0 && !empty($filter_month_ym) && strlen($filter_month_ym) === 6) {
        $sql_preload = "SELECT * FROM online_month WHERE cast_id = :cast_id AND online_ym = :online_ym";
        $stmt_preload = $pdo->prepare($sql_preload);
        $stmt_preload->bindValue(':cast_id', (int)$cast_id, PDO::PARAM_INT);
        $stmt_preload->bindValue(':online_ym', $filter_month_ym, PDO::PARAM_STR);
        $stmt_preload->execute();
        $preload_data = $stmt_preload->fetch(PDO::FETCH_ASSOC);
        
        if ($preload_data) {
            $online_amount = $preload_data['online_amount'];
            $is_paid = $preload_data['is_paid'];
            $paid_date = ($preload_data['paid_date'] && $preload_data['paid_date'] != '0000-00-00') ? $preload_data['paid_date'] : '';
        }
    }
}

// 全キャスト情報の取得
$casts = cast_get_all($pdo);

// POSTリクエスト処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'create';

    // フォームデータの取得
    $id = $_POST['id'] ?? null;
    $cast_id = $_POST['cast_id'] ?? '';
    $online_ym_input = $_POST['online_ym'] ?? '';
    $online_amount = $_POST['online_amount'] ?? '';
    $is_paid = $_POST['is_paid'] ?? 0;
    $paid_date = $_POST['paid_date'] ?? date('Y-m-d');

    // バリデーション
    $errors = [];
    if (empty($cast_id)) {
        $errors[] = "キャスト名が選択されていません。";
    }
    if (empty($online_ym_input)) {
        $errors[] = "対象年月が入力されていません。";
    }
    // 金額のバリデーション：0円は許可、マイナスはエラー
    if (!isset($online_amount) || $online_amount === '' || !is_numeric($online_amount)) {
        $errors[] = "金額が正しく入力されていません。";
    } elseif ((float)$online_amount < 0) {
        $errors[] = "金額は0円以上で入力してください。";
    }

    // エラーがある場合は処理を中断
    if (!empty($errors)) {
        $message = "エラー: " . implode(" ", $errors);
        // フォーム表示用に値を保持（YYYY-MM形式）
        $online_ym = $online_ym_input;
        $filter_month_ym = str_replace('-', '', $online_ym_input);
        // エラー時も既存データを読み込む
        if (!empty($cast_id) && !empty($filter_month_ym)) {
            $sql_preload_error = "SELECT * FROM online_month WHERE cast_id = :cast_id AND online_ym = :online_ym";
            $stmt_preload_error = $pdo->prepare($sql_preload_error);
            $stmt_preload_error->bindValue(':cast_id', (int)$cast_id, PDO::PARAM_INT);
            $stmt_preload_error->bindValue(':online_ym', $filter_month_ym, PDO::PARAM_STR);
            $stmt_preload_error->execute();
            $preload_data_error = $stmt_preload_error->fetch(PDO::FETCH_ASSOC);
            
            if ($preload_data_error) {
                $online_amount = $preload_data_error['online_amount'];
                $is_paid = $preload_data_error['is_paid'];
                $paid_date = ($preload_data_error['paid_date'] && $preload_data_error['paid_date'] != '0000-00-00') ? $preload_data_error['paid_date'] : '';
            }
        }
    } else {
        // YYYY-MM形式をYYYYMMに変換（データベース用）
        $online_ym = str_replace('-', '', $online_ym_input);
        
        // 支払い状況が未払いでpaid_dateが未入力の場合はNULLにする
        if ($is_paid == 0 && empty($paid_date)) {
            $paid_date = null;
        }
        
        try {
            if ($action === 'create') {
            // 新規作成時、既存レコードがあるかチェック
            $sql_check = "SELECT id FROM online_month WHERE cast_id = :cast_id AND online_ym = :online_ym";
            $stmt_check = $pdo->prepare($sql_check);
            $stmt_check->bindValue(':cast_id', (int)$cast_id, PDO::PARAM_INT);
            $stmt_check->bindValue(':online_ym', $online_ym, PDO::PARAM_STR);
            $stmt_check->execute();
            $existing_record = $stmt_check->fetch(PDO::FETCH_ASSOC);

            if ($existing_record) {
                // 既存レコードがあれば更新
                $id = $existing_record['id'];
                $sql = "UPDATE online_month SET online_amount = :online_amount, is_paid = :is_paid, paid_date = :paid_date WHERE id = :id";
                $stmt = $pdo->prepare($sql);
                $stmt->bindValue(':online_amount', (int)$online_amount, PDO::PARAM_INT);
                $stmt->bindValue(':is_paid', (int)$is_paid, PDO::PARAM_INT);
                if ($paid_date === null) {
                    $stmt->bindValue(':paid_date', null, PDO::PARAM_NULL);
                } else {
                    $stmt->bindValue(':paid_date', $paid_date, PDO::PARAM_STR);
                }
                $stmt->bindValue(':id', (int)$id, PDO::PARAM_INT);
                $stmt->execute();
                $message = "データが既に存在するため、更新しました。";
                // フォーム表示用にYYYY-MM形式に戻す
                $online_ym = $online_ym_input;
            } else {
                // 既存レコードがなければ新規作成
                $sql = "INSERT INTO online_month (cast_id, online_ym, online_amount, is_paid, paid_date) VALUES (:cast_id, :online_ym, :online_amount, :is_paid, :paid_date)";
                $stmt = $pdo->prepare($sql);
                $stmt->bindValue(':cast_id', (int)$cast_id, PDO::PARAM_INT);
                $stmt->bindValue(':online_ym', $online_ym, PDO::PARAM_STR);
                $stmt->bindValue(':online_amount', (int)$online_amount, PDO::PARAM_INT);
                $stmt->bindValue(':is_paid', (int)$is_paid, PDO::PARAM_INT);
                if ($paid_date === null) {
                    $stmt->bindValue(':paid_date', null, PDO::PARAM_NULL);
                } else {
                    $stmt->bindValue(':paid_date', $paid_date, PDO::PARAM_STR);
                }
                $stmt->execute();
                $message = "データを追加しました。";
                // フォーム表示用にYYYY-MM形式に戻す
                $online_ym = $online_ym_input;
            }

        } elseif ($action === 'update' && $id !== null) {
            // 更新
            $sql = "UPDATE online_month SET cast_id = :cast_id, online_ym = :online_ym, online_amount = :online_amount, is_paid = :is_paid, paid_date = :paid_date WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':cast_id', (int)$cast_id, PDO::PARAM_INT);
            $stmt->bindValue(':online_ym', $online_ym, PDO::PARAM_STR);
            $stmt->bindValue(':online_amount', (int)$online_amount, PDO::PARAM_INT);
            $stmt->bindValue(':is_paid', (int)$is_paid, PDO::PARAM_INT);
            if ($paid_date === null) {
                $stmt->bindValue(':paid_date', null, PDO::PARAM_NULL);
            } else {
                $stmt->bindValue(':paid_date', $paid_date, PDO::PARAM_STR);
            }
            $stmt->bindValue(':id', (int)$id, PDO::PARAM_INT);
            $stmt->execute();
            $message = "データを更新しました。";
            $action = 'create'; // 更新後は新規作成モードに戻す
            // フォーム表示用にYYYY-MM形式に戻す
            $online_ym = $online_ym_input;

        } elseif ($action === 'delete' && $id !== null) {
            // 削除
            $sql = "DELETE FROM online_month WHERE id = :id";
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':id', (int)$id, PDO::PARAM_INT);
            $stmt->execute();
            $message = "データを削除しました。";
            $action = 'create'; // 削除後は新規作成モードに戻す
        }
        } catch (PDOException $e) {
            $message = "エラーが発生しました: " . $e->getMessage();
            // エラー時もフォーム表示用に値を保持
            $online_ym = $online_ym_input;
        }
    }
    
    // POST処理後、成功した場合はcast_idと年月を保持してリダイレクト
    if (!empty($message) && strpos($message, 'エラー') === false) {
        // フォーム表示用にYYYY-MM形式に戻す
        $online_ym_display = $online_ym_input ?? date('Y-m');
        $redirect_url = 'online_support_input.php?filter_month=' . urlencode($online_ym_display);
        if (!empty($cast_id)) {
            $redirect_url .= '&cast_id=' . urlencode($cast_id);
        }
        header('Location: ' . $redirect_url);
        exit;
    }
}


// GETパラメータ処理（編集ボタンクリック時）
if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
    $action = 'update';
    $id = $_GET['id'];
    $sql = "SELECT * FROM online_month WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    $edit_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($edit_data) {
        $cast_id = $edit_data['cast_id'];
        // YYYYMMをYYYY-MM形式に変換
        $online_ym = substr($edit_data['online_ym'], 0, 4) . '-' . substr($edit_data['online_ym'], 4, 2);
        $online_amount = $edit_data['online_amount'];
        $is_paid = $edit_data['is_paid'];
        // NULLまたは'0000-00-00'の場合は空文字列にする
        $paid_date = ($edit_data['paid_date'] && $edit_data['paid_date'] != '0000-00-00') ? $edit_data['paid_date'] : '';
        $filter_month_ym = $edit_data['online_ym']; // 編集時はその月のデータを表示
    }
} elseif ($_SERVER['REQUEST_METHOD'] !== 'POST' && $action === 'create' && !empty($cast_id) && !empty($filter_month_ym)) {
    // GETリクエスト時、編集モードでない場合、既存データを読み込む
    $sql_preload2 = "SELECT * FROM online_month WHERE cast_id = :cast_id AND online_ym = :online_ym";
    $stmt_preload2 = $pdo->prepare($sql_preload2);
    $stmt_preload2->bindValue(':cast_id', (int)$cast_id, PDO::PARAM_INT);
    $stmt_preload2->bindValue(':online_ym', $filter_month_ym, PDO::PARAM_STR);
    $stmt_preload2->execute();
    $preload_data2 = $stmt_preload2->fetch(PDO::FETCH_ASSOC);
    
    if ($preload_data2) {
        $online_amount = $preload_data2['online_amount'];
        $is_paid = $preload_data2['is_paid'];
        $paid_date = ($preload_data2['paid_date'] && $preload_data2['paid_date'] != '0000-00-00') ? $preload_data2['paid_date'] : '';
    }
}

// データ一覧取得（選択された年月でフィルタリング）
$sql_select = "SELECT om.*, cm.cast_name FROM online_month AS om JOIN cast_mst AS cm ON om.cast_id = cm.cast_id";
if (!empty($filter_month_ym)) {
    $sql_select .= " WHERE om.online_ym = :filter_month";
}
$sql_select .= " ORDER BY om.online_ym DESC, om.cast_id ASC";
if (!empty($filter_month_ym)) {
    $stmt_select = $pdo->prepare($sql_select);
    $stmt_select->bindValue(':filter_month', $filter_month_ym, PDO::PARAM_STR);
    $stmt_select->execute();
    $online_data = $stmt_select->fetchAll(PDO::FETCH_ASSOC);
} else {
    $stmt_select = $pdo->query($sql_select);
    $online_data = $stmt_select->fetchAll(PDO::FETCH_ASSOC);
}

// データベース接続を閉じる
disconnect($pdo);

// HTML部分の開始
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>遠隔サポート売上入力・編集</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .container { max-width: 900px; margin: auto; }
        .form-section, .list-section { margin-bottom: 40px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .button-group a, .button-group button { margin-right: 5px; }
        .message { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
    </style>
    <script>
    function loadExistingData() {
        const castIdEl = document.getElementById('cast_id');
        const onlineYmEl = document.getElementById('online_ym');
        
        if (!castIdEl || !onlineYmEl) {
            console.error('Required elements not found');
            return;
        }
        
        const castId = castIdEl.value;
        const onlineYm = onlineYmEl.value;
        
        console.log('loadExistingData called - castId:', castId, 'onlineYm:', onlineYm);
        
        // 既存データを取得して入力欄に表示
        if (castId && onlineYm) {
            const url = 'online_support_input.php?ajax=1&cast_id=' + encodeURIComponent(castId) + '&online_ym=' + encodeURIComponent(onlineYm);
            console.log('Loading existing data:', url);
            
            fetch(url)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('Response data:', data);
                    if (data.success && data.data) {
                        const amountEl = document.getElementById('online_amount');
                        const paidEl = document.getElementById('is_paid');
                        const paidDateEl = document.getElementById('paid_date');
                        
                        if (amountEl) amountEl.value = data.data.online_amount || '';
                        if (paidEl) paidEl.value = data.data.is_paid || '0';
                        if (paidDateEl) {
                            if (data.data.paid_date && data.data.paid_date !== '0000-00-00' && data.data.paid_date !== null) {
                                paidDateEl.value = data.data.paid_date;
                            } else {
                                paidDateEl.value = '';
                            }
                        }
                        console.log('Data loaded successfully - amount:', data.data.online_amount, 'is_paid:', data.data.is_paid);
                    } else {
                        // 既存データがない場合は入力欄をクリア
                        const amountEl = document.getElementById('online_amount');
                        const paidEl = document.getElementById('is_paid');
                        const paidDateEl = document.getElementById('paid_date');
                        
                        if (amountEl) amountEl.value = '';
                        if (paidEl) paidEl.value = '0';
                        if (paidDateEl) paidDateEl.value = '';
                        console.log('No existing data found');
                    }
                })
                .catch(error => {
                    console.error('Error loading data:', error);
                });
        } else {
            console.log('castId or onlineYm is empty, skipping load');
        }
    }
    
    // 年月が変更された時に一覧を更新し、既存データを読み込む
    document.addEventListener('DOMContentLoaded', function() {
        const onlineYmInput = document.getElementById('online_ym');
        if (onlineYmInput) {
            onlineYmInput.addEventListener('change', function() {
                const onlineYm = this.value;
                const castId = document.getElementById('cast_id').value;
                // データ一覧を更新（年月でフィルタリング）
                if (onlineYm) {
                    // キャストIDも保持するためにURLに含める
                    let url = '?filter_month=' + encodeURIComponent(onlineYm);
                    if (castId) {
                        url += '&cast_id=' + encodeURIComponent(castId);
                    }
                    window.location.href = url;
                }
            });
        }
        
        const castIdSelect = document.getElementById('cast_id');
        if (castIdSelect) {
            castIdSelect.addEventListener('change', function() {
                const castId = this.value;
                const onlineYm = document.getElementById('online_ym').value;
                // キャスト名と対象年月の両方が選択されている場合のみ既存データを読み込む
                if (castId && onlineYm) {
                    loadExistingData();
                } else {
                    // どちらかが未選択の場合は入力欄をクリア
                    document.getElementById('online_amount').value = '';
                    document.getElementById('is_paid').value = '0';
                    document.getElementById('paid_date').value = '';
                }
            });
        }
        
        // ページ読み込み時に既存データを読み込む（キャストと年月が選択されている場合）
        // 少し遅延を入れて確実にDOM要素が読み込まれてから実行
        setTimeout(function() {
            const castId = document.getElementById('cast_id').value;
            const onlineYm = document.getElementById('online_ym').value;
            console.log('Page loaded, checking for existing data. castId:', castId, 'onlineYm:', onlineYm);
            if (castId && onlineYm) {
                loadExistingData();
            }
        }, 200);
    });
    </script>
</head>
<body>

<div class="container">
    <h1>遠隔売上入力・編集</h1>

    <?php if ($message): ?>
        <p class="<?php echo (strpos($message, 'エラー') !== false) ? 'error' : 'message'; ?>"><?php echo h($message); ?></p>
    <?php endif; ?>

    <div class="form-section">
        <h2><?php echo ($action === 'create') ? '新規データ登録' : 'データ編集'; ?></h2>
        <form action="online_support_input.php" method="POST">
            <input type="hidden" name="action" value="<?php echo h($action); ?>">
            <input type="hidden" name="id" value="<?php echo h($id); ?>">

            <p>
                <label for="cast_id">キャスト名:</label>
                <select name="cast_id" id="cast_id" required>
                    <option value="">--選択してください--</option>
                    <?php foreach ($casts as $cast): ?>
                        <option value="<?php echo h($cast['cast_id']); ?>" <?php echo ((string)$cast_id === (string)$cast['cast_id'] || (isset($_GET['cast_id']) && (string)$_GET['cast_id'] === (string)$cast['cast_id'])) ? 'selected' : ''; ?>>
                            <?php echo h($cast['cast_name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </p>

            <p>
                <label for="online_ym">対象年月:</label>
                <input type="month" name="online_ym" id="online_ym" value="<?php echo h($online_ym); ?>" required>
            </p>

            <p>
                <label for="online_amount">金額:</label>
                <input type="number" name="online_amount" id="online_amount" value="<?php echo isset($online_amount) ? h($online_amount) : ''; ?>" min="0" step="1"> 円
            </p>

            <p>
                <label for="is_paid">支払い状況:</label>
                <select name="is_paid" id="is_paid" required>
                    <option value="0" <?php echo ($is_paid == 0) ? 'selected' : ''; ?>>未払い</option>
                    <option value="1" <?php echo ($is_paid == 1) ? 'selected' : ''; ?>>支払い済み</option>
                </select>
            </p>

            <p>
                <label for="paid_date">支払い日:</label>
                <input type="date" name="paid_date" id="paid_date" value="<?php echo ($paid_date && $paid_date != '0000-00-00') ? h($paid_date) : ''; ?>">
            </p>
            
            <p>
                <button type="submit"><?php echo ($action === 'create') ? '登録' : '更新'; ?></button>
                <?php if ($action === 'update'): ?>
                    <a href="online_support_input.php">キャンセル</a>
                <?php endif; ?>
            </p>
        </form>
    </div>

    <div class="list-section">
        <h2>データ一覧</h2>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>キャスト名</th>
                    <th>対象年月</th>
                    <th>金額</th>
                    <th>支払い状況</th>
                    <th>支払い日</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($online_data as $data): ?>
                <tr>
                    <td><?php echo h($data['id']); ?></td>
                    <td><?php echo h($data['cast_name']); ?></td>
                    <td><?php echo h(date('Y年m月', strtotime($data['online_ym'] . '01'))); ?></td>
                    <td><?php echo number_format(h($data['online_amount'])); ?> 円</td>
                    <td><?php echo ($data['is_paid'] == 1) ? '支払い済み' : '未払い'; ?></td>
                    <td><?php echo ($data['is_paid'] == 1 && $data['paid_date'] && $data['paid_date'] != '0000-00-00') ? h($data['paid_date']) : ''; ?></td>
                    <td>
                        <a href="?action=edit&id=<?php echo h($data['id']); ?>">編集</a>
                        <a href="online_support_input.php" onclick="event.preventDefault(); if(confirm('本当に削除しますか？')) { document.getElementById('delete-form-<?php echo h($data['id']); ?>').submit(); }">削除</a>
                        <form id="delete-form-<?php echo h($data['id']); ?>" action="online_support_input.php" method="POST" style="display:none;">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?php echo h($data['id']); ?>">
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($online_data)): ?>
                <tr>
                    <td colspan="7">データがありません。</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>

</body>
</html>
