<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_cache_limiter('none');
session_start();

require("./dbconnect.php");
require("./functions.php");

$uid = null;
$utype = 0;

// URLのutypeを優先
if(isset($_GET['utype'])) {
    $utype = $_GET['utype'];
    $_SESSION['utype'] = $utype;
} elseif(isset($_POST['utype'])) { 
    $utype = $_POST['utype'];
    $_SESSION['utype'] = $utype;
} elseif(isset($_SESSION['utype'])) {
    $utype = $_SESSION['utype'];
} else {
    header("Location: index.php");
    exit();
}
$_SESSION['user_id'] = 1;

// セッションリセットのロジックを修正
// GETリクエストでis_backパラメータがない場合のみセッションをリセット
if ($_SERVER['REQUEST_METHOD'] === 'GET' && !isset($_GET['is_back']) && isset($_SESSION['join'])) {
    unset($_SESSION['join']);
}

// データベース接続を確立
try {
	$pdo = connect();
} catch (PDOException $e) {
	echo "データベース接続に失敗しました: " . $e->getMessage();
	exit();
}

// バリデーションのロジックを修正
// POSTデータがあり、POSTデータにis_backがない場合のみ実行
// (GETで渡されたis_backは無視される)
if(!empty($_POST) && !isset($_POST['is_back'])){
    $_SESSION['join'] = $_POST;
    $errors = [];

    // バリデーションチェック
    if (empty($_POST['receipt_day'])) {
        $errors['receipt_day'] = '伝票集計日付が未入力です。';
    }
    if (empty($_POST['in_date'])) {
        $errors['in_date'] = '入店日付が未入力です。';
    }
    if (empty($_POST['in_time'])) {
        $errors['in_time'] = '入店時間が未入力です。';
    }
    if (empty($_POST['p_type'])) {
        $errors['p_type'] = '支払い方法が未選択です。';
    }

    $has_item = false;
    for ($i = 1; $i <= 11; $i++) {
        $itemId = $_POST['item_name' . $i] ?? '';
        $quantity = $_POST['suu' . $i] ?? '';
        $castId = $_POST['cast_name' . $i] ?? '';

        if (!empty($itemId)) {
            $has_item = true;
            // 数量のバリデーション
            if (empty($quantity) || !is_numeric($quantity) || $quantity <= 0) {
                $errors['suu' . $i] = '数量は1以上で入力してください。';
            }
            // back_priceが設定されている場合のキャストチェック
            $itemInfo = item_get($pdo, $itemId);
            if ($itemInfo && $itemInfo['back_price'] > 0 && empty($castId)) {
                $errors['cast' . $i] = 'バックが設定されている商品はキャストの選択が必須です。';
            }
        }
    }

    if (!$has_item) {
        $errors['no_item'] = '伝票明細を少なくとも1つ入力してください。';
    }

    if (!empty($errors)) {
        $_SESSION['errors'] = $errors;
        header('Location: receipt_error.php');
        exit();
    }
    
    // エラーがなければ次のページへ
    header('Location: receipt_check.php');
    exit();
}

if(isset($_SESSION['join'])) {
    $receipt_day = $_SESSION['join']['receipt_day'];
}elseif(isset($_GET['initday'])) {
    $receipt_day = $_GET['initday'];
}else {
    $now = new DateTime();
    $receipt_day = $now->format('Y-m-d');
}

// 店舗情報を取得
$shop_info = get_shop_info($utype);

$items = [];
$item_categories = [];
try {
    $items = item_get_all($pdo);

    $stmt = $pdo->query("SELECT category_id, category_name FROM category_mst ORDER BY category_id ASC");
    $item_categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Database error fetching items or categories: " . $e->getMessage());
}

$casts = [];
try {
    $casts = cast_get_all($pdo);
} catch (PDOException $e) {
    error_log("Database error fetching casts: " . $e->getMessage());
}

$payments = [];
try {
    $payments = payment_get_all($pdo);
} catch (PDOException $e) {
    error_log("Database error fetching payments: " . $e->getMessage());
}

