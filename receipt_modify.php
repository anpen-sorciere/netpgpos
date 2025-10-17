<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once('./dbconnect.php');
require_once('./functions.php');

session_start();

$receipt_id = $_GET['id'] ?? null;
$error = [];

// セッションから店舗コードを取得
$utype = isset($_SESSION['utype']) ? $_SESSION['utype'] : null;
$shop_info = get_shop_info($utype);
$shop_mst = $shop_info['id'];

// データベース接続
try {
    $pdo = connect();
} catch (PDOException $e) {
    echo "データベース接続に失敗しました: " . $e->getMessage();
    exit();
}

// フォームへの初期データ表示、または更新失敗時の再表示のためのデータ準備
$receipt_data = [];
$detail_data = [];

// POST送信時
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 入力値のバリデーション
    if (empty($_POST['receipt_day'])) {
        $error['input'] = '伝票集計日付は必須入力です。';
    }
    // 入店時間を必須入力に
    if (empty($_POST['in_time'])) {
        $error['in_time'] = '入店時間は必須入力です。';
    }
    if (isset($_POST['adjust_price']) && !is_numeric($_POST['adjust_price'])) {
        $error['adjust_price'] = '調整額は半角数字で入力してください。';
    }

    if (empty($error)) {
        try {
            // トランザクション開始
            $pdo->beginTransaction();

            // receipt_tblの更新
            $receipt_day = str_replace('-', '', $_POST['receipt_day']);
            $in_date = !empty($_POST['in_date']) ? str_replace('-', '', $_POST['in_date']) : null;
            $out_date = !empty($_POST['out_date']) ? str_replace('-', '', $_POST['out_date']) : null;

            // HH:MM形式をHHMM形式に変換して保存
            $in_time = !empty($_POST['in_time']) ? str_replace(':', '', $_POST['in_time']) : null;
            $out_time = !empty($_POST['out_time']) ? str_replace(':', '', $_POST['out_time']) : null;

            $sql_receipt = "UPDATE receipt_tbl SET
                sheet_no = ?,
                receipt_day = ?,
                in_date = ?,
                in_time = ?,
                out_date = ?,
                out_time = ?,
                customer_name = ?,
                issuer_id = ?,
                payment_type = ?,
                adjust_price = ?
                WHERE receipt_id = ?";
            
            $stmh_update_receipt = $pdo->prepare($sql_receipt);
            $stmh_update_receipt->execute([
                $_POST['sheet_no'] ?? null,
                $receipt_day,
                $in_date,
                $in_time,
                $out_date,
                $out_time,
                $_POST['customer_name'] ?? null,
                $_POST['issuer_id'] ?? null,
                $_POST['p_type'],
                $_POST['adjust_price'] ?? 0,
                $receipt_id
            ]);

            // item_mstから価格情報を事前に取得
            $items_map = [];
            foreach (item_get_all($pdo) as $item) {
                $items_map[$item['item_id']] = $item;
            }

            // receipt_detail_tblの更新
            $sql_detail = "UPDATE receipt_detail_tbl SET
                           item_id = ?,
                           quantity = ?,
                           price = ?,
                           cast_id = ?,
                           cast_back_price = ?
                           WHERE receipt_detail_id = ?";
            $stmh_update_detail = $pdo->prepare($sql_detail);
            
            // 11件の明細をループ処理
            for ($i = 1; $i <= 11; $i++) {
                $receipt_detail_id = $_POST['receipt_detail_id' . $i] ?? null;
                $item_id = $_POST['item_name' . $i] ?? null;
                $quantity = $_POST['suu' . $i] ?? null;
                $cast_id = $_POST['cast_name' . $i] ?? null;

                if (!empty($receipt_detail_id)) {
                    $item_data = $items_map[$item_id] ?? null;

                    // 商品が選択されているか確認
                    if (!empty($item_id) && $item_data) {
                        $price = $item_data['price'] ?? 0;
                        $cast_back_price = $item_data['back_price'] ?? 0;
                        
                        $stmh_update_detail->execute([
                            $item_id,
                            $quantity ?? 0,
                            $price,
                            $cast_id,
                            $cast_back_price,
                            $receipt_detail_id
                        ]);
                    } else {
                        // フォームで空欄にされた場合、データベースの該当レコードを空にする
                        $stmh_update_detail->execute([
                            null, // item_id
                            null, // quantity
                            null, // price
                            null, // cast_id
                            null, // cast_back_price
                            $receipt_detail_id
                        ]);
                    }
                }
            }
            
            // コミット
            $pdo->commit();

            // 修正完了後、一覧画面にリダイレクト
            header('Location: receipt_confirm.php');
            exit();

        } catch (PDOException $e) {
            $pdo->rollBack();
            $error['db'] = "更新中にエラーが発生しました：" . $e->getMessage();
            error_log($error['db']);
            // エラー時でもフォームの入力値を保持
            $receipt_data = $_POST;
        }
    } else {
        // バリデーションエラー時でもフォームの入力値を保持
        $receipt_data = $_POST;
    }
} else {
    // 初回アクセス時
    try {
        // 伝票マスターデータ取得
        $stmh_receipt = $pdo->prepare("SELECT * FROM receipt_tbl WHERE receipt_id = ? AND shop_mst = ?");
        $stmh_receipt->execute([$receipt_id, $shop_mst]);
        $receipt_data = $stmh_receipt->fetch(PDO::FETCH_ASSOC);

        if (!$receipt_data) {
            throw new Exception("指定された伝票が見つかりません。");
        }
        
        // データベースの日付・時間フォーマットをHTMLの形式に変換
        $receipt_data['receipt_day'] = date('Y-m-d', strtotime($receipt_data['receipt_day']));
        $receipt_data['in_date'] = $receipt_data['in_date'] ? date('Y-m-d', strtotime($receipt_data['in_date'])) : '';
        $receipt_data['out_date'] = $receipt_data['out_date'] ? date('Y-m-d', strtotime($receipt_data['out_date'])) : '';
        // HHMM形式をHH:MM形式に変換
        $receipt_data['in_time'] = !empty($receipt_data['in_time']) ? date('H:i', strtotime($receipt_data['in_time'])) : '';
        $receipt_data['out_time'] = !empty($receipt_data['out_time']) ? date('H:i', strtotime($receipt_data['out_time'])) : '';

        // 伝票詳細データ取得
        $stmh_detail = $pdo->prepare("SELECT * FROM receipt_detail_tbl WHERE receipt_id = ? ORDER BY receipt_detail_id");
        $stmh_detail->execute([$receipt_id]);
        $detail_data = $stmh_detail->fetchAll(PDO::FETCH_ASSOC);
        
        // 明細データが11件未満の場合、不足分を空の配列で埋める
        while (count($detail_data) < 11) {
            $detail_data[] = [
                'receipt_detail_id' => null,
                'item_id' => null,
                'quantity' => null,
                'cast_id' => null
            ];
        }

    } catch (Exception $e) {
        $error['db'] = $e->getMessage();
    }
}

