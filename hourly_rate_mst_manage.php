<?php
// hourly_rate_mst_manage.php

// 共通関数の読み込み
require_once(__DIR__ . '/../common/dbconnect.php');
require_once(__DIR__ . '/../common/functions.php');

// HTMLエスケープ処理
if (!function_exists('h')) {
    function h($str)
    {
        return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
    }
}

// データベース接続
$pdo = connect();

// 変数初期化
$hourly_rate_val = '';
$regular_work_val = '';
$short_time_work_val = '';
$action = 'create';
$message = '';
$error = '';

// GETリクエスト処理
if (isset($_GET['action'])) {
    if ($_GET['action'] === 'edit' && isset($_GET['hourly_rate'])) {
        $action = 'update';
        $hourly_rate_val = $_GET['hourly_rate'];
        $sql = "SELECT * FROM hourly_rate_mst WHERE hourly_rate = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$hourly_rate_val]);
        $edit_data = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($edit_data) {
            $hourly_rate_val = $edit_data['hourly_rate'];
            $regular_work_val = $edit_data['regular_work'];
            $short_time_work_val = $edit_data['short_time_work'];
        }
    } elseif ($_GET['action'] === 'export_csv') {
        // CSVエクスポート処理
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="hourly_rate_data_' . date('Ymd_His') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // ヘッダー行を出力
        fputcsv($output, ['時給', '通常勤務者', '月5日以内の短時間勤務者']);
        
        // データベースから全データを取得
        $sql = "SELECT * FROM hourly_rate_mst ORDER BY hourly_rate ASC";
        $stmt = $pdo->query($sql);
        
        // データ行を出力
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            // 日本語のヘッダーに合わせてデータを再構築
            fputcsv($output, [
                $row['hourly_rate'],
                $row['regular_work'],
                $row['short_time_work']
            ]);
        }
        
        fclose($output);
        exit();
    }
}

// POSTリクエスト処理 (フォームからの送信)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSVファイルインポート処理
    if (isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
        $file_tmp_name = $_FILES['csv_file']['tmp_name'];
        if (($handle = fopen($file_tmp_name, "r")) !== FALSE) {
            fgetcsv($handle, 1000, "\t"); // ヘッダー行をスキップ（区切り文字をタブに変更）

            $pdo->beginTransaction();
            try {
                while (($data = fgetcsv($handle, 1000, "\t")) !== FALSE) {
                    // CSVのインデックスを直接指定
                    $hourly_rate = trim($data[0]);
                    $regular_work = trim($data[1]);
                    $short_time_work = trim($data[2]);

                    if (count($data) < 3 || empty($hourly_rate)) {
                        continue; // データが不完全な行はスキップ
                    }
                    
                    // 金額データのカンマを除去
                    $hourly_rate = str_replace(',', '', $hourly_rate);
                    $regular_work = str_replace(',', '', $regular_work);
                    $short_time_work = str_replace(',', '', $short_time_work);
                    
                    $sql_check = "SELECT hourly_rate FROM hourly_rate_mst WHERE hourly_rate = ?";
                    $stmt_check = $pdo->prepare($sql_check);
                    $stmt_check->execute([$hourly_rate]);
                    if ($stmt_check->fetch(PDO::FETCH_ASSOC)) {
                        $sql = "UPDATE hourly_rate_mst SET regular_work = ?, short_time_work = ? WHERE hourly_rate = ?";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute([$regular_work, $short_time_work, $hourly_rate]);
                    } else {
                        $sql = "INSERT INTO hourly_rate_mst (hourly_rate, regular_work, short_time_work) VALUES (?, ?, ?)";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute([$hourly_rate, $regular_work, $short_time_work]);
                    }
                }
                $pdo->commit();
                $message = "CSVファイルのインポートが完了しました。";
            } catch (PDOException $e) {
                $pdo->rollBack();
                $error = "データベースエラー: " . $e->getMessage();
            }
            fclose($handle);
        } else {
            $error = "ファイルの読み込みに失敗しました。";
        }
    }
    // フォームからの新規登録/更新処理
    else {
        $hourly_rate = $_POST['hourly_rate'] ?? '';
        $regular_work = $_POST['regular_work'] ?? '';
        $short_time_work = $_POST['short_time_work'] ?? '';
        $form_action = $_POST['action'] ?? 'create';
        
        // 金額データのカンマを除去
        $hourly_rate = str_replace(',', '', $hourly_rate);
        $regular_work = str_replace(',', '', $regular_work);
        $short_time_work = str_replace(',', '', $short_time_work);

        try {
            if ($form_action === 'create') {
                $sql = "INSERT INTO hourly_rate_mst (hourly_rate, regular_work, short_time_work) VALUES (?, ?, ?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$hourly_rate, $regular_work, $short_time_work]);
                $message = "新規データを登録しました。";
            } elseif ($form_action === 'update') {
                $sql = "UPDATE hourly_rate_mst SET regular_work = ?, short_time_work = ? WHERE hourly_rate = ?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$regular_work, $short_time_work, $hourly_rate]);
                $message = "データを更新しました。";
            }
        } catch (PDOException $e) {
            $error = "データベースエラー: " . $e->getMessage();
        }
    }
}

