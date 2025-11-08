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
            $new_sheet_no = isset($_POST['sheet_no']) && $_POST['sheet_no'] !== '' ? (int)$_POST['sheet_no'] : 0;
            // 数値系は空文字/NULLを0に正規化（MySQL8の厳格モード対策）
            $new_issuer_id = isset($_POST['issuer_id']) && $_POST['issuer_id'] !== '' ? (int)$_POST['issuer_id'] : 0;
            $new_payment_type = isset($_POST['payment_type']) && $_POST['payment_type'] !== '' ? (int)$_POST['payment_type'] : 0;
            $new_adjust_price = isset($_POST['adjust_price']) && $_POST['adjust_price'] !== '' ? (int)$_POST['adjust_price'] : 0;
            $new_customer_name = $_POST['customer_name'];
            $new_is_new_customer = isset($_POST['is_new_customer']) ? 1 : 0;
            
            // YYYY-MM-DD形式からYYYYMMDD形式に変換
            $new_in_date = str_replace('-', '', $new_in_date_raw);
            $new_in_time = str_replace(':', '', $new_in_time_raw);
            $new_out_date = str_replace('-', '', $new_out_date_raw);
            $new_out_time = str_replace(':', '', $new_out_time_raw);
            $new_receipt_day = str_replace('-', '', $new_receipt_day_raw);

            $stmt = $pdo->prepare("UPDATE receipt_tbl SET customer_name = ?, sheet_no = ?, in_date = ?, in_time = ?, out_date = ?, out_time = ?, receipt_day = ?, issuer_id = ?, payment_type = ?, adjust_price = ?, is_new_customer = ? WHERE receipt_id = ?");
            $stmt->execute([$new_customer_name, $new_sheet_no, $new_in_date, $new_in_time, $new_out_date, $new_out_time, $new_receipt_day, $new_issuer_id, $new_payment_type, $new_adjust_price, $new_is_new_customer, $receipt_id]);

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

// 伝票明細を11行に固定する
$fixed_details = array_pad($details, 11, null);