// マスターデータ取得
try {
    $items = item_get_all($pdo);
    $casts = cast_get_all($pdo, 0); // drop_flg=0のキャストを取得
    $payments = payment_get_all($pdo);
} catch (PDOException $e) {
    error_log("Database error fetching master data: " . $e->getMessage());
    $error['db'] = "マスターデータの取得中にエラーが発生しました。";
}

disconnect($pdo);
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0,minimum-scale=1.0">
    <title>伝票修正</title>
    <link href="https://unpkg.com/sanitize.css" rel="stylesheet"/>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="content">
        <form action="receipt_modify.php?id=<?= htmlspecialchars($receipt_id, ENT_QUOTES) ?>" method="POST">
            <h2>伝票修正</h2>
            <?php if (!empty($error)): ?>
                <div class="error-message">
                    <?php foreach($error as $err): ?>
                        <p><?= htmlspecialchars($err, ENT_QUOTES) ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <p>伝票ID: <?= htmlspecialchars($receipt_id, ENT_QUOTES) ?></p>

            <table>
                <tbody>
                    <tr>
                        <th><label for="shop_mst">店舗コード</label></th>
                        <td>
                            <span class="check-info"><?= htmlspecialchars($shop_info['name'], ENT_QUOTES) ?></span>
                            <input type="hidden" name="shop_mst" value="<?= htmlspecialchars($shop_mst, ENT_QUOTES) ?>">
                        </td>
                    </tr>
                    <tr>
                        <th><label for="sheet_no">座席番号</label></th>
                        <td><input id="sheet_no" type="text" name="sheet_no" value="<?= htmlspecialchars($receipt_data['sheet_no'] ?? '', ENT_QUOTES) ?>"></td>
                    </tr>
                    <tr>
                        <th><label for="receipt_day">伝票集計日付</label></th>
                        <td><input id="receipt_day" type="date" name="receipt_day" value="<?= htmlspecialchars($receipt_data['receipt_day'] ?? '', ENT_QUOTES) ?>" required></td>
                    </tr>
                    <tr>
                        <th><label for="in_date">入店日付</label></th>
                        <td><input id="in_date" type="date" name="in_date" value="<?= htmlspecialchars($receipt_data['in_date'] ?? '', ENT_QUOTES) ?>"></td>
                    </tr>
                    <tr>
                        <th><label for="in_time">入店時間</label></th>
                        <td><input id="in_time" type="time" name="in_time" value="<?= htmlspecialchars($receipt_data['in_time'] ?? '', ENT_QUOTES) ?>" required></td>
                    </tr>
                    <tr>
                        <th><label for="out_date">退店日付</label></th>
                        <td><input id="out_date" type="date" name="out_date" value="<?= htmlspecialchars($receipt_data['out_date'] ?? '', ENT_QUOTES) ?>"></td>
                    </tr>
                    <tr>
                        <th><label for="out_time">退店時間</label></th>
                        <td><input id="out_time" type="time" name="out_time" value="<?= htmlspecialchars($receipt_data['out_time'] ?? '', ENT_QUOTES) ?>"></td>
                    </tr>
                    <tr>
                        <th><label for="customer_name">顧客名</label></th>
                        <td><input id="customer_name" type="text" name="customer_name" value="<?= htmlspecialchars($receipt_data['customer_name'] ?? '', ENT_QUOTES) ?>"></td>
                    </tr>
                    <tr>
                        <th><label for="issuer_id">伝票起票者</label></th>
                        <td>
                            <select name="issuer_id" id="issuer_id">
                                <option value=""></option>
                                <?php foreach ($casts as $cast): ?>
                                    <option value="<?= htmlspecialchars($cast['cast_id']) ?>"
                                        <?= (($receipt_data['issuer_id'] ?? '') == $cast['cast_id']) ? 'selected' : ''; ?>>
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
                                <option value=""></option>
                                <?php foreach ($payments as $payment): ?>
                                    <option value="<?= htmlspecialchars($payment['payment_type']) ?>"
                                        <?= (($receipt_data['payment_type'] ?? '') == $payment['payment_type']) ? 'selected' : ''; ?>>
                                        <?= htmlspecialchars($payment['payment_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="adjust_price">調整額</label></th>
                        <td><input id="adjust_price" type="number" name="adjust_price" value="<?= htmlspecialchars($receipt_data['adjust_price'] ?? 0, ENT_QUOTES) ?>"></td>
                    </tr>
                </tbody>
            </table>
            <hr>
            <h3>商品明細</h3>
            <table id="item-details">
                <thead>
                    <tr>
                        <th>商品</th>
                        <th>数量</th>
                        <th>キャスト</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // 明細データが11件以上ある場合は、11件に切り詰める
                    $detail_to_display = array_slice($detail_data, 0, 11);
                    for ($i = 0; $i < 11; $i++) {
                        $current_detail = $detail_to_display[$i] ?? null;
                        $receipt_detail_id = $current_detail['receipt_detail_id'] ?? null;
                        $item_id = $current_detail['item_id'] ?? null;
                        $quantity = $current_detail['quantity'] ?? null;
                        $cast_id = $current_detail['cast_id'] ?? null;

                        // POST送信失敗時に$_POSTの値を反映
                        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                            $item_id = $_POST['item_name' . ($i + 1)] ?? null;
                            $quantity = $_POST['suu' . ($i + 1)] ?? null;
                            $cast_id = $_POST['cast_name' . ($i + 1)] ?? null;
                        }
                    ?>
                    <tr>
                        <td>
                            <select name="item_name<?= $i + 1 ?>">
                                <option value=""></option>
                                <?php foreach ($items as $item): ?>
                                    <option value="<?= htmlspecialchars($item['item_id']) ?>"
                                        <?= (($item_id) == $item['item_id']) ? 'selected' : ''; ?>>
                                        <?= htmlspecialchars($item['item_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td><input type="number" name="suu<?= $i + 1 ?>" value="<?= htmlspecialchars($quantity) ?>"></td>
                        <td>
                            <select name="cast_name<?= $i + 1 ?>">
                                <option value=""></option>
                                <?php foreach ($casts as $cast): ?>
                                    <option value="<?= htmlspecialchars($cast['cast_id']) ?>"
                                        <?= (($cast_id) == $cast['cast_id']) ? 'selected' : ''; ?>>
                                        <?= htmlspecialchars($cast['cast_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <input type="hidden" name="receipt_detail_id<?= $i + 1 ?>" value="<?= htmlspecialchars($receipt_detail_id) ?>">
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
            <hr>
            <div class="control">
                <button type="submit" class="btn">修正</button>
                <a href="receipt_confirm.php" class="btn">戻る</a>
                <a href="index.php" class="btn">メニューへ</a>
            </div>
        </form>
    </div>
</body>
</html>
