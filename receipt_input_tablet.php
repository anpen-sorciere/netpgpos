<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

session_cache_limiter('none');
session_start();

require_once(__DIR__ . '/../common/config.php');
require_once(__DIR__ . '/../common/dbconnect.php');
require_once(__DIR__ . '/../common/functions.php');

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

// セッションリセット
if ($_SERVER['REQUEST_METHOD'] === 'GET' && !isset($_GET['is_back']) && isset($_SESSION['join'])) {
    unset($_SESSION['join']);
}

try {
    $pdo = connect();
} catch (PDOException $e) {
    echo "データベース接続に失敗しました: " . $e->getMessage();
    exit();
}

// バリデーション & 送信処理
if(!empty($_POST) && !isset($_POST['is_back'])){
    $_SESSION['join'] = $_POST;
    $errors = [];

    if(empty($_POST['receipt_day'])) $errors['receipt_day'] = '日付未入力';
    if(empty($_POST['in_date'])) $errors['in_date'] = '入店日未入力';
    if(empty($_POST['p_type'])) $errors['p_type'] = '支払方法未選択';

    // 商品チェック
    $has_item = false;
    for($i=1; $i<=50; $i++){
        if(!empty($_POST['item_name'.$i])) {
            $has_item = true;
            break;
        }
    }
    if(!$has_item) $errors['no_item'] = '商品が選択されていません';

    if(empty($errors)){
        header('Location: receipt_check.php');
        exit();
    }
}

// データ取得
$casts = cast_get_all($pdo);
$items = item_get_all($pdo);
$payments = payment_get_all($pdo);
$shop_info = get_shop_info($utype);

// カテゴリ分類
$items_by_category = [];
foreach($items as $item) {
    $cat = $item['category'] ?? 'other';
    $items_by_category[$cat][] = $item;
}

