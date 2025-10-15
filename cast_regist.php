<?php
// エラーレポートを有効にし、すべてのエラーを表示
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 必要なファイルを読み込む
require_once('./dbconnect.php');
require_once('./functions.php');

session_start();

$errors = [];
$cast_types = [
    0 => "キャスト",
    1 => "スタッフ",
    2 => "ゲスト"
];

// POSTリクエストが送信された場合の処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // フォームの内容をセッションに保存
    $_SESSION['join'] = $_POST;

    // 入力情報の不備をチェック
    if ($_POST['cast_name'] == '') {
        $errors['cast_name'] = 'blank';
    }
    if (!isset($_POST['cast_type']) || $_POST['cast_type'] == '') {
        $errors['cast_type'] = 'blank';
    }
    if (!isset($_POST['drop_flg'])) {
        $errors['drop_flg'] = 'blank';
    }
    
    // 日付フォーマットの変換
    if (!empty($_POST['birthday'])) {
        $_SESSION['join']['birthday'] = str_replace('-', '', $_POST['birthday']);
    }
    if (!empty($_POST['joinday'])) {
        $_SESSION['join']['joinday'] = str_replace('-', '', $_POST['joinday']);
    }
    if (!empty($_POST['dropday'])) {
        $_SESSION['join']['dropday'] = str_replace('-', '', $_POST['dropday']);
    }

    // エラーがなければ次のページへ
    if (empty($errors)) {
        header('Location: cast_check.php');
        exit();
    }
} else {
    // GETリクエストの場合
    // URLパラメータからcast_idが取得でき、かつセッションにデータがない場合はDBから取得
    if (isset($_GET['cast_id'])) {
        $db = connect();
        $stmt = $db->prepare("SELECT * FROM cast_mst WHERE cast_id = ?");
        $stmt->execute([$_GET['cast_id']]);
        $selected_cast = $stmt->fetch(PDO::FETCH_ASSOC);
        $db = null;
        
        // 取得したデータをセッションに設定して、フォームに表示
        if ($selected_cast) {
            // DBから取得した日付をYYYY-MM-DD形式に変換
            if (!empty($selected_cast['birthday'])) {
                $selected_cast['birthday'] = substr($selected_cast['birthday'], 0, 4) . '-' . substr($selected_cast['birthday'], 4, 2) . '-' . substr($selected_cast['birthday'], 6, 2);
            }
            if (!empty($selected_cast['joinday'])) {
                $selected_cast['joinday'] = substr($selected_cast['joinday'], 0, 4) . '-' . substr($selected_cast['joinday'], 4, 2) . '-' . substr($selected_cast['joinday'], 6, 2);
            }
            if (!empty($selected_cast['dropday'])) {
                $selected_cast['dropday'] = substr($selected_cast['dropday'], 0, 4) . '-' . substr($selected_cast['dropday'], 4, 2) . '-' . substr($selected_cast['dropday'], 6, 2);
            }
            $_SESSION['join'] = $selected_cast;
        }
    } else if (!isset($_SESSION['join'])) {
        // 新規登録の場合はセッションデータをクリア
        unset($_SESSION['join']);
    }
}


// データベースから既存のキャスト情報を取得
$db = connect();
$stmt = $db->prepare("SELECT * FROM cast_mst ORDER BY cast_type, cast_yomi");
$stmt->execute();
$all_casts = $stmt->fetchAll(PDO::FETCH_ASSOC);
$db = null;