// YYYYMMDD形式からYYYY-MM-DD形式に変換
// $receipt が null の場合に配列アクセスで警告が出ないようにガード
$receipt_day_formatted = (isset($receipt['receipt_day']) && $receipt['receipt_day']) ? date('Y-m-d', strtotime($receipt['receipt_day'])) : '';
$in_date_formatted = (isset($receipt['in_date']) && $receipt['in_date']) ? date('Y-m-d', strtotime($receipt['in_date'])) : '';
$in_time_formatted = (isset($receipt['in_time']) && $receipt['in_time']) ? date('H:i', strtotime($receipt['in_time'])) : '';
$out_date_formatted = (isset($receipt['out_date']) && $receipt['out_date']) ? date('Y-m-d', strtotime($receipt['out_date'])) : '';
$out_time_formatted = (isset($receipt['out_time']) && $receipt['out_time']) ? date('H:i', strtotime($receipt['out_time'])) : '';
$is_new_customer_flag = (isset($receipt['is_new_customer']) && $receipt['is_new_customer']) ? 1 : 0;
$is_new_customer_checked = $is_new_customer_flag === 1 ? 'checked' : '';
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0,minimum-scale=1.0">
    <title>伝票修正</title>
    <link href="https://unpkg.com/sanitize.css" rel="stylesheet"/>
    <link rel="stylesheet" href="css/style.css">
    <style>
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            border: 1px solid #ccc;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
            width: 20%;
        }
        .check-info {
            font-weight: bold;
        }
        .required {
            color: red;
            font-size: 0.9em;
            margin-left: 4px;
        }
        .category-buttons {
            margin-bottom: 20px;
            text-align: left;
        }
        .category-buttons button {
            padding: 10px 20px;
            margin: 5px;
            font-size: 1em;
            cursor: pointer;
        }
        .receipt-grid {
            display: grid;
            grid-template-columns: 200px 150px 60px max-content;
            gap: 5px;
            align-items: center;
        }
        .receipt-grid .header-item {
            font-weight: bold;
            text-align: left;
            padding-bottom: 2px;
            white-space: nowrap;
        }
        .receipt-grid .item-row {
            display: contents;
        }
        .receipt-grid .item-row > * {
            border-bottom: 1px solid #ccc;
            padding: 3px 0;
        }
        .receipt-grid select,
        .receipt-grid input[type="number"] {
            height: 28px;
            box-sizing: border-box;
        }
        .item-action-buttons {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .item-action-buttons button {
            height: 28px;
            padding: 0 10px;
            font-size: 0.85em;
            cursor: pointer;
        }
        .quantity-input {
            width: 50px;
            text-align: right;
        }
        .error-message {
            color: red;
            font-size: 0.8em;
            margin-top: 4px;
        }
        .success-message {
            color: #28a745;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .submit-btn {
            padding: 20px 0;
            display: flex;
            justify-content: center;
        }
        .submit-btn .next-btn {
            width: 60%;
            font-size: 1.1em;
            padding: 16px 0;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: bold;
            box-shadow: 0 4px 8px rgba(0, 123, 255, 0.3);
            transition: all 0.3s ease;
        }
        .submit-btn .next-btn:hover {
            background-color: #0069d9;
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0, 123, 255, 0.4);
        }
        .btn-group {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-bottom: 30px;
        }
        .btn {
            padding: 10px 18px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1em;
            text-decoration: none;
            text-align: center;
            color: #fff;
            min-width: 140px;
        }
        .btn-update,
        .next-btn {
            background-color: #27ae60;
        }
        .btn-delete {
            background-color: #e74c3c;
        }
        .btn-back {
            background-color: #3498db;
        }
        .info-text {
            margin-bottom: 10px;
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
            <form action="receipt_detail.php?receipt_id=<?= htmlspecialchars($receipt_id) ?>" method="POST" id="receipt-form">
                <input type="hidden" name="utype" value="<?= htmlspecialchars($_SESSION['utype'] ?? 0) ?>">
                <h1>伝票修正 (伝票番号: <?= htmlspecialchars($receipt['receipt_id']) ?>)</h1>
                <p class="info-text">入力画面と同じ感覚で修正いただけます。完了後は「更新する」を押してください。</p>
                <hr>

                <?php if (isset($_GET['status']) && $_GET['status'] === 'updated'): ?>
                    <p class="success-message">伝票が正常に更新されました。</p>
                <?php endif; ?>

                <h2>伝票基本情報</h2>
                <table>
                    <tbody>
                        <tr>
                            <th>店舗</th>
                            <td>
                                <span class="check-info">
                                    コード: <?= htmlspecialchars($shop_info['id'] ?? '') ?> / 店舗名: <?= htmlspecialchars($shop_info['name'] ?? '') ?>
                                </span>
                            </td>
                            <th>座席番号</th>
                            <td><input type="text" name="sheet_no" value="<?= htmlspecialchars($receipt['sheet_no'] ?? '') ?>"></td>
                        </tr>
                        <tr>
                            <th>伝票集計日付<span class="required">*</span></th>
                            <td><input type="date" name="receipt_day" value="<?= htmlspecialchars($receipt_day_formatted) ?>" required></td>
                            <td></td>
                            <td></td>
                        </tr>
                        <tr>
                            <th>入店日付</th>
                            <td><input type="date" name="in_date" value="<?= htmlspecialchars($in_date_formatted) ?>"></td>
                            <th>入店時間</th>
                            <td><input type="time" name="in_time" value="<?= htmlspecialchars($in_time_formatted) ?>"></td>
                        </tr>
                        <tr>
                            <th>退店日付</th>
                            <td><input type="date" name="out_date" value="<?= htmlspecialchars($out_date_formatted) ?>"></td>
                            <th>退店時間</th>
                            <td><input type="time" name="out_time" value="<?= htmlspecialchars($out_time_formatted) ?>"></td>
                        </tr>
                        <tr>
                            <th>顧客名</th>
                            <td><input type="text" name="customer_name" value="<?= htmlspecialchars($receipt['customer_name'] ?? '') ?>"></td>
                            <th>伝票起票者</th>
                            <td>
                                <select name="issuer_id">
                                    <option value="" <?= ($receipt['issuer_id'] == 0 || $receipt['issuer_id'] === null) ? 'selected' : '' ?>></option>
                                    <?php foreach ($casts as $cast): ?>
                                        <option value="<?= htmlspecialchars($cast['cast_id']) ?>" <?= ($cast['cast_id'] == $receipt['issuer_id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($cast['cast_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th>新規顧客</th>
                            <td colspan="3">
                                <label>
                                    <input type="checkbox" name="is_new_customer" value="1" <?= $is_new_customer_checked ?>>
                                    新規顧客の場合はチェックしてください
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th>支払い方法<span class="required">*</span></th>
                            <td>
                                <select name="payment_type" required>
                                    <?php foreach ($payments as $payment): ?>
                                        <option value="<?= htmlspecialchars($payment['payment_type']) ?>" <?= ($payment['payment_type'] == $receipt['payment_type']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($payment['payment_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <th>調整額</th>
                            <td><input type="number" name="adjust_price" value="<?= htmlspecialchars($receipt['adjust_price']) ?>"></td>
                        </tr>
                    </tbody>
                </table>

                <h2>伝票明細</h2>
                <div class="category-buttons">
                    <button type="button" class="category-filter-btn" data-category="all">全部</button>
                    <button type="button" class="category-filter-btn" data-category="1">通常</button>
                    <button type="button" class="category-filter-btn" data-category="2">シャンパン</button>
                    <button type="button" class="category-filter-btn" data-category="3">フード</button>
                    <button type="button" class="category-filter-btn" data-category="4">イベント</button>
                    <button type="button" class="category-filter-btn" data-category="7">遠隔</button>
                    <button type="button" id="quantity-1-btn" style="margin-left:5px;">数量1</button>
                </div>

                <div class="receipt-grid">
                    <div class="header-item">商品名</div>
                    <div class="header-item">キャスト名</div>
                    <div class="header-item">数量</div>
                    <div class="header-item"></div>
                    <?php foreach ($fixed_details as $index => $detail): ?>
                        <?php
                            $rowNumber = $index + 1;
                            $quantityValue = ($detail && ($detail['item_id'] ?? 0) > 0) ? $detail['quantity'] : '';
                            $priceValue = ($detail && ($detail['item_id'] ?? 0) > 0) ? $detail['price'] : '';
                        ?>
                        <div class="item-row">
                            <select name="details[<?= $index ?>][item_id]" id="item_name<?= $rowNumber ?>" class="item-name-select" data-row="<?= $rowNumber ?>">
                                <option value="" data-back-price="0" data-category="0" data-price="0" <?= (!$detail || ($detail['item_id'] ?? 0) == 0) ? 'selected' : '' ?>></option>
                                <?php foreach ($items as $item): ?>
                                    <option value="<?= htmlspecialchars($item['item_id']) ?>"
                                        data-back-price="<?= htmlspecialchars($item['back_price'] ?? 0) ?>"
                                        data-category="<?= htmlspecialchars($item['category'] ?? '') ?>"
                                        data-price="<?= htmlspecialchars($item['price'] ?? 0) ?>"
                                        <?= ($detail && ($item['item_id'] == ($detail['item_id'] ?? 0))) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($item['item_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <select name="details[<?= $index ?>][cast_id]" id="cast_name<?= $rowNumber ?>" class="cast-name-select">
                                <option value="" <?= (!$detail || ($detail['cast_id'] ?? 0) == 0) ? 'selected' : '' ?>></option>
                                <?php foreach ($casts as $cast): ?>
                                    <option value="<?= htmlspecialchars($cast['cast_id']) ?>"
                                        <?= ($detail && ($cast['cast_id'] == ($detail['cast_id'] ?? 0))) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($cast['cast_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <input type="number" name="details[<?= $index ?>][quantity]" id="suu<?= $rowNumber ?>" min="1" step="1"
                                value="<?= htmlspecialchars($quantityValue) ?>" class="quantity-input">
                            <div class="item-action-buttons">
                                <button type="button" class="kampai-btn" data-row="<?= $rowNumber ?>" data-item-id="2">乾杯</button>
                                <?php if ($rowNumber > 1): ?>
                                    <button type="button" class="copy-item-btn" data-row="<?= $rowNumber ?>">↑</button>
                                    <button type="button" class="copy-cast-btn" data-row="<?= $rowNumber ?>">C</button>
                                <?php endif; ?>
                            </div>
                            <input type="hidden" name="details[<?= $index ?>][price]" id="price<?= $rowNumber ?>" value="<?= htmlspecialchars($priceValue) ?>">
                            <input type="hidden" name="details[<?= $index ?>][receipt_detail_id]" value="<?= ($detail) ? htmlspecialchars($detail['receipt_detail_id']) : '' ?>">
                            <div class="error-message" id="error-message-<?= $rowNumber ?>" style="grid-column: 1 / span 4;"></div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="submit-btn">
                    <button type="submit" name="update" class="btn next-btn" id="update_btn">更新する</button>
                </div>

                <div class="btn-group">
                    <button type="button" class="btn btn-delete" onclick="confirmDelete()">伝票を削除</button>
                    <a href="summary_result.php" class="btn btn-back">一覧に戻る</a>
                </div>
            </form>

            <form action="receipt_detail.php?receipt_id=<?= htmlspecialchars($receipt_id) ?>" method="POST" id="delete-form" style="display:none;">
                <input type="hidden" name="delete_receipt" value="1">
            </form>

            <script>
                function confirmDelete() {
                    if (confirm("本当にこの伝票を削除しますか？\nこの操作は元に戻せません。")) {
                        document.getElementById('delete-form').submit();
                    }
                }

                document.addEventListener('DOMContentLoaded', function() {
                    const totalRows = 11;
                    let currentCategory = 'all';

                    const applyCategoryFilter = (categoryId) => {
                        document.querySelectorAll('.item-name-select').forEach(selectEl => {
                            Array.from(selectEl.options).forEach(option => {
                                const itemCategory = option.getAttribute('data-category');
                                if (categoryId === 'all' || itemCategory === categoryId || option.value === '') {
                                    option.style.display = '';
                                } else {
                                    option.style.display = 'none';
                                }
                            });
                        });
                    };

                    document.querySelectorAll('.category-filter-btn').forEach(button => {
                        button.addEventListener('click', function() {
                            currentCategory = this.getAttribute('data-category');
                            applyCategoryFilter(currentCategory);
                        });
                    });

                    document.querySelectorAll('.item-name-select').forEach(selectElement => {
                        selectElement.addEventListener('change', function() {
                            const row = this.dataset.row;
                            const selectedOption = this.options[this.selectedIndex];
                            const price = selectedOption.getAttribute('data-price') || '';
                            document.getElementById('price' + row).value = price;

                            if (!this.value) {
                                document.getElementById('suu' + row).value = '';
                                document.getElementById('cast_name' + row).value = '';
                            }
                        });
                    });

                    document.querySelectorAll('.kampai-btn').forEach(button => {
                        button.addEventListener('click', function() {
                            const row = this.getAttribute('data-row');
                            const itemId = this.getAttribute('data-item-id');
                            const itemSelect = document.getElementById('item_name' + row);
                            itemSelect.value = itemId;
                            const selectedOption = itemSelect.options[itemSelect.selectedIndex];
                            const price = selectedOption.getAttribute('data-price') || '';
                            document.getElementById('price' + row).value = price;
                            document.getElementById('suu' + row).value = '';
                            document.getElementById('cast_name' + row).value = '';
                        });
                    });

                    document.querySelectorAll('.copy-item-btn').forEach(button => {
                        button.addEventListener('click', function() {
                            const currentRow = parseInt(this.getAttribute('data-row'), 10);
                            const prevRow = currentRow - 1;
                            const prevItem = document.getElementById('item_name' + prevRow).value;
                            const prevPrice = document.getElementById('price' + prevRow).value;

                            document.getElementById('item_name' + currentRow).value = prevItem;
                            document.getElementById('price' + currentRow).value = prevPrice;
                        });
                    });

                    document.querySelectorAll('.copy-cast-btn').forEach(button => {
                        button.addEventListener('click', function() {
                            const currentRow = parseInt(this.getAttribute('data-row'), 10);
                            const prevRow = currentRow - 1;
                            const prevCast = document.getElementById('cast_name' + prevRow).value;

                            if (prevCast) {
                                document.getElementById('cast_name' + currentRow).value = prevCast;
                            }
                        });
                    });

                    const quantityAllOneBtn = document.getElementById('quantity-1-btn');
                    if (quantityAllOneBtn) {
                        quantityAllOneBtn.addEventListener('click', function() {
                            for (let i = 1; i <= totalRows; i++) {
                                const itemSelect = document.getElementById('item_name' + i);
                                const quantityInput = document.getElementById('suu' + i);
                                if (itemSelect && itemSelect.value !== '') {
                                    quantityInput.value = 1;
                                }
                            }
                        });
                    }

                    document.getElementById('receipt-form').addEventListener('submit', function(e) {
                        document.querySelectorAll('.error-message').forEach(el => el.textContent = '');
                        let errorExists = false;
                        let firstErrorElement = null;

                        for (let i = 1; i <= totalRows; i++) {
                            const itemSelect = document.getElementById('item_name' + i);
                            const quantityInput = document.getElementById('suu' + i);
                            const castSelect = document.getElementById('cast_name' + i);
                            const errorMessage = document.getElementById('error-message-' + i);

                            if (itemSelect && itemSelect.value !== '') {
                                const quantity = parseInt(quantityInput.value, 10);
                                if (isNaN(quantity) || quantity <= 0) {
                                    errorMessage.textContent = '数量は1以上で入力してください。';
                                    if (!firstErrorElement) {
                                        firstErrorElement = quantityInput;
                                    }
                                    errorExists = true;
                                }

                                const selectedOption = itemSelect.options[itemSelect.selectedIndex];
                                const backPrice = Number(selectedOption.getAttribute('data-back-price') || 0);
                                if (backPrice > 0 && castSelect.value === '') {
                                    errorMessage.textContent = 'バックが設定されている商品はキャストの選択が必須です。';
                                    if (!firstErrorElement) {
                                        firstErrorElement = castSelect;
                                    }
                                    errorExists = true;
                                }
                            }
                        }

                        if (errorExists) {
                            e.preventDefault();
                            if (firstErrorElement) {
                                firstErrorElement.focus();
                            }
                        }
                    });

                    applyCategoryFilter(currentCategory);
                });
            </script>
        <?php endif; ?>
    </div>
</body>
</html>
