<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_cache_limiter('none');
session_start();

require("../common/dbconnect.php");
require("../common/functions.php");

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

// セッションリセットのロジック
if ($_SERVER['REQUEST_METHOD'] === 'GET' && !isset($_GET['is_back']) && isset($_SESSION['join'])) {
    unset($_SESSION['join']);
}

try {
    $pdo = connect();
} catch (PDOException $e) {
    echo "データベース接続に失敗しました: " . $e->getMessage();
    exit();
}

// バリデーション
if(!empty($_POST) && !isset($_POST['is_back'])){
    $_SESSION['join'] = $_POST;
    $errors = [];

    // バリデーションチェック
    if(empty($_POST['receipt_day'])) {
        $errors['receipt_day'] = '伝票集計日付を入力してください';
    }
    if(empty($_POST['in_date'])) {
        $errors['in_date'] = '入店日付を入力してください';
    }
    if(empty($_POST['in_time'])) {
        $errors['in_time'] = '入店時間を入力してください';
    }
    if(empty($_POST['p_type'])) {
        $errors['p_type'] = '支払い方法を選択してください';
    }

    if(empty($errors)){
        header('Location: receipt_check.php');
        exit();
    }
}

// マスターデータの取得
$casts = cast_get_all($pdo);
$items = item_get_all($pdo);
$payments = payment_get_all($pdo);
$shop_info = get_shop_info($utype);

// カテゴリー別に商品を分類
$items_by_category = [];
foreach($items as $item) {
    $category = $item['category'] ?? 'other';
    if(!isset($items_by_category[$category])) {
        $items_by_category[$category] = [];
    }
    $items_by_category[$category][] = $item;
}

// カテゴリー名の定義
$category_names = [
    1 => '通常',
    2 => 'シャンパン', 
    3 => 'フード',
    4 => '基本料金',
    5 => 'イベント',
    6 => 'グッズ',
    7 => '遠隔'
];

