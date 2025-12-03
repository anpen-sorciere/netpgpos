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

$uid = $_SESSION['user_id'] ?? null;
$utype = 0;
if(isset($_GET['utype'])) {
    $utype = $_GET['utype'];
    $_SESSION['utype'] = $utype;
} elseif(isset($_SESSION['utype'])) {
    $utype = $_SESSION['utype'];
}

$db = connect();
$casts = cast_get_all($db);

$selected_cast_id = $_POST['cast_id'] ?? null;
$selected_ymd = $_POST['in_ymd'] ?? date('Y-m');
$current_pay = 0;

// POSTリクエストが送信された場合にのみデータを処理
if (!empty($_POST) && isset($_POST['submit_action']) && $_POST['submit_action'] === 'save_data') {
    // フォームの内容をセッションで保存
    $_SESSION['join'] = $_POST;
    $cast_id = isset($_SESSION['join']['cast_id']) && $_SESSION['join']['cast_id'] !== '' ? (int)$_SESSION['join']['cast_id'] : 0;
    $work_in_ymd = explode('-', $_SESSION['join']['in_ymd']);
    $chk_month = $work_in_ymd[0] . $work_in_ymd[1];
    $pay = isset($_SESSION['join']['pay']) && $_SESSION['join']['pay'] !== '' ? (int)str_replace(',', '', $_SESSION['join']['pay']) : 0;

    try {
        // 金額が0より大きい場合のみ処理を実行
        if ($pay > 0) {
            // 同じキャストIDと月が既に存在するか確認
            $statement = $db->prepare("SELECT COUNT(*) AS cnt FROM pay_tbl WHERE cast_id = ? AND set_month = ?");
            $statement->execute(array($cast_id, $chk_month));
            $row = $statement->fetch(PDO::FETCH_ASSOC);

            if ($row['cnt'] == 0) {
                // 存在しない場合は新規挿入
                $statement = $db->prepare("INSERT INTO pay_tbl (cast_id, set_month, pay) VALUES (?, ?, ?)");
                $statement->execute(array($cast_id, $chk_month, $pay));
            } else {
                // 存在する場合は更新
                $statement = $db->prepare("UPDATE pay_tbl SET pay = ? WHERE cast_id = ? AND set_month = ?");
                $statement->execute(array($pay, $cast_id, $chk_month));
            }
        }
        
        // 処理成功後にセッションのpayをクリアしてリダイレクト
        unset($_SESSION['join']['pay']);
        header('Location: paytbl_result.php?utype=' . htmlspecialchars($utype));
        exit();
    } catch (PDOException $e) {
        // エラーハンドリング
        error_log("Database Error: " . $e->getMessage());
        echo "データベースエラーが発生しました。詳細はログを確認してください。";
    }
} else {
    // POSTリクエストがない場合（初回アクセスまたはGETリクエストの場合）
    // キャストや年月が変更された場合はセッションのpayをクリア
    if (isset($_GET['cast_id']) || isset($_GET['in_ymd'])) {
        unset($_SESSION['join']['pay']);
    }
    
    // フォームの値がセットされていれば、その値で時給データを取得
    if (isset($_GET['cast_id']) && isset($_GET['in_ymd'])) {
        $selected_cast_id = $_GET['cast_id'];
        $selected_ymd = $_GET['in_ymd'];
    } elseif ($casts) {
        // 初回アクセス時は最初のキャストを選択
        $selected_cast_id = $casts[0]['cast_id'];
    }

    if ($selected_cast_id) {
        $chk_month = str_replace('-', '', $selected_ymd);
        $statement = $db->prepare("SELECT pay FROM pay_tbl WHERE cast_id = ? AND set_month = ?");
        $statement->execute(array($selected_cast_id, $chk_month));
        $pay_data = $statement->fetch(PDO::FETCH_ASSOC);
        if ($pay_data) {
            $current_pay = $pay_data['pay'];
        } else {
            // データベースにデータがない場合は0に設定（セッションの値は使用しない）
            $current_pay = 0;
        }
    }
}

// 登録済み・未登録キャストの一覧を取得
$registered_casts = [];
$unregistered_casts = [];

$chk_month = str_replace('-', '', $selected_ymd);