$category_names = [
    1 => '通常', 2 => 'シャンパン', 3 => 'フード', 
    4 => '基本料金', 5 => 'イベント', 6 => 'グッズ', 7 => '遠隔'
];
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Tablet Order Entry</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --bg-color: #1a1a1a;
            --panel-bg: #2d2d2d;
            --text-color: #e0e0e0;
            --accent-color: #3498db;
            --confirm-color: #2ecc71;
            --danger-color: #e74c3c;
            --border-color: #444;
        }
        * { box-sizing: border-box; touch-action: manipulation; }
        body {
            margin: 0; padding: 0;
            font-family: 'Segoe UI', sans-serif;
            background-color: var(--bg-color);
            color: var(--text-color);
            height: 100vh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }
        
        /* Layout */
        .main-container {
            display: flex;
            flex: 1;
            overflow: hidden;
        }
        .left-panel {
            flex: 2;
            display: flex;
            flex-direction: column;
            border-right: 1px solid var(--border-color);
        }
        .right-panel {
            flex: 1;
            display: flex;
            flex-direction: column;
            background-color: var(--panel-bg);
            min-width: 350px;
        }

        /* Header */
        header {
            background-color: #000;
            padding: 10px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            height: 60px;
        }
        .shop-name { font-weight: bold; font-size: 1.2rem; margin-right: 20px; }
        .header-controls { display: flex; gap: 15px; align-items: center; }
        .header-input {
            background: #333; border: 1px solid #555; color: white;
            padding: 5px 10px; border-radius: 4px; font-size: 1rem;
        }

        /* Category Tabs */
        .category-tabs {
            display: flex;
            overflow-x: auto;
            background: #222;
            padding: 10px;
            gap: 10px;
            border-bottom: 1px solid var(--border-color);
        }
        .cat-btn {
            background: #444; color: white; border: none;
            padding: 10px 20px; border-radius: 20px;
            white-space: nowrap; cursor: pointer;
            font-size: 1rem; transition: 0.2s;
        }
        .cat-btn.active { background: var(--accent-color); font-weight: bold; }

        /* Item Grid */
        .item-grid {
            flex: 1;
            overflow-y: auto;
            padding: 15px;
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
            gap: 15px;
            align-content: start;
        }
        .item-card {
            background: #3a3a3a;
            border-radius: 8px;
            padding: 15px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            height: 120px;
            cursor: pointer;
            transition: 0.1s;
            position: relative;
            user-select: none;
        }
        .item-card:active { transform: scale(0.95); background: #555; }
        .item-name { font-weight: bold; font-size: 1rem; line-height: 1.3; overflow: hidden; }
        .item-price { color: var(--accent-color); font-size: 1.1rem; font-weight: bold; text-align: right; }
        
        /* Cart Area */
        .cart-header {
            padding: 15px;
            background: #252525;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .cart-list {
            flex: 1;
            overflow-y: auto;
            padding: 10px;
        }
        .cart-item {
            background: #333;
            border-radius: 6px;
            margin-bottom: 10px;
            padding: 10px;
            display: flex;
            flex-direction: column;
            gap: 8px;
            border-left: 4px solid var(--accent-color);
        }
        .cart-row-main {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .cart-item-name { font-weight: bold; font-size: 1rem; }
        .cart-item-price { color: #ccc; font-size: 0.9rem; }
        .cart-controls {
            display: flex;
            gap: 10px;
            align-items: center;
            justify-content: space-between;
            margin-top: 5px;
        }
        .qty-ctrl {
            display: flex;
            align-items: center;
            background: #222;
            border-radius: 20px;
            padding: 2px;
        }
        .qty-btn {
            width: 32px; height: 32px;
            border-radius: 50%;
            border: none;
            background: #555;
            color: white;
            font-size: 1.2rem;
            cursor: pointer;
            display: flex; justify-content: center; align-items: center;
        }
        .qty-val { width: 40px; text-align: center; font-weight: bold; }
        .cast-select-btn {
            background: #444; border: 1px solid #555;
            color: #ddd; padding: 5px 10px;
            border-radius: 4px; cursor: pointer;
            font-size: 0.85rem;
            flex: 1; text-align: center;
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .cast-select-btn.selected { background: #2c3e50; border-color: var(--accent-color); color: var(--accent-color); }

        /* Cart Footer */
        .cart-footer {
            padding: 20px;
            background: #222;
            border-top: 1px solid var(--border-color);
        }
        .total-display {
            display: flex; justify-content: space-between;
            font-size: 1.5rem; font-weight: bold;
            margin-bottom: 15px;
        }
        .checkout-btn {
            width: 100%;
            padding: 15px;
            background: var(--confirm-color);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1.2rem;
            font-weight: bold;
            cursor: pointer;
        }
        .checkout-btn:active { transform: translateY(2px); }

        /* Modal */
        .modal-overlay {
            position: fixed; top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.7);
            display: none; align-items: center; justify-content: center;
            z-index: 1000;
        }
        .modal-content {
            background: #2d2d2d;
            width: 80%; max-width: 600px;
            max-height: 80vh;
            border-radius: 10px;
            display: flex; flex-direction: column;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
        }
        .modal-header {
            padding: 15px; background: #222;
            font-weight: bold; font-size: 1.2rem;
            border-bottom: 1px solid var(--border-color);
            display: flex; justify-content: space-between;
        }
        .modal-body {
            padding: 15px; overflow-y: auto;
            flex: 1;
        }
        .cast-grid {
            display: grid; grid-template-columns: repeat(auto-fill, minmax(100px, 1fr));
            gap: 10px;
        }
        .cast-card {
            background: #444; padding: 15px;
            text-align: center; border-radius: 6px;
            cursor: pointer;
        }
        .cast-card:active { background: var(--accent-color); }
        .cast-card.active { background: var(--accent-color); }
        .close-modal { cursor: pointer; font-size: 1.5rem; }

        /* Responsive */
        @media (max-width: 768px) {
            .main-container { flex-direction: column; }
            .left-panel { flex: 2; overflow-y: hidden; }
            .right-panel { flex: 3; min-width: 100%; border-top: 2px solid #555; }
            .item-grid { overflow-y: auto; height: 300px; }
            .cart-list { max-height: 250px; }
        }
    </style>
</head>
<body>

<!-- Hidden Form for Submission -->
<form id="receiptForm" method="POST" style="display:none;">
    <input type="hidden" name="utype" value="<?= htmlspecialchars($utype) ?>">
    <input type="hidden" name="shop_mst" value="<?= htmlspecialchars($shop_info['id']) ?>">
    <input type="hidden" name="receipt_day" id="input_receipt_day">
    <input type="hidden" name="in_date" id="input_in_date">
    <input type="hidden" name="in_time" id="input_in_time">
    <input type="hidden" name="p_type" id="input_p_type">
    <input type="hidden" name="customer_name" id="input_customer_name">
    <input type="hidden" name="issuer_id" id="input_issuer_id">
    <input type="hidden" name="sheet_no" id="input_sheet_no">
    <!-- Items will be appended here via JS -->
</form>

<header>
    <div style="display:flex; align-items:center;">
        <span class="shop-name"><?= htmlspecialchars($shop_info['name']) ?></span>
        <button onclick="location.href='index.php'" style="background:#555; color:white; border:none; padding:5px 10px; border-radius:4px;">EXIT</button>
    </div>
    <div class="header-controls">
        <input type="text" class="header-input" id="disp_customer_name" placeholder="顧客名" style="width:100px;">
        <select class="header-input" id="disp_p_type">
            <option value="">支払方法</option>
            <?php foreach($payments as $p): ?>
                <option value="<?= $p['payment_type'] ?>"><?= $p['payment_name'] ?></option>
            <?php endforeach; ?>
        </select>
        <select class="header-input" id="disp_issuer_id">
            <option value="">担当</option>
            <?php foreach($casts as $c): ?>
                <option value="<?= $c['cast_id'] ?>"><?= $c['cast_name'] ?></option>
            <?php endforeach; ?>
        </select>
    </div>
</header>

<div class="main-container">
    <!-- Menu Section -->
    <div class="left-panel">
        <div class="category-tabs">
            <button class="cat-btn active" onclick="filterCategory(this, 'all')">ALL</button>
            <?php foreach($category_names as $cid => $cname): ?>
                <button class="cat-btn" onclick="filterCategory(this, '<?= $cid ?>')"><?= $cname ?></button>
            <?php endforeach; ?>
        </div>
        <div class="item-grid" id="itemGrid">
            <?php foreach($items as $item): ?>
                <div class="item-card" data-cat="<?= $item['category'] ?>" 
                     onclick="addToCart(<?= $item['item_id'] ?>, '<?= htmlspecialchars($item['item_name'], ENT_QUOTES) ?>', <?= $item['price'] ?>, <?= $item['back_price'] ?>)">
                    <div class="item-name"><?= $item['item_name'] ?></div>
                    <div class="item-price">¥<?= number_format($item['price']) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Cart Section -->
    <div class="right-panel">
        <div class="cart-header">
            <div>
                <label style="color:#aaa; font-size:0.8rem;">DATE</label><br>
                <input type="date" id="disp_receipt_day" value="<?= date('Y-m-d') ?>" class="header-input" style="width:130px; background:#222;">
            </div>
            <div>
                <label style="color:#aaa; font-size:0.8rem;">IN TIME</label><br>
                <input type="time" id="disp_in_time" value="<?= date('H:i') ?>" class="header-input" style="width:100px; background:#222;">
            </div>
        </div>
        <div class="cart-list" id="cartList">
            <!-- Items go here -->
            <div style="text-align:center; color:#666; margin-top:50px;">
                <i class="fas fa-shopping-cart" style="font-size:2rem;"></i><br>
                No items
            </div>
        </div>
        <div class="cart-footer">
            <div class="total-display">
                <span>Total</span>
                <span id="totalAmount">¥0</span>
            </div>
            <button class="checkout-btn" onclick="submitReceipt()">CHECK OUT</button>
        </div>
    </div>
</div>

<!-- Cast Selection Modal -->
<div class="modal-overlay" id="castModal">
    <div class="modal-content">
        <div class="modal-header">
            <span>Select Cast for <span id="modalItemName" style="color:var(--accent-color)"></span></span>
            <span class="close-modal" onclick="closeModal()">&times;</span>
        </div>
        <div class="modal-body">
            <div class="cast-grid">
                <div class="cast-card" onclick="selectCast(0)">未指定</div>
                <?php foreach($casts as $c): ?>
                    <div class="cast-card" onclick="selectCast(<?= $c['cast_id'] ?>, '<?= $c['cast_name'] ?>')">
                        <?= $c['cast_name'] ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<script>
    let cart = [];
    let currentEditIndex = -1;

    // --- Cart Logic ---
    function addToCart(id, name, price, backPrice) {
        cart.push({
            id: id,
            name: name,
            price: price,
            qty: 1,
            backPrice: backPrice,
            castId: 0,
            castName: ''
        });
        
        // If items needs cast back, auto-open modal for the new item
        if(backPrice > 0) {
            openCastModal(cart.length - 1);
        } else {
            renderCart();
        }
    }

    function renderCart() {
        const container = document.getElementById('cartList');
        if(cart.length === 0) {
            container.innerHTML = '<div style="text-align:center; color:#666; margin-top:50px;"><i class="fas fa-shopping-cart" style="font-size:2rem;"></i><br>No items</div>';
            document.getElementById('totalAmount').innerText = '¥0';
            return;
        }

        let html = '';
        let total = 0;
        
        cart.forEach((item, index) => {
            total += item.price * item.qty;
            const castLabel = item.castName ? item.castName : (item.backPrice > 0 ? '<span style="color:#e74c3c">キャスト必須</span>' : 'キャスト指定なし');
            
            html += `
            <div class="cart-item">
                <div class="cart-row-main">
                    <div class="cart-item-name">${item.name}</div>
                    <div class="cart-item-price">¥${item.price.toLocaleString()}</div>
                </div>
                <div class="cart-controls">
                    <div class="qty-ctrl">
                        <button class="qty-btn" onclick="updateQty(${index}, -1)">-</button>
                        <div class="qty-val">${item.qty}</div>
                        <button class="qty-btn" onclick="updateQty(${index}, 1)">+</button>
                    </div>
                    <div class="cast-select-btn ${item.castId > 0 ? 'selected' : ''}" onclick="openCastModal(${index})">
                        <i class="fas fa-user"></i> ${castLabel}
                    </div>
                    <button class="qty-btn" style="background:#e74c3c; width:30px; height:30px; font-size:1rem;" onclick="removeItem(${index})"><i class="fas fa-trash"></i></button>
                </div>
            </div>`;
        });
        
        container.innerHTML = html;
        document.getElementById('totalAmount').innerText = '¥' + total.toLocaleString();
        
        // Auto scroll to bottom
        container.scrollTop = container.scrollHeight;
    }

    function updateQty(index, change) {
        cart[index].qty += change;
        if(cart[index].qty <= 0) {
            removeItem(index);
        } else {
            renderCart();
        }
    }

    function removeItem(index) {
        cart.splice(index, 1);
        renderCart();
    }

    // --- Modal Logic ---
    function openCastModal(index) {
        currentEditIndex = index;
        document.getElementById('modalItemName').innerText = cart[index].name;
        document.getElementById('castModal').style.display = 'flex';
    }

    function closeModal() {
        document.getElementById('castModal').style.display = 'none';
        renderCart(); // Re-render to show updates or close
    }

    function selectCast(id, name) {
        if(currentEditIndex >= 0 && currentEditIndex < cart.length) {
            cart[currentEditIndex].castId = id;
            cart[currentEditIndex].castName = name || '';
        }
        closeModal();
    }

    // --- Filter Logic ---
    function filterCategory(btn, cat) {
        document.querySelectorAll('.cat-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        
        const cards = document.querySelectorAll('.item-card');
        cards.forEach(card => {
            if(cat === 'all' || card.dataset.cat == cat) {
                card.style.display = 'flex';
            } else {
                card.style.display = 'none';
            }
        });
    }

    // --- Submission Logic ---
    function submitReceipt() {
        if(cart.length === 0) {
            alert('商品が選択されていません');
            return;
        }
        
        // Validations
        const p_type = document.getElementById('disp_p_type').value;
        const receipt_day = document.getElementById('disp_receipt_day').value;
        const in_time = document.getElementById('disp_in_time').value;

        if(!p_type) { alert('支払方法を選択してください'); return; }
        if(!receipt_day) { alert('日付を選択してください'); return; }
        if(!in_time) { alert('入店時間を入力してください'); return; }

        // check cast requirement
        for(let i=0; i<cart.length; i++) {
            if(cart[i].backPrice > 0 && cart[i].castId == 0) {
                alert(`「${cart[i].name}」にはキャスト指定が必須です`);
                return;
            }
        }

        const form = document.getElementById('receiptForm');
        document.getElementById('input_receipt_day').value = receipt_day;
        document.getElementById('input_in_date').value = receipt_day; // Assuming same day
        document.getElementById('input_in_time').value = in_time;
        document.getElementById('input_p_type').value = p_type;
        document.getElementById('input_customer_name').value = document.getElementById('disp_customer_name').value;
        document.getElementById('input_issuer_id').value = document.getElementById('disp_issuer_id').value;

        // Clean previous hidden items if any
        form.querySelectorAll('.dynamic-item').forEach(e => e.remove());

        // Append Items
        cart.forEach((item, idx) => {
            const index = idx + 1; // 1-based index
            if(index > 50) return; // Limit check

            addHidden(form, `item_name${index}`, item.id);
            addHidden(form, `suu${index}`, item.qty);
            addHidden(form, `price${index}`, item.price);
            if(item.castId > 0) {
                addHidden(form, `cast_name${index}`, item.castId);
            }
        });

        form.submit();
    }

    function addHidden(form, name, value) {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = name;
        input.value = value;
        input.className = 'dynamic-item';
        form.appendChild(input);
    }
</script>

</body>
</html>