?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>スマート伝票入力</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 10px;
            min-height: 100vh;
        }
        
        .container {
            max-width: 100%;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
            padding: 15px;
        }
        
        .header {
            text-align: center;
            padding: 15px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px 10px 0 0;
            margin: -15px -15px 15px -15px;
        }
        
        .header h1 {
            font-size: 1.5rem;
            margin-bottom: 5px;
        }
        
        .form-section {
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        .form-group label {
            display: block;
            font-weight: bold;
            margin-bottom: 5px;
            color: #333;
            font-size: 0.9rem;
        }
        
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .required {
            color: #ff4757;
        }
        
        .item-section {
            margin-top: 20px;
        }
        
        .category-tabs {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 15px;
        }
        
        .category-tab {
            padding: 8px 16px;
            background: #f0f0f0;
            border: none;
            border-radius: 20px;
            cursor: pointer;
            font-size: 0.9rem;
            transition: all 0.3s;
        }
        
        .category-tab.active {
            background: #667eea;
            color: white;
        }
        
        .items-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .item-card {
            background: #f8f9fa;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            padding: 10px;
            cursor: pointer;
            transition: all 0.3s;
            text-align: center;
            font-size: 0.85rem;
        }
        
        .item-card:hover {
            background: #667eea;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .selected-items {
            margin-top: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
        }
        
        .selected-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px;
            background: white;
            border-radius: 8px;
            margin-bottom: 10px;
        }
        
        .selected-item-info {
            flex: 1;
        }
        
        .selected-item-name {
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .selected-item-price {
            color: #667eea;
            font-size: 0.9rem;
        }
        
        .quantity-control {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .quantity-control button {
            width: 35px;
            height: 35px;
            border: none;
            border-radius: 50%;
            background: #667eea;
            color: white;
            font-size: 1.2rem;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .quantity-control button:active {
            transform: scale(0.9);
        }
        
        .quantity-control input {
            width: 50px;
            text-align: center;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            padding: 8px;
        }
        
        .remove-btn {
            background: #ff4757;
            color: white;
            border: none;
            border-radius: 8px;
            padding: 5px 10px;
            cursor: pointer;
            font-size: 0.8rem;
        }
        
        .summary {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background: white;
            padding: 15px;
            box-shadow: 0 -5px 20px rgba(0,0,0,0.1);
            border-radius: 15px 15px 0 0;
        }
        
        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            font-size: 1rem;
        }
        
        .summary-total {
            font-size: 1.3rem;
            font-weight: bold;
            color: #667eea;
            border-top: 2px solid #e0e0e0;
            padding-top: 10px;
        }
        
        .btn-submit {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 1.1rem;
            font-weight: bold;
            cursor: pointer;
            margin-top: 10px;
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .btn-submit:active {
            transform: scale(0.98);
        }
        
        .hidden {
            display: none;
        }
        
        @media (max-width: 480px) {
            .items-grid {
                grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📝 簡単伝票入力</h1>
            <p><?= htmlspecialchars($shop_info['name'] ?? '') ?></p>
        </div>

        <form id="receiptForm" method="POST">
            <input type="hidden" name="utype" value="<?= htmlspecialchars($utype) ?>">
            <input type="hidden" name="shop_mst" value="<?= htmlspecialchars($shop_info['id'] ?? 1) ?>">
            
            <div class="form-section">
                <div class="form-group">
                    <label>伝票日付 <span class="required">*</span></label>
                    <input type="date" name="receipt_day" value="<?= date('Y-m-d') ?>" required>
                </div>
                
                <div class="form-group">
                    <label>入店日時 <span class="required">*</span></label>
                    <div style="display: flex; gap: 10px;">
                        <input type="date" name="in_date" value="<?= date('Y-m-d') ?>" style="flex: 1;" required>
                        <input type="time" name="in_time" value="<?= date('H:i') ?>" style="flex: 1;" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>顧客名</label>
                    <input type="text" name="customer_name" placeholder="お名前">
                </div>
                
                <div class="form-group">
                    <label>担当 <span class="required">*</span></label>
                    <select name="issuer_id" required>
                        <option value="">選択してください</option>
                        <?php foreach($casts as $cast): ?>
                            <option value="<?= $cast['cast_id'] ?>"><?= htmlspecialchars($cast['cast_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>支払い方法 <span class="required">*</span></label>
                    <select name="p_type" required>
                        <option value="">選択してください</option>
                        <?php foreach($payments as $payment): ?>
                            <option value="<?= $payment['payment_type'] ?>"><?= htmlspecialchars($payment['payment_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="item-section">
                <h2>商品を選択</h2>
                
                <div class="category-tabs">
                    <button type="button" class="category-tab active" data-category="all">全部</button>
                    <?php foreach($category_names as $cat_id => $cat_name): ?>
                        <button type="button" class="category-tab" data-category="<?= $cat_id ?>"><?= htmlspecialchars($cat_name) ?></button>
                    <?php endforeach; ?>
                </div>
                
                <div id="itemsContainer" class="items-grid">
                    <?php foreach($items as $item): ?>
                        <div class="item-card" data-item-id="<?= $item['item_id'] ?>" 
                             data-item-name="<?= htmlspecialchars($item['item_name']) ?>"
                             data-item-price="<?= $item['price'] ?>"
                             data-item-category="<?= $item['category'] ?>">
                            <?= htmlspecialchars($item['item_name']) ?><br>
                            <small>¥<?= number_format($item['price']) ?></small>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div id="selectedItems" class="selected-items hidden">
                    <h3>選択した商品</h3>
                    <div id="selectedList"></div>
                </div>
            </div>
            
            <div class="summary">
                <div class="summary-row">
                    <span>小計:</span>
                    <span id="subtotal">¥0</span>
                </div>
                <div class="summary-row summary-total">
                    <span>合計:</span>
                    <span id="total">¥0</span>
                </div>
                <button type="submit" class="btn-submit">📤 登録する</button>
            </div>
        </form>
    </div>

    <script>
        let selectedItems = [];
        let currentCategory = 'all';
        
        // カテゴリータブの切り替え
        document.querySelectorAll('.category-tab').forEach(tab => {
            tab.addEventListener('click', function() {
                document.querySelectorAll('.category-tab').forEach(t => t.classList.remove('active'));
                this.classList.add('active');
                currentCategory = this.dataset.category;
                filterItems();
            });
        });
        
        function filterItems() {
            document.querySelectorAll('.item-card').forEach(card => {
                if (currentCategory === 'all' || card.dataset.itemCategory === currentCategory) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        }
        
        // 商品カードのクリック
        document.querySelectorAll('.item-card').forEach(card => {
            card.addEventListener('click', function() {
                const itemId = this.dataset.itemId;
                const itemName = this.dataset.itemName;
                const itemPrice = parseInt(this.dataset.itemPrice);
                
                // 既に選択されているかチェック
                const existingIndex = selectedItems.findIndex(item => item.id === itemId);
                
                if (existingIndex >= 0) {
                    // 数量を増やす
                    selectedItems[existingIndex].quantity++;
                } else {
                    // 新しく追加
                    selectedItems.push({
                        id: itemId,
                        name: itemName,
                        price: itemPrice,
                        quantity: 1
                    });
                }
                
                updateSelectedItems();
            });
        });
        
        function updateSelectedItems() {
            const container = document.getElementById('selectedItems');
            const list = document.getElementById('selectedList');
            
            if (selectedItems.length === 0) {
                container.classList.add('hidden');
                return;
            }
            
            container.classList.remove('hidden');
            list.innerHTML = selectedItems.map((item, index) => `
                <div class="selected-item">
                    <div class="selected-item-info">
                        <div class="selected-item-name">${item.name}</div>
                        <div class="selected-item-price">¥${item.price.toLocaleString()} × ${item.quantity}</div>
                    </div>
                    <div class="quantity-control">
                        <button type="button" onclick="changeQuantity(${index}, -1)">−</button>
                        <input type="number" value="${item.quantity}" min="1" 
                               onchange="changeQuantity(${index}, this.value - ${item.quantity})" 
                               style="width: 50px; padding: 5px;">
                        <button type="button" onclick="changeQuantity(${index}, 1)">+</button>
                    </div>
                    <button type="button" class="remove-btn" onclick="removeItem(${index})">削除</button>
                </div>
            `).join('');
            
            updateSummary();
        }
        
        function changeQuantity(index, delta) {
            selectedItems[index].quantity = Math.max(1, selectedItems[index].quantity + delta);
            updateSelectedItems();
        }
        
        function removeItem(index) {
            selectedItems.splice(index, 1);
            updateSelectedItems();
        }
        
        function updateSummary() {
            let subtotal = 0;
            selectedItems.forEach(item => {
                subtotal += item.price * item.quantity;
            });
            
            document.getElementById('subtotal').textContent = '¥' + subtotal.toLocaleString();
            document.getElementById('total').textContent = '¥' + subtotal.toLocaleString();
        }
        
        // フォーム送信時
        document.getElementById('receiptForm').addEventListener('submit', function(e) {
            // 選択された商品を入力フィールドに追加
            let itemIndex = 0;
            selectedItems.forEach(item => {
                for (let i = 0; i < item.quantity; i++) {
                    if (itemIndex < 11) {
                        const input1 = document.createElement('input');
                        input1.type = 'hidden';
                        input1.name = `item_name${itemIndex + 1}`;
                        input1.value = item.id;
                        this.appendChild(input1);
                        
                        const input2 = document.createElement('input');
                        input2.type = 'hidden';
                        input2.name = `suu${itemIndex + 1}`;
                        input2.value = i === 0 ? item.quantity : 0;
                        this.appendChild(input2);
                        
                        const input3 = document.createElement('input');
                        input3.type = 'hidden';
                        input3.name = `price${itemIndex + 1}`;
                        input3.value = item.price;
                        this.appendChild(input3);
                        
                        itemIndex++;
                    }
                }
            });
        });
    </script>
</body>
</html>

<?php
disconnect($pdo);
?>