$items_with_back_price = [];
try {
    $stmt = $pdo->query("SELECT item_id FROM item_mst WHERE back_price >= 1");
    $items_with_back_price = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    error_log("Database error fetching items with back_price: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1.0,minimum-scale=1.0">
    <title>伝票登録入力画面</title>
    <link href="https://use.fontawesome.com/releases/v5.6.1/css/all.css" rel="stylesheet">
    <link href="https://unpkg.com/sanitize.css" rel="stylesheet"/>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .error-message {
            color: red;
            font-size: 0.8em;
            margin-top: 5px;
            display: block;
        }
        .item-action-buttons {
            display: flex;
            gap: 5px;
            justify-content: flex-start;
        }
        .item-action-buttons button {
            padding: 5px 10px;
            font-size: 0.8em;
            cursor: pointer;
            flex-shrink: 1;
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
        .input-suu {
            width: 40px; /* 数量入力欄の幅を調整 */
            box-sizing: border-box;
        }
        /* Flexboxレイアウトの再適用 */
        .receipt-grid {
            display: grid;
            grid-template-columns: 200px 150px 50px max-content;
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
        .receipt-grid .item-row select, .receipt-grid .item-row input {
            height: 25px; /* 高さを統一 */
            box-sizing: border-box;
        }
        /* ボタンの配置を修正 */
        .item-action-buttons {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        .item-action-buttons button {
            height: 25px;
            padding: 0 10px;
            font-size: 0.8em;
        }
        /* 数量入力欄のテキストを右揃えに設定 */
        .quantity-input {
            text-align: right;
        }
        /* 修正箇所: ボタンのサイズと配置を調整 */
        .submit-btn {
            padding: 20px 0;
            display: flex;
            justify-content: center;
        }
        .submit-btn .next-btn {
            width: 80%;
            font-size: 1.2em;
            padding: 15px 0;
        }
    </style>
</head>
<body>
    <div class="content">
        <form action="" method="POST">
            <h1>伝票登録</h1>
            <p>次のフォームに必要事項をご記入ください。</p>
            <hr>

            <h2>伝票基本情報</h2>
            <table>
                <tbody>
                    <tr>
                        <th>店舗</th>
                        <td>
                            <span class="check-info">コード: <?= htmlspecialchars($shop_info['id'], ENT_QUOTES) ?> / 店舗名: <?= htmlspecialchars($shop_info['name'], ENT_QUOTES) ?></span>
                            <input type="hidden" name="shop_mst" value="<?= htmlspecialchars($shop_info['id'], ENT_QUOTES) ?>">
                        </td>
                        <th>座席番号</th>
                        <td><input type="text" name="sheet_no" value="<?= htmlspecialchars($_SESSION['join']['sheet_no'] ?? '', ENT_QUOTES) ?>"></td>
                    </tr>
                    <tr>
                        <th>伝票集計日付<span class="required">*</span></th>
                        <td><input type="date" name="receipt_day" value="<?= htmlspecialchars($_SESSION['join']['receipt_day'] ?? $receipt_day, ENT_QUOTES) ?>" required></td>
                        <td></td>
                        <td></td>
                    </tr>
                    <tr>
                        <th>入店日付<span class="required">*</span></th>
                        <td><input type="date" name="in_date" value="<?= htmlspecialchars($_SESSION['join']['in_date'] ?? $receipt_day, ENT_QUOTES) ?>" required></td>
                        <th>入店時間<span class="required">*</span></th>
                        <td><input type="time" name="in_time" value="<?= htmlspecialchars($_SESSION['join']['in_time'] ?? '', ENT_QUOTES) ?>" required></td>
                    </tr>
                    <tr>
                        <th>顧客名</th>
                        <td><input type="text" name="customer_name" value="<?= htmlspecialchars($_SESSION['join']['customer_name'] ?? '', ENT_QUOTES) ?>"></td>
                        <th>伝票起票者</th>
                        <td>
                            <select name="issuer_id">
                                <option value=""></option>
                                <?php foreach ($casts as $cast): ?>
                                    <option value="<?= htmlspecialchars($cast['cast_id']) ?>"
                                        <?= (isset($_SESSION['join']['issuer_id']) && $_SESSION['join']['issuer_id'] == $cast['cast_id']) ? 'selected' : ''; ?>>
                                        <?= htmlspecialchars($cast['cast_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th>支払い方法<span class="required">*</span></th>
                        <td>
                            <select name="p_type" required>
                                <?php foreach ($payments as $payment): ?>
                                    <option value="<?= htmlspecialchars($payment['payment_type']) ?>"
                                        <?= (isset($_SESSION['join']['p_type']) && $_SESSION['join']['p_type'] == $payment['payment_type']) ? 'selected' : ''; ?>>
                                        <?= htmlspecialchars($payment['payment_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <th>調整額</th>
                        <td><input type="number" name="adjust_price" value="<?= htmlspecialchars($_SESSION['join']['adjust_price'] ?? 0, ENT_QUOTES) ?>"></td>
                    </tr>
                </tbody>
            </table>
            
            <h2>伝票明細</h2>
            <div class="category-buttons">
                <button type="button" class="category-filter-btn" data-category="all">全部</button>
                <button type="button" class="category-filter-btn" data-category="1">通常</button>
                <button type="button" class="category-filter-btn" data-category="2">シャンパン</button>
                <button type="button" class="category-filter-btn" data-category="3">フード</button>
                <button type="button" class="category-filter-btn" data-category="7">遠隔</button>
                <button type="button" id="quantity-1-btn" style="margin-left:5px;">数量1</button>
            </div>
            
            <div class="receipt-grid">
                <div class="header-item">商品名</div>
                <div class="header-item">キャスト名</div>
                <div class="header-item">数量</div>
                <div class="header-item"></div>
                <?php for ($i = 1; $i <= 11; $i++): ?>
                    <div class="item-row">
                        <select name="item_name<?= $i ?>" id="item_name<?= $i ?>" class="item-name-select">
                            <option value=""></option>
                            <?php foreach ($items as $item): ?>
                                <option value="<?= htmlspecialchars($item['item_id']) ?>" 
                                    data-back-price="<?= htmlspecialchars($item['back_price']) ?>"
                                    data-category="<?= htmlspecialchars($item['category']) ?>"
                                    data-price="<?= htmlspecialchars($item['price']) ?>"
                                    <?= (isset($_SESSION['join']["item_name$i"]) && $_SESSION['join']["item_name$i"] == $item['item_id']) ? 'selected' : ''; ?>>
                                    <?= htmlspecialchars($item['item_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <select name="cast_name<?= $i ?>" id="cast_name<?= $i ?>" class="cast-name-select">
                            <option value=""></option>
                            <?php foreach ($casts as $cast): ?>
                                <option value="<?= htmlspecialchars($cast['cast_id']) ?>"
                                    <?= (isset($_SESSION['join']["cast_name$i"]) && $_SESSION['join']["cast_name$i"] == $cast['cast_id']) ? 'selected' : ''; ?>>
                                    <?= htmlspecialchars($cast['cast_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="number" name="suu<?= $i ?>" id="suu<?= $i ?>" min="1" step="1"
                            value="<?= htmlspecialchars($_SESSION['join']["suu$i"] ?? '', ENT_QUOTES) ?>" class="quantity-input">
                        <div class="item-action-buttons">
                            <button type="button" class="kampai-btn" data-row="<?= $i ?>" data-item-id="2">乾杯</button>
                            <?php if ($i > 1): ?>
                                <button type="button" class="copy-item-btn" data-row="<?= $i ?>">↑</button>
                            <?php endif; ?>
                        </div>
                        <input type="hidden" name="price<?= $i ?>" id="price<?= $i ?>" value="<?= htmlspecialchars($_SESSION['join']["price$i"] ?? '', ENT_QUOTES) ?>">
                        <div class="error-message" id="error-message-<?= $i ?>" style="grid-column: 1 / span 4;"></div>
                    </div>
                <?php endfor; ?>
            </div>

            <div class="submit-btn">
                <button type="submit" id="next_btn" class="btn next-btn">次へ</button>
            </div>
        </form>
    </div>

    <script type="text/javascript">
        document.addEventListener('DOMContentLoaded', function() {
            // カテゴリーフィルターボタンの状態を管理
            let currentCategory = 'all';

            // 最新のデータを取得してコンボボックスを更新する関数
            const updateComboboxes = async () => {
                try {
                    const response = await fetch('get_latest_data.php');
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    const data = await response.json();
                    
                    // 商品名のコンボボックスを更新
                    const itemSelects = document.querySelectorAll('select[name^="item_name"]');
                    itemSelects.forEach(selectElement => {
                        const savedValue = selectElement.value;
                        selectElement.innerHTML = '<option value=""></option>';
                        data.items.forEach(item => {
                            const option = document.createElement('option');
                            option.value = item.item_id;
                            option.textContent = item.item_name;
                            option.setAttribute('data-back-price', item.back_price);
                            option.setAttribute('data-category', item.category);
                            option.setAttribute('data-price', item.price);
                            if (item.item_id == savedValue) {
                                option.selected = true;
                            }
                            selectElement.appendChild(option);
                        });
                    });

                    // キャスト名のコンボボックスを更新
                    const castSelects = document.querySelectorAll('select[name^="cast_name"]');
                    castSelects.forEach(selectElement => {
                        const savedValue = selectElement.value;
                        selectElement.innerHTML = '<option value=""></option>';
                        data.casts.forEach(cast => {
                            const option = document.createElement('option');
                            option.value = cast.cast_id;
                            option.textContent = cast.cast_name;
                            if (cast.cast_id == savedValue) {
                                option.selected = true;
                            }
                            selectElement.appendChild(option);
                        });
                    });
                    
                    // 最新データ読み込み後に、現在のカテゴリーフィルターを再適用
                    applyCategoryFilter(currentCategory);

                } catch (error) {
                    console.error('Failed to fetch and update data:', error);
                }
            };

            // カテゴリーフィルター適用関数
            const applyCategoryFilter = (categoryId) => {
                document.querySelectorAll('select[name^="item_name"]').forEach(itemSelect => {
                    itemSelect.querySelectorAll('option').forEach(option => {
                        const itemCategory = option.getAttribute('data-category');
                        if (categoryId === 'all') {
                            option.style.display = '';
                        } else if (itemCategory === categoryId) {
                            option.style.display = 'block';
                        } else {
                            option.style.display = 'none';
                        }
                    });
                });
            };

            // 初回表示時にコンボボックスを更新
            updateComboboxes();

            // 定期的に（例: 30秒ごとに）コンボボックスを更新
            setInterval(updateComboboxes, 30000);

            // 各行の商品選択時に価格を隠しフィールドに設定
            document.querySelectorAll('.item-name-select').forEach(selectElement => {
                selectElement.addEventListener('change', function() {
                    const row = this.id.replace('item_name', '');
                    const selectedOption = this.options[this.selectedIndex];
                    const price = selectedOption.getAttribute('data-price');
                    document.getElementById('price' + row).value = price;
                });
            });

            document.querySelectorAll('.kampai-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const row = this.getAttribute('data-row');
                    const itemId = this.getAttribute('data-item-id');
                    document.getElementById('item_name' + row).value = itemId;
                    
                    // 乾杯ドリンクの価格を取得して設定
                    const selectedOption = document.getElementById('item_name' + row).options[document.getElementById('item_name' + row).selectedIndex];
                    const price = selectedOption.getAttribute('data-price');
                    document.getElementById('price' + row).value = price;

                    document.getElementById('suu' + row).value = '';
                    document.getElementById('cast_name' + row).value = '';
                });
            });

            document.querySelectorAll('.copy-item-btn').forEach(button => {
                button.addEventListener('click', function() {
                    const currentRow = parseInt(this.getAttribute('data-row'));
                    const prevRow = currentRow - 1;
                    const prevItem = document.getElementById('item_name' + prevRow).value;
                    const prevPrice = document.getElementById('price' + prevRow).value;
                    
                    document.getElementById('item_name' + currentRow).value = prevItem;
                    document.getElementById('price' + currentRow).value = prevPrice;
                });
            });

            document.getElementById('quantity-1-btn').addEventListener('click', function() {
                for (let i = 1; i <= 11; i++) {
                    const itemSelect = document.getElementById('item_name' + i);
                    const quantityInput = document.getElementById('suu' + i);
                    if (itemSelect && itemSelect.value !== '') {
                        quantityInput.value = 1;
                    }
                }
            });

            document.querySelectorAll('.category-filter-btn').forEach(button => {
                button.addEventListener('click', function() {
                    currentCategory = this.getAttribute('data-category');
                    applyCategoryFilter(currentCategory);
                });
            });

            document.getElementById('next_btn').addEventListener('click', function(e) {
                document.querySelectorAll('.error-message').forEach(el => el.textContent = '');
                let errorExists = false;
                let firstErrorElement = null;

                for (let i = 1; i <= 11; i++) {
                    const itemSelect = document.getElementById('item_name' + i);
                    const quantityInput = document.getElementById('suu' + i);
                    const castSelect = document.getElementById('cast_name' + i);
                    const errorMessage = document.getElementById('error-message-' + i);

                    // 商品が選択されているかチェック
                    if (itemSelect && itemSelect.value !== '') {
                        // 数量が0または未入力の場合
                        const quantity = parseInt(quantityInput.value, 10);
                        if (isNaN(quantity) || quantity <= 0) {
                            errorMessage.textContent = '数量は1以上で入力してください。';
                            if (!firstErrorElement) {
                                firstErrorElement = quantityInput;
                            }
                            errorExists = true;
                        }

                        // back_priceが設定されている商品のキャスト選択をチェック
                        const selectedOption = itemSelect.options[itemSelect.selectedIndex];
                        const backPrice = selectedOption.getAttribute('data-back-price');
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
        });
    </script>
</body>
</html>
<?php
// 処理の最後にデータベース接続を閉じる
disconnect($pdo);
?>