// データ一覧取得
$sql_select = "SELECT * FROM hourly_rate_mst ORDER BY hourly_rate ASC";
$stmt_select = $pdo->query($sql_select);
$hourly_rate_data = $stmt_select->fetchAll(PDO::FETCH_ASSOC);

// データベース接続を閉じる
disconnect($pdo);
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>時給マスター管理</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .container { max-width: 900px; margin: auto; }
        .section { margin-bottom: 40px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .message { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
    </style>
</head>
<body>

<div class="container">
    <h1>時給マスター管理</h1>

    <?php if ($message): ?>
        <p class="message"><?php echo h($message); ?></p>
    <?php endif; ?>

    <?php if ($error): ?>
        <p class="error"><?php echo h($error); ?></p>
    <?php endif; ?>

    <div class="section">
        <h2><?php echo ($action === 'create') ? '新規データ登録' : 'データ修正'; ?></h2>
        <form action="hourly_rate_mst_manage.php" method="POST">
            <input type="hidden" name="action" value="<?php echo h($action); ?>">
            <p>
                <label for="hourly_rate">時給:</label>
                <input type="number" name="hourly_rate" id="hourly_rate" value="<?php echo h($hourly_rate_val); ?>" <?php echo ($action === 'update') ? 'readonly' : ''; ?> required>
            </p>
            <p>
                <label for="regular_work">通常勤務者:</label>
                <input type="number" name="regular_work" id="regular_work" value="<?php echo h($regular_work_val); ?>" required>
            </p>
            <p>
                <label for="short_time_work">月5日以内の短時間勤務者:</label>
                <input type="number" name="short_time_work" id="short_time_work" value="<?php echo h($short_time_work_val); ?>" required>
            </p>
            <p>
                <button type="submit"><?php echo ($action === 'create') ? '登録' : '更新'; ?></button>
                <?php if ($action === 'update'): ?>
                    <a href="hourly_rate_mst_manage.php">キャンセル</a>
                <?php endif; ?>
            </p>
        </form>
    </div>

    <hr>

    <div class="section">
        <h2>CSVインポート</h2>
        <form action="hourly_rate_mst_manage.php" method="POST" enctype="multipart/form-data">
            <p>
                <label for="csv_file">CSVファイルを選択:</label>
                <input type="file" name="csv_file" id="csv_file" accept=".csv" required>
            </p>
            <p>
                <button type="submit">インポート開始</button>
            </p>
        </form>
    </div>

    <hr>

    <div class="section">
        <h2>時給マスターデータ一覧</h2>
        <p>
            <a href="hourly_rate_mst_manage.php?action=export_csv">CSVダウンロード</a>
        </p>
        <table>
            <thead>
                <tr>
                    <th>時給</th>
                    <th>通常勤務者</th>
                    <th>月5日以内の短時間勤務者</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($hourly_rate_data as $data): ?>
                <tr>
                    <td><?php echo h($data['hourly_rate']); ?></td>
                    <td><?php echo h($data['regular_work']); ?></td>
                    <td><?php echo h($data['short_time_work']); ?></td>
                    <td>
                        <a href="?action=edit&hourly_rate=<?php echo h($data['hourly_rate']); ?>">編集</a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($hourly_rate_data)): ?>
                <tr>
                    <td colspan="4">データがありません。</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>

</body>
</html>