// 登録済みキャストを取得
$statement_reg = $db->prepare("
    SELECT p.pay, c.cast_name
    FROM pay_tbl p
    JOIN cast_mst c ON p.cast_id = c.cast_id
    WHERE p.set_month = ?
    ORDER BY c.cast_type, c.cast_yomi
");
$statement_reg->execute(array($chk_month));
$registered_casts = $statement_reg->fetchAll(PDO::FETCH_ASSOC);

// 未登録キャストを取得
$statement_unreg = $db->prepare("
    SELECT c.cast_name
    FROM cast_mst c
    LEFT JOIN pay_tbl p ON c.cast_id = p.cast_id AND p.set_month = ?
    WHERE p.cast_id IS NULL AND c.drop_flg = 0
    ORDER BY c.cast_type, c.cast_yomi
");
$statement_unreg->execute(array($chk_month));
$unregistered_casts = $statement_unreg->fetchAll(PDO::FETCH_ASSOC);

$db = null;

?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0,minimum-scale=1.0">
    <title>時給登録</title>
    <link href="https://unpkg.com/sanitize.css" rel="stylesheet"/>
    <link rel="stylesheet" href="style.css">
    <style>
        .control a {
            padding: 12px 25px;
            text-decoration: none;
            color: #fff;
            background-color: #3498db;
            border-radius: 30px;
            font-size: 1em;
            transition: transform 0.2s, box-shadow 0.2s;
            box-shadow: 0 4px 15px rgba(52, 152, 219, 0.4);
            display: inline-block;
            margin-top: 10px;
        }
        .control a:hover {
            background-color: #2980b9;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(52, 152, 219, 0.6);
        }
        .container {
            width: 100%;
            max-width: 600px;
            padding: 30px;
            background-color: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            text-align: center;
        }
        body {
            font-family: 'Inter', sans-serif;
            background-color: #f0f4f8;
            color: #333;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            flex-direction: column;
            min-height: 100vh;
        }
        .list-container {
            width: 100%;
            max-width: 600px;
            margin-top: 30px;
            padding: 20px;
            background-color: #fff;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            text-align: left;
        }
        .list-heading {
            font-size: 1.5em;
            color: #2980b9;
            border-bottom: 2px solid #ddd;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .list-group {
            margin-bottom: 20px;
        }
        .list-group h3 {
            font-size: 1.2em;
            color: #555;
            margin-bottom: 10px;
        }
        .list-item {
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>時給登録</h1>
        <form action="paytbl_input.php" method="POST" id="payForm">
            <div class="control">
                <label for="cast_id">キャスト</label>
                <select name="cast_id" id="cast_id">
                    <?php
                    foreach ($casts as $cast) {
                        $selected = ($selected_cast_id == $cast['cast_id']) ? 'selected' : '';
                        echo '<option value="' . htmlspecialchars($cast["cast_id"]) . '" ' . $selected . '>';
                        echo htmlspecialchars($cast["cast_name"], ENT_QUOTES);
                        echo '</option>';
                    }
                    ?>
                </select>
            </div>
            <div class="control">
                <label for="in_ymd">登録年月</label>
                <input type="month" name="in_ymd" id="in_ymd" value="<?php echo htmlspecialchars($selected_ymd); ?>">
            </div>
            <div class="control">
                <label for="pay">金額</label>
                <input name="pay" value="<?php echo htmlspecialchars($current_pay); ?>">
            </div>
            <div class="control">
                <button type="submit" name="submit_action" value="save_data" class="btn">確認する</button>
            </div>
        </form>
        <div class="control">
            <a href="index.php?utype=<?php echo htmlspecialchars($utype); ?>">メニューへ</a>
        </div>
    </div>

    <div class="list-container">
        <h2 class="list-heading"><?= htmlspecialchars(str_replace('-', '年', $selected_ymd)) ?>月 時給登録状況</h2>
        <div class="list-group">
            <h3>✅ 登録済みキャスト</h3>
            <?php if (empty($registered_casts)): ?>
                <p>登録済みのデータはありません。</p>
            <?php else: ?>
                <?php foreach ($registered_casts as $cast): ?>
                    <p class="list-item"><?= htmlspecialchars($cast['cast_name']) ?>: <?= htmlspecialchars(number_format($cast['pay'])) ?>円</p>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <div class="list-group">
            <h3>❌ 未登録キャスト</h3>
            <?php if (empty($unregistered_casts)): ?>
                <p>未登録のキャストはいません。</p>
            <?php else: ?>
                <?php foreach ($unregistered_casts as $cast): ?>
                    <p class="list-item"><?= htmlspecialchars($cast['cast_name']) ?></p>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const castSelect = document.getElementById('cast_id');
            const ymdInput = document.getElementById('in_ymd');

            // キャスト選択肢が変更されたらフォームを送信
            castSelect.addEventListener('change', function() {
                window.location.href = `paytbl_input.php?cast_id=${this.value}&in_ymd=${ymdInput.value}`;
            });

            // 登録年月が変更されたらフォームを送信
            ymdInput.addEventListener('change', function() {
                window.location.href = `paytbl_input.php?cast_id=${castSelect.value}&in_ymd=${this.value}`;
            });
        });
    </script>
</body>
</html>
