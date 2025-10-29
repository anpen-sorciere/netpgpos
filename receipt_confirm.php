<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once(__DIR__ . '/../common/config.php');
require_once(__DIR__ . '/../common/dbconnect.php');
require_once(__DIR__ . '/../common/functions.php');

session_start();

$utype = isset($_SESSION['utype']) ? $_SESSION['utype'] : null;

$search_results = []; // 検索結果を格納する配列
$payments_map = []; // 支払い方法データをキャッシュする配列
$casts = []; // キャストデータをキャッシュする配列
$pdo = null;

try {
    $pdo = connect();
    
    // 支払い方法データ全件取得してキャッシュ
    $payments = payment_get_all($pdo);
    foreach ($payments as $p) {
        $payments_map[$p['payment_type']] = $p['payment_name'];
    }
    
    // キャストデータ全件取得してキャッシュ
    $casts = cast_get_all($pdo);

} catch (PDOException $e) {
    echo "データベース接続に失敗しました: " . $e->getMessage();
    exit();
}

if (!empty($_POST)) {
    // フォーム送信時の処理
    try {
        // 検索条件の構築
        $conditions = [];
        $params = [];

        // 店舗コード
        if (!empty($_POST['shop_mst'])) {
            $conditions[] = "shop_mst = ?";
            $params[] = $_POST['shop_mst'];
        }

        // 座席番号
        if (!empty($_POST['sheet_no'])) {
            $conditions[] = "sheet_no = ?";
            $params[] = $_POST['sheet_no'];
        }

        // 伝票集計日付
        if (!empty($_POST['receipt_day'])) {
            $receipt_day = str_replace('-', '', $_POST['receipt_day']);
            $conditions[] = "receipt_day = ?";
            $params[] = $receipt_day;
        }

        // 入店日付
        if (!empty($_POST['in_date'])) {
            $in_date = str_replace('-', '', $_POST['in_date']);
            $conditions[] = "in_date = ?";
            $params[] = $in_date;
        }
        
        // 入店時間
        if (!empty($_POST['in_time'])) {
            $conditions[] = "in_time = ?";
            $params[] = $_POST['in_time'];
        }

        // 退店日付
        if (!empty($_POST['out_date'])) {
            $out_date = str_replace('-', '', $_POST['out_date']);
            $conditions[] = "out_date = ?";
            $params[] = $out_date;
        }

        // 退店時間
        if (!empty($_POST['out_time'])) {
            $conditions[] = "out_time = ?";
            $params[] = $_POST['out_time'];
        }

        // 顧客名
        if (!empty($_POST['customer_name'])) {
            $conditions[] = "customer_name LIKE ?";
            $params[] = '%' . $_POST['customer_name'] . '%';
        }
        
        // 伝票起票者
        if (!empty($_POST['issuer_id'])) {
            $conditions[] = "issuer_id = ?";
            $params[] = $_POST['issuer_id'];
        }

        // 支払い方法
        if (isset($_POST['p_type']) && $_POST['p_type'] !== '0') {
            $conditions[] = "payment_type = ?";
            $params[] = $_POST['p_type'];
        }

        // SQLクエリの構築
        $sql = "SELECT * FROM receipt_tbl";
        if (count($conditions) > 0) {
            $sql .= " WHERE " . implode(" AND ", $conditions);
        }
        $sql .= " ORDER BY receipt_day DESC, receipt_id DESC LIMIT 500";

        $stmh = $pdo->prepare($sql);
        $stmh->execute($params);
        $search_results = $stmh->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {
        error_log("Database error during search: " . $e->getMessage());
        // ユーザーには一般的なエラーメッセージを表示
        echo "伝票の検索中にエラーが発生しました。";
    }
}

// データベース接続を閉じる
disconnect($pdo);

// 本日の日付を取得
$today = date('Y-m-d');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0,minimum-scale=1.0">
    <title>伝票確認</title>
    <link href="https://unpkg.com/sanitize.css" rel="stylesheet"/>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .result-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .result-table th, .result-table td {
            border: 1px solid #ccc;
            padding: 8px;
            text-align: center;
        }
        .result-table th {
            background-color: #f2f2f2;
        }
    </style>
</head>
<body>
    <div class="content">
        <form action="" method="POST">
            <h2>伝票確認・修正</h2>
            <table>
                <tbody>
                    <tr>
                        <th><label for="shop_mst">店舗コード</label></th>
                        <td>
                            <?php $shop_info = get_shop_info($utype); ?>
                            <?php if ($shop_info['name']): ?>
                                <span class="check-info"><?= htmlspecialchars($shop_info['name'], ENT_QUOTES) ?></span>
                                <input type="hidden" name="shop_mst" value="<?= htmlspecialchars($shop_info['id'], ENT_QUOTES) ?>">
                            <?php else: ?>
                                <input id="shop_mst" type="text" name="shop_mst" value="<?= htmlspecialchars($_POST['shop_mst'] ?? $utype, ENT_QUOTES) ?>">
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="sheet_no">座席番号</label></th>
                        <td><input id="sheet_no" type="text" name="sheet_no" value="<?= htmlspecialchars($_POST['sheet_no'] ?? '', ENT_QUOTES) ?>"></td>
                    </tr>
                    <tr>
                        <th><label for="receipt_day">伝票集計日付</label></th>
                        <td><input id="receipt_day" type="date" name="receipt_day" value="<?= htmlspecialchars($_POST['receipt_day'] ?? $today, ENT_QUOTES) ?>"></td>
                    </tr>
                    <tr>
                        <th><label for="in_date">入店日付</label></th>
                        <td><input id="in_date" type="date" name="in_date" value="<?= htmlspecialchars($_POST['in_date'] ?? '', ENT_QUOTES) ?>"></td>
                    </tr>
                    <tr>
                        <th><label for="in_time">入店時間</label></th>
                        <td><input id="in_time" type="time" name="in_time" value="<?= htmlspecialchars($_POST['in_time'] ?? '', ENT_QUOTES) ?>"></td>
                    </tr>
                    <tr>
                        <th><label for="out_date">退店日付</label></th>
                        <td><input id="out_date" type="date" name="out_date" value="<?= htmlspecialchars($_POST['out_date'] ?? '', ENT_QUOTES) ?>"></td>
                    </tr>
                    <tr>
                        <th><label for="out_time">退店時間</label></th>
                        <td><input id="out_time" type="time" name="out_time" value="<?= htmlspecialchars($_POST['out_time'] ?? '', ENT_QUOTES) ?>"></td>
                    </tr>
                    <tr>
                        <th><label for="customer_name">顧客名</label></th>
                        <td><input id="customer_name" type="text" name="customer_name" value="<?= htmlspecialchars($_POST['customer_name'] ?? '', ENT_QUOTES) ?>"></td>
                    </tr>
                    <tr>
                        <th><label for="issuer_id">伝票起票者</label></th>
                        <td>
                            <select name="issuer_id" id="issuer_id">
                                <option value=""></option>
                                <?php foreach ($casts as $cast): ?>
                                    <option value="<?= htmlspecialchars($cast['cast_id']) ?>"
                                        <?= (isset($_POST['issuer_id']) && $_POST['issuer_id'] == $cast['cast_id']) ? 'selected' : ''; ?>>
                                        <?= htmlspecialchars($cast['cast_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="p_type">支払い方法</label></th>
                        <td>
                            <select name="p_type" id="p_type">
                                <option value="0">すべて</option>
                                <?php foreach ($payments as $payment): ?>
                                    <option value="<?= htmlspecialchars($payment['payment_type']) ?>"
                                        <?= (isset($_POST['p_type']) && $_POST['p_type'] == $payment['payment_type']) ? 'selected' : ''; ?>>
                                        <?= htmlspecialchars($payment['payment_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                </tbody>
            </table>
            <hr>
            <div class="control">
                <button type="submit" class="btn">検索</button>
                <a href="index.php">メニューへ</a>
            </div>
        </form>

        <?php if (!empty($_POST)): ?>
            <h3>検索結果（最大500件）</h3>
            <?php if (!empty($search_results)): ?>
                <table class="result-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>日付</th>
                            <th>座席</th>
                            <th>顧客名</th>
                            <th>支払い方法</th>
                            <th>調整額</th>
                            <th>アクション</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($search_results as $receipt): ?>
                            <tr>
                                <td><?= htmlspecialchars($receipt['receipt_id']) ?></td>
                                <td><?= htmlspecialchars($receipt['receipt_day']) ?></td>
                                <td><?= htmlspecialchars($receipt['sheet_no']) ?></td>
                                <td><?= htmlspecialchars($receipt['customer_name']) ?></td>
                                <td>
                                    <?= htmlspecialchars($payments_map[$receipt['payment_type']] ?? '不明') ?>
                                </td>
                                <td><?= htmlspecialchars(number_format($receipt['adjust_price'])) ?></td>
                                <td>
                                    <a href="receipt_modify.php?id=<?= htmlspecialchars($receipt['receipt_id']) ?>" class="btn">修正</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>検索条件に一致する伝票は見つかりませんでした。</p>
            <?php endif; ?>
        <?php endif; ?>

    </div>
</body>
</html>
