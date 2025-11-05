<?php
// online_support_input.php

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
$online_amount = '';
$is_paid = 0;
$paid_date = date('Y-m-d');
$action = 'create';
$message = '';
$online_data = [];

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
    }
}

// データ一覧取得
$sql_select = "SELECT om.*, cm.cast_name FROM online_month AS om JOIN cast_mst AS cm ON om.cast_id = cm.cast_id ORDER BY online_ym DESC, cast_id ASC";
$stmt_select = $pdo->query($sql_select);
$online_data = $stmt_select->fetchAll(PDO::FETCH_ASSOC);

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
                        <option value="<?php echo h($cast['cast_id']); ?>" <?php echo ($cast_id == $cast['cast_id']) ? 'selected' : ''; ?>>
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
                <input type="number" name="online_amount" id="online_amount" value="<?php echo h($online_amount); ?>" min="0" step="1"> 円
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
