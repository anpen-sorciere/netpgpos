<?php
// エラーレポートを有効にし、すべてのエラーを表示
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// 必要なファイルを読み込む
require_once(__DIR__ . '/../common/config.php');
require_once(__DIR__ . '/../common/dbconnect.php');
require_once(__DIR__ . '/../common/functions.php');

session_start();

// セッションにデータがなければ、強制的に入力画面へ戻す
if (!isset($_SESSION['cast_regist'])) {
    header('Location: cast_regist.php');
    exit();
}

$cast_types = [
    0 => "キャスト",
    1 => "スタッフ",
    2 => "ゲスト"
];

// POSTリクエストが送信された場合の処理（登録・更新）
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db = connect();

        // セッションからデータ取得
        $post = $_SESSION['cast_regist'];

        // cast_idがある場合は更新、ない場合は新規登録
        if (isset($post['cast_id']) && $post['cast_id'] !== '') {
            // 更新処理
            $stmt = $db->prepare(
                'UPDATE cast_mst SET
                    cast_name = ?, cast_yomi = ?, real_name = ?, yomigana = ?, birthday = ?, address = ?,
                    station = ?, tel1 = ?, tel2 = ?, tc = ?, joinday = ?, dropday = ?,
                    cast_type = ?, drop_flg = ?
                WHERE cast_id = ?'
            );
            $stmt->execute([
                $post['cast_name'], $post['cast_yomi'], $post['real_name'], $post['yomigana'],
                $post['birthday'], $post['address'], $post['station'], $post['tel1'],
                $post['tel2'], $post['tc'], $post['joinday'], $post['dropday'],
                $post['cast_type'], $post['drop_flg'], $post['cast_id']
            ]);
        } else {
            // 新規登録処理
            $stmt = $db->prepare(
                'INSERT INTO cast_mst (
                    cast_name, cast_yomi, real_name, yomigana, birthday, address, station,
                    tel1, tel2, tc, joinday, dropday, cast_type, drop_flg
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $post['cast_name'], $post['cast_yomi'], $post['real_name'], $post['yomigana'],
                $post['birthday'], $post['address'], $post['station'], $post['tel1'],
                $post['tel2'], $post['tc'], $post['joinday'], $post['dropday'],
                $post['cast_type'], $post['drop_flg']
            ]);
        }

        $db = null;
        // セッションデータをクリアして登録完了画面へ
        unset($_SESSION['cast_regist']);
        header('Location: cast_regist.php?status=success');
        exit();
    } catch (PDOException $e) {
        error_log('Error: ' . $e->getMessage());
        header('Location: cast_regist.php?status=error');
        exit();
    }
}

// フォームの入力値を取得
$post = $_SESSION['cast_regist'];
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0,minimum-scale=1.0">
    <title>キャスト情報確認</title>
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
            max-width: 600px;
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
        .info-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .info-table th, .info-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        .info-table th {
            width: 30%;
            background-color: #f8f8f8;
            font-weight: bold;
        }
        .info-table td {
            word-wrap: break-word;
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
        .message-success {
            color: #2ecc71;
            text-align: center;
            margin-top: 20px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>入力内容の確認</h1>
        <p>以下の内容で登録します。よろしければ「登録する」ボタンを押してください。</p>

        <form action="" method="POST">
            <table class="info-table">
                <tbody>
                    <?php if (isset($post['cast_id'])): ?>
                        <tr>
                            <th>キャストID</th>
                            <td><?= htmlspecialchars($post['cast_id']) ?></td>
                        </tr>
                    <?php endif; ?>
                    <tr>
                        <th>キャスト名</th>
                        <td><?= htmlspecialchars($post['cast_name'] ?? '') ?></td>
                    </tr>
                    <tr>
                        <th>キャスト名の読み仮名</th>
                        <td><?= htmlspecialchars($post['cast_yomi'] ?? '') ?></td>
                    </tr>
                    <tr>
                        <th>本名</th>
                        <td><?= htmlspecialchars($post['real_name'] ?? '') ?></td>
                    </tr>
                    <tr>
                        <th>よみがな</th>
                        <td><?= htmlspecialchars($post['yomigana'] ?? '') ?></td>
                    </tr>
                    <tr>
                        <th>生年月日</th>
                        <td><?= htmlspecialchars($post['birthday'] ? date('Y/m/d', strtotime($post['birthday'])) : '') ?></td>
                    </tr>
                    <tr>
                        <th>住所</th>
                        <td><?= htmlspecialchars($post['address'] ?? '') ?></td>
                    </tr>
                    <tr>
                        <th>最寄駅</th>
                        <td><?= htmlspecialchars($post['station'] ?? '') ?></td>
                    </tr>
                    <tr>
                        <th>電話番号1</th>
                        <td><?= htmlspecialchars($post['tel1'] ?? '') ?></td>
                    </tr>
                    <tr>
                        <th>電話番号2</th>
                        <td><?= htmlspecialchars($post['tel2'] ?? '') ?></td>
                    </tr>
                    <tr>
                        <th>交通費</th>
                        <td><?= htmlspecialchars($post['tc'] ?? '') ?></td>
                    </tr>
                    <tr>
                        <th>入店日</th>
                        <td><?= htmlspecialchars($post['joinday'] ? date('Y/m/d', strtotime($post['joinday'])) : '') ?></td>
                    </tr>
                    <tr>
                        <th>退店日</th>
                        <td><?= htmlspecialchars($post['dropday'] ? date('Y/m/d', strtotime($post['dropday'])) : '') ?></td>
                    </tr>
                    <tr>
                        <th>キャストタイプ</th>
                        <td><?= htmlspecialchars($cast_types[$post['cast_type']] ?? '') ?></td>
                    </tr>
                    <tr>
                        <th>在籍状況</th>
                        <td><?= ($post['drop_flg'] == 1) ? '退職済' : '在籍' ?></td>
                    </tr>
                </tbody>
            </table>

            <div class="control-group">
                <button type="button" class="btn btn-secondary" onclick="history.back()">変更する</button>
                <button type="submit" class="btn btn-primary">登録する</button>
            </div>
        </form>
    </div>
</body>
</html>
