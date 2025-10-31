<?php
// エラー表示を有効にする
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once(__DIR__ . '/../common/config.php');
require_once(__DIR__ . '/../common/dbconnect.php');
require_once(__DIR__ . '/../common/functions.php');
session_start();

$receipt_id = $_GET['receipt_id'] ?? null;
if (!$receipt_id) {
    error_log("伝票IDが指定されていません。");
    echo "エラー: 伝票IDが指定されていません。";
    exit;
}

$error = [];
$pdo = null;
$receipt = null;
$details = [];
$items = [];
$casts = [];
$payments = [];

try {
    $pdo = connect();
    $shop_info = get_shop_info($_SESSION['utype'] ?? 0);
    $shop_mst = $shop_info['id'] ?? null;

    if (!$shop_mst) {
        throw new Exception("店舗情報が取得できませんでした。");
    }

    // トランザクションを開始
    $pdo->beginTransaction();

    // 伝票データの取得
    $stmt = $pdo->prepare("SELECT * FROM receipt_tbl WHERE receipt_id = ? AND shop_id = ?");
    $stmt->execute([$receipt_id, $shop_mst]);
    $receipt = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($receipt) {
        // 伝票明細データの取得
        $stmt = $pdo->prepare("SELECT * FROM receipt_detail_tbl WHERE receipt_id = ? ORDER BY receipt_detail_id ASC");
        $stmt->execute([$receipt_id]);
        $details = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // 伝票が存在しない
        $error[] = "指定された伝票が見つかりませんでした。伝票ID: " . $receipt_id;
        $pdo->rollBack();
        error_log("Receipt with ID: " . $receipt_id . " not found for shop ID: " . $shop_mst);
    }
    
    // 商品マスターデータの取得
    $items = item_get_all($pdo);
    $items_indexed = [];
    foreach ($items as $item) {
        $items_indexed[$item['item_id']] = $item;
    }

    // キャストマスターデータの取得
    $casts = cast_get_all($pdo);
    // cast_idをキーとした連想配列に変換
    $casts_indexed = [];
    foreach ($casts as $cast) {
        $casts_indexed[$cast['cast_id']] = $cast;
    }

    // 支払い方法マスターデータの取得
    $stmt = $pdo->prepare("SELECT * FROM payment_mst ORDER BY payment_type");
    $stmt->execute();
    $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // フォームの送信を処理
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['update'])) {
            // 伝票データの更新
            $new_in_date_raw = $_POST['in_date'];
            $new_in_time_raw = $_POST['in_time'];
            $new_out_date_raw = $_POST['out_date'];
            $new_out_time_raw = $_POST['out_time'];
            $new_receipt_day_raw = $_POST['receipt_day'];
            // 数値系は空文字/NULLを0に正規化（MySQL8の厳格モード対策）
            $new_issuer_id = isset($_POST['issuer_id']) && $_POST['issuer_id'] !== '' ? (int)$_POST['issuer_id'] : 0;
            $new_payment_type = isset($_POST['payment_type']) && $_POST['payment_type'] !== '' ? (int)$_POST['payment_type'] : 0;
            $new_adjust_price = isset($_POST['adjust_price']) && $_POST['adjust_price'] !== '' ? (int)$_POST['adjust_price'] : 0;
            $new_customer_name = $_POST['customer_name'];
            
            // YYYY-MM-DD形式からYYYYMMDD形式に変換
            $new_in_date = str_replace('-', '', $new_in_date_raw);
            $new_in_time = str_replace(':', '', $new_in_time_raw);
            $new_out_date = str_replace('-', '', $new_out_date_raw);
            $new_out_time = str_replace(':', '', $new_out_time_raw);
            $new_receipt_day = str_replace('-', '', $new_receipt_day_raw);

            $stmt = $pdo->prepare("UPDATE receipt_tbl SET customer_name = ?, in_date = ?, in_time = ?, out_date = ?, out_time = ?, receipt_day = ?, issuer_id = ?, payment_type = ?, adjust_price = ? WHERE receipt_id = ?");
            $stmt->execute([$new_customer_name, $new_in_date, $new_in_time, $new_out_date, $new_out_time, $new_receipt_day, $new_issuer_id, $new_payment_type, $new_adjust_price, $receipt_id]);

            // 既存明細の更新または新規明細の追加
            $submitted_details = $_POST['details'];
            
            for ($i = 0; $i < 11; $i++) {
                $detail = $submitted_details[$i];
                $item_id = isset($detail['item_id']) && $detail['item_id'] !== '' ? (int)$detail['item_id'] : 0;
                $quantity = isset($detail['quantity']) && $detail['quantity'] !== '' ? (int)$detail['quantity'] : 0;
                $cast_id = isset($detail['cast_id']) && $detail['cast_id'] !== '' ? (int)$detail['cast_id'] : 0;
                $receipt_detail_id = $detail['receipt_detail_id'] ?? null;
                
                // item_idが空の場合は、値をリセット
                if (empty($item_id)) {
                    $item_id = 0;
                    $quantity = 0;
                    $price = 0;
                    $cast_id = 0;
                    $cast_back_price = 0;
                } else {
                    $item_data = $items_indexed[$item_id] ?? null;
                    $price = $item_data['price'] ?? 0;
                    $cast_back_price = ($item_data['back_price'] ?? 0) * ($quantity ?? 0);
                    // cast_idが空の場合に0を設定
                    if (empty($cast_id)) {
                        $cast_id = 0;
                    }
                }
                
                if (!empty($receipt_detail_id)) {
                    // 既存明細の更新と伝票日付の更新
                    $stmt = $pdo->prepare("UPDATE receipt_detail_tbl SET receipt_day = ?, item_id = ?, quantity = ?, price = ?, cast_id = ?, cast_back_price = ? WHERE receipt_detail_id = ?");
                    $stmt->execute([$new_receipt_day, $item_id, $quantity, $price, $cast_id, $cast_back_price, $receipt_detail_id]);
                } else {
                    // 新規明細の追加（11行未満の場合のみ）
                    if (count($details) + $i < 11) {
                         $stmt = $pdo->prepare("INSERT INTO receipt_detail_tbl (receipt_id, item_id, quantity, price, cast_id, cast_back_price, receipt_day) VALUES (?, ?, ?, ?, ?, ?, ?)");
                         $stmt->execute([$receipt_id, $item_id, $quantity, $price, $cast_id, $cast_back_price, $new_receipt_day]);
                    }
                }
            }

            $pdo->commit();
            header("Location: receipt_detail.php?receipt_id=" . $receipt_id . "&status=updated");
            exit;
        } elseif (isset($_POST['delete_receipt'])) {
            // 伝票全体の削除
            $stmt = $pdo->prepare("DELETE FROM receipt_detail_tbl WHERE receipt_id = ?");
            $stmt->execute([$receipt_id]);
            $stmt = $pdo->prepare("DELETE FROM receipt_tbl WHERE receipt_id = ?");
            $stmt->execute([$receipt_id]);

            $pdo->commit();
            header("Location: summary_result.php?status=deleted");
            exit;
        }
    }
} catch (Exception $e) {
    $error[] = "エラー: " . $e->getMessage();
    if ($pdo && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Exception: " . $e->getMessage());
} finally {
    disconnect($pdo);
}

// 金額をカンマ区切りでフォーマットするヘルパー関数
function format_price($price) {
    return number_format($price) . '円';
}

// 伝票明細を11行に固定する
$fixed_details = array_pad($details, 11, null);

// YYYYMMDD形式からYYYY-MM-DD形式に変換
// $receipt が null の場合に配列アクセスで警告が出ないようにガード
$receipt_day_formatted = (isset($receipt['receipt_day']) && $receipt['receipt_day']) ? date('Y-m-d', strtotime($receipt['receipt_day'])) : '';
$in_date_formatted = (isset($receipt['in_date']) && $receipt['in_date']) ? date('Y-m-d', strtotime($receipt['in_date'])) : '';
$in_time_formatted = (isset($receipt['in_time']) && $receipt['in_time']) ? date('H:i', strtotime($receipt['in_time'])) : '';
$out_date_formatted = (isset($receipt['out_date']) && $receipt['out_date']) ? date('Y-m-d', strtotime($receipt['out_date'])) : '';
$out_time_formatted = (isset($receipt['out_time']) && $receipt['out_time']) ? date('H:i', strtotime($receipt['out_time'])) : '';
?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0,minimum-scale=1.0">
    <title>伝票詳細</title>
    <link href="https://unpkg.com/sanitize.css" rel="stylesheet"/>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .detail-card {
            background-color: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-bottom: 20px;
        }
        .detail-card h2 {
            margin-top: 0;
            border-bottom: 2px solid #3498db;
            padding-bottom: 10px;
            color: #333;
        }
        .detail-card p {
            margin-bottom: 10px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .form-group input, .form-group select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9em;
            margin-bottom: 20px;
        }
        th, td {
            border: 1px solid #ccc;
            padding: 8px;
            text-align: center;
        }
        th {
            background-color: #f2f2f2;
        }
        .btn-group {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1em;
            text-decoration: none;
            text-align: center;
            color: #fff;
        }
        .btn-update {
            background-color: #27ae60;
        }
        .btn-delete {
            background-color: #e74c3c;
        }
        .btn-back {
            background-color: #3498db;
        }
        .delete-btn {
            background-color: #e74c3c;
            color: white;
            border: none;
            padding: 5px 10px;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <div class="content">
        <?php if (!empty($error) || !$receipt): ?>
            <div class="error-message">
                <p>伝票の取得中にエラーが発生しました。一覧に戻って再度お試しください。<br><?= htmlspecialchars(implode('<br>', $error)) ?></p>
            </div>
            <a href="summary_result.php" class="btn btn-back">一覧に戻る</a>
        <?php else: ?>
            <h1>伝票詳細 (伝票番号: <?= htmlspecialchars($receipt['receipt_id']) ?>)</h1>

            <?php if (isset($_GET['status']) && $_GET['status'] === 'updated'): ?>
                <p style="color: green;">伝票が正常に更新されました。</p>
            <?php endif; ?>

            <form action="receipt_detail.php?receipt_id=<?= htmlspecialchars($receipt_id) ?>" method="POST" id="receipt-form">
                <input type="hidden" name="utype" value="<?= htmlspecialchars($_SESSION['utype'] ?? 0) ?>">
                
                <div class="detail-card">
                    <h2>基本情報</h2>
                    <div class="form-group">
                        <label for="customer_name">顧客名</label>
                        <input type="text" name="customer_name" id="customer_name" value="<?= htmlspecialchars($receipt['customer_name'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label for="receipt_day">伝票日付</label>
                        <input type="date" name="receipt_day" value="<?= htmlspecialchars($receipt_day_formatted) ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="in_date">入店日時</label>
                        <input type="date" name="in_date" id="in_date" value="<?= htmlspecialchars($in_date_formatted) ?>">
                        <input type="time" name="in_time" id="in_time" value="<?= htmlspecialchars($in_time_formatted) ?>">
                    </div>

                    <div class="form-group">
                        <label for="out_date">退店日時</label>
                        <input type="date" name="out_date" id="out_date" value="<?= htmlspecialchars($out_date_formatted) ?>">
                        <input type="time" name="out_time" id="out_time" value="<?= htmlspecialchars($out_time_formatted) ?>">
                    </div>

                    <div class="form-group">
                        <label for="issuer_id">起票者</label>
                        <select name="issuer_id">
                            <option value="" <?= ($receipt['issuer_id'] == 0 || $receipt['issuer_id'] === null) ? 'selected' : '' ?>>-- 選択 --</option>
                            <?php foreach ($casts as $cast): ?>
                                <option value="<?= htmlspecialchars($cast['cast_id']) ?>" <?= ($cast['cast_id'] == $receipt['issuer_id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cast['cast_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="payment_type">支払い方法</label>
                        <select name="payment_type" required>
                            <?php foreach ($payments as $payment): ?>
                                <option value="<?= htmlspecialchars($payment['payment_type']) ?>" <?= ($payment['payment_type'] == $receipt['payment_type']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($payment['payment_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="adjust_price">調整額</label>
                        <input type="number" name="adjust_price" value="<?= htmlspecialchars($receipt['adjust_price']) ?>">
                    </div>
                </div>

                <div class="detail-card">
                    <h2>伝票明細</h2>
                    <table>
                        <thead>
                            <tr>
                                <th>項目</th>
                                <th>数量</th>
                                <th>担当キャスト</th>
                                <th>小計</th>
                                <th>バック金額</th>
                            </tr>
                        </thead>
                        <tbody id="details-table-body">
                            <?php foreach ($fixed_details as $index => $detail): ?>
                                <tr>
                                    <td>
                                        <select name="details[<?= $index ?>][item_id]">
                                            <option value="" <?= (!$detail || $detail['item_id'] == 0) ? 'selected' : '' ?>></option>
                                            <?php foreach ($items as $item): ?>
                                                <option value="<?= htmlspecialchars($item['item_id']) ?>" <?= ($detail && $item['item_id'] == $detail['item_id']) ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($item['item_name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td>
                                        <input type="number" name="details[<?= $index ?>][quantity]" value="<?= ($detail && $detail['item_id'] > 0) ? htmlspecialchars($detail['quantity']) : '' ?>" min="1">
                                        <input type="hidden" name="details[<?= $index ?>][receipt_detail_id]" value="<?= ($detail) ? htmlspecialchars($detail['receipt_detail_id']) : '' ?>">
                                    </td>
                                    <td>
                                        <select name="details[<?= $index ?>][cast_id]">
                                            <option value="" <?= (!$detail || ($detail['cast_id'] == 0)) ? 'selected' : '' ?>></option>
                                            <?php foreach ($casts as $cast): ?>
                                                <option value="<?= htmlspecialchars($cast['cast_id']) ?>" <?= ($detail && $cast['cast_id'] == $detail['cast_id']) ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($cast['cast_name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td><?= ($detail && $detail['item_id'] > 0) ? format_price($detail['price'] * $detail['quantity']) : '-' ?></td>
                                    <td><?= ($detail && $detail['item_id'] > 0) ? format_price($detail['cast_back_price']) : '-' ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="btn-group">
                    <button type="submit" name="update" class="btn btn-update">伝票を更新</button>
                    <button type="button" class="btn btn-delete" onclick="confirmDelete()">伝票を削除</button>
                </div>
            </form>

            <form action="receipt_detail.php?receipt_id=<?= htmlspecialchars($receipt_id) ?>" method="POST" id="delete-form" style="display:none;">
                <input type="hidden" name="delete_receipt" value="1">
            </form>

            <a href="summary_result.php" class="btn btn-back">一覧に戻る</a>

            <script>
                function confirmDelete() {
                    if (confirm("本当にこの伝票を削除しますか？\nこの操作は元に戻せません。")) {
                        document.getElementById('delete-form').submit();
                    }
                }
            </script>
        <?php endif; ?>
    </div>
</body>
</html>