// フォームの入力値を保持
$post = $_SESSION['join'] ?? [];
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0,minimum-scale=1.0">
    <title>キャスト登録</title>
    <link href="https://unpkg.com/sanitize.css" rel="stylesheet"/>
    <link rel="stylesheet" href="css/style.css">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f0f4f8;
            color: #333;
            margin: 0;
            padding: 20px;
            display: flex;
            justify-content: center;
            align-items: flex-start;
            min-height: 100vh;
            flex-direction: column;
        }
        .container {
            width: 100%;
            max-width: 800px;
            margin: 20px auto;
            background-color: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            padding: 30px;
        }
        h1 {
            color: #3498db;
            text-align: center;
            margin-bottom: 20px;
        }
        .form-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .form-table th, .form-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        .form-table th {
            width: 20%;
            background-color: #f8f8f8;
            font-weight: bold;
        }
        .form-table td input[type="text"], .form-table td input[type="tel"], .form-table td input[type="date"], .form-table td select {
            width: 90%;
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 4px;
            transition: border-color 0.3s;
        }
        .form-table td input:focus, .form-table td select:focus {
            outline: none;
            border-color: #3498db;
        }
        .control-group {
            display: flex;
            justify-content: flex-end;
            gap: 15px;
            margin-top: 20px;
        }
        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 30px;
            font-size: 1em;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .btn-primary {
            background-color: #3498db;
            color: #fff;
            box-shadow: 0 4px 15px rgba(52, 152, 219, 0.4);
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(52, 152, 219, 0.6);
        }
        .btn-secondary {
            background-color: #ecf0f1;
            color: #333;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        .btn-secondary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.2);
        }
        .error {
            color: #e74c3c;
            font-size: 0.9em;
            margin-top: 5px;
        }
        .info-table-container {
            width: 100%;
            overflow-x: auto;
            margin-top: 30px;
        }
        .info-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9em;
            min-width: 900px;
        }
        .info-table th, .info-table td {
            padding: 10px;
            border: 1px solid #ccc;
            text-align: center;
        }
        .info-table th {
            background-color: #f1f1f1;
        }
        .info-table tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .info-table a {
            color: #3498db;
            text-decoration: none;
            font-weight: bold;
        }
        .info-table a:hover {
            text-decoration: underline;
        }
        .required-label {
            color: #e74c3c;
            font-size: 0.9em;
            margin-left: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>キャスト登録</h1>
        <form action="" method="POST">
            <!-- 編集時にはcast_idを送信 -->
            <input type="hidden" name="cast_id" value="<?= htmlspecialchars($post['cast_id'] ?? '') ?>">
            <table class="form-table">
                <tbody>
                    <tr>
                        <th><label for="cast_name">キャスト名 <span class="required">（必須）</span></label></th>
                        <td>
                            <input id="cast_name" type="text" name="cast_name" value="<?= htmlspecialchars($post['cast_name'] ?? '') ?>" autocomplete="off">
                            <?php if (isset($errors['cast_name'])): ?>
                                <p class="error">キャスト名を入力してください</p>
                            <?php endif; ?>
                        </td>
                        <th><label for="cast_yomi">キャスト名の読み仮名</label></th>
                        <td><input id="cast_yomi" type="text" name="cast_yomi" value="<?= htmlspecialchars($post['cast_yomi'] ?? '') ?>" autocomplete="off"></td>
                    </tr>
                    <tr>
                        <th><label for="real_name">本名</label></th>
                        <td><input id="real_name" type="text" name="real_name" value="<?= htmlspecialchars($post['real_name'] ?? '') ?>" autocomplete="off"></td>
                        <th><label for="yomigana">よみがな</label></th>
                        <td><input id="yomigana" type="text" name="yomigana" value="<?= htmlspecialchars($post['yomigana'] ?? '') ?>" autocomplete="off"></td>
                    </tr>
                    <tr>
                        <th><label for="birthday">生年月日</label></th>
                        <td><input id="birthday" type="date" name="birthday" value="<?= htmlspecialchars($post['birthday'] ?? '') ?>" autocomplete="off"></td>
                        <th><label for="address">住所</label></th>
                        <td><input id="address" type="text" name="address" value="<?= htmlspecialchars($post['address'] ?? '') ?>" autocomplete="off"></td>
                    </tr>
                    <tr>
                        <th><label for="station">最寄駅</label></th>
                        <td><input id="station" type="text" name="station" value="<?= htmlspecialchars($post['station'] ?? '') ?>" autocomplete="off"></td>
                        <th><label for="tel1">電話番号1</label></th>
                        <td><input id="tel1" type="tel" name="tel1" value="<?= htmlspecialchars($post['tel1'] ?? '') ?>" autocomplete="off"></td>
                    </tr>
                    <tr>
                        <th><label for="tel2">電話番号2</label></th>
                        <td><input id="tel2" type="tel" name="tel2" value="<?= htmlspecialchars($post['tel2'] ?? '') ?>" autocomplete="off"></td>
                        <th><label for="tc">交通費</label></th>
                        <td><input id="tc" type="text" name="tc" value="<?= htmlspecialchars($post['tc'] ?? '') ?>" autocomplete="off"></td>
                    </tr>
                    <tr>
                        <th><label for="joinday">入店日</label></th>
                        <td><input id="joinday" type="date" name="joinday" value="<?= htmlspecialchars($post['joinday'] ?? '') ?>" autocomplete="off"></td>
                        <th><label for="dropday">退店日</label></th>
                        <td><input id="dropday" type="date" name="dropday" value="<?= htmlspecialchars($post['dropday'] ?? '') ?>" autocomplete="off"></td>
                    </tr>
                    <tr>
                        <th><label for="cast_type">キャストタイプ <span class="required">（必須）</span></label></th>
                        <td>
                            <select id="cast_type" name="cast_type">
                                <?php foreach ($cast_types as $value => $label): ?>
                                    <option value="<?= htmlspecialchars($value) ?>" <?= (isset($post['cast_type']) && $post['cast_type'] == $value) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($label) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($errors['cast_type'])): ?>
                                <p class="error">キャストタイプを選択してください</p>
                            <?php endif; ?>
                        </td>
                        <th><label for="drop_flg">在籍状況 <span class="required">（必須）</span></label></th>
                        <td>
                            <input type="radio" name="drop_flg" value="0" id="status-0" <?= (isset($post['drop_flg']) && $post['drop_flg'] == '0') ? 'checked' : '' ?>>
                            <label for="status-0">在籍</label>
                            <input type="radio" name="drop_flg" value="1" id="status-1" <?= (isset($post['drop_flg']) && $post['drop_flg'] == '1') ? 'checked' : '' ?>>
                            <label for="status-1">退職済</label>
                            <?php if (isset($errors['drop_flg'])): ?>
                                <p class="error">在籍状況を選択してください</p>
                            <?php endif; ?>
                        </td>
                    </tr>
                </tbody>
            </table>
            <div class="control-group">
                <button type="submit" class="btn btn-primary">確認する</button>
                <input value="前に戻る" onclick="history.back();" type="button" class="btn btn-secondary">
            </div>
        </form>
    </div>

    <div class="info-table-container">
        <table class="info-table">
            <thead>
                <tr>
                    <th>ID</th><th>キャスト名</th><th>キャスト読み</th><th>本名</th><th>よみがな</th><th>生年月日</th><th>住所</th><th>TEL1</th><th>TEL2</th><th>最寄駅</th><th>交通費</th><th>キャストタイプ</th><th>入店日</th><th>退店日</th><th>在籍状況</th><th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($all_casts as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars($row['cast_id']) ?></td>
                        <td><?= htmlspecialchars($row['cast_name']) ?></td>
                        <td><?= htmlspecialchars($row['cast_yomi']) ?></td>
                        <td><?= htmlspecialchars($row['real_name']) ?></td>
                        <td><?= htmlspecialchars($row['yomigana']) ?></td>
                        <td><?= htmlspecialchars($row['birthday'] ? date('Y/m/d', strtotime($row['birthday'])) : '') ?></td>
                        <td><?= htmlspecialchars($row['address']) ?></td>
                        <td><?= htmlspecialchars($row['tel1']) ?></td>
                        <td><?= htmlspecialchars($row['tel2']) ?></td>
                        <td><?= htmlspecialchars($row['station']) ?></td>
                        <td><?= htmlspecialchars($row['tc']) ?></td>
                        <td><?= htmlspecialchars($cast_types[$row['cast_type']] ?? '') ?></td>
                        <td><?= htmlspecialchars($row['joinday'] ? date('Y/m/d', strtotime($row['joinday'])) : '') ?></td>
                        <td><?= htmlspecialchars($row['dropday'] ? date('Y/m/d', strtotime($row['dropday'])) : '') ?></td>
                        <td><?= ($row['drop_flg'] == 1) ? '退職済' : '在籍' ?></td>
                        <td><a href="?cast_id=<?= htmlspecialchars($row['cast_id']) ?>">編集</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</body>
</html>
