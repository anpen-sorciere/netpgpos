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
    $shop_info = get_shop_info($utype);
    $sheets = get_sheet_layout($pdo, $shop_info['id']); // 座席取得
    $payments = payment_get_all($pdo);

    // カテゴリ分類
    $items_by_category = [];
    foreach($items as $item) {
        $cat = $item['category'] ?? 'other';
        $items_by_category[$cat][] = $item;
    }

    $category_names = [
        0 => '基本料金',
        1 => '通常', 2 => 'シャンパン', 3 => 'フード', 
        5 => 'イベント', 6 => 'グッズ', 7 => '遠隔'
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
                --bg-color: #f4f7f6;
                --panel-bg: #ffffff;
                --text-color: #333333;
                --accent-color: #3498db;
                --confirm-color: #2ecc71;
                --danger-color: #e74c3c;
                --warning-color: #f39c12;
                --border-color: #e0e0e0;
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
                flex: 1.5;
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
                border-left: 1px solid var(--border-color); /* Added Border */
            }
    
            /* Header */
            header {
                background-color: #ffffff;
                padding: 10px 20px;
                display: flex;
                justify-content: space-between;
                align-items: center;
                height: 60px;
                border-bottom: 1px solid var(--border-color);
                box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            }
            .shop-name { font-weight: bold; font-size: 1.2rem; margin-right: 20px; color: #333; }
            .header-controls { display: flex; gap: 15px; align-items: center; }
            .header-input {
                background: #f9f9f9; border: 1px solid #ddd; color: #333;
                padding: 5px 10px; border-radius: 4px; font-size: 1rem;
            }
            
            /* Seat Map Modal */
            #seatMapContainer {
                position: relative;
                width: 100%;
                height: 500px;
                background: #f0f0f0;
                border: 2px solid #ccc;
                margin-top: 10px;
                overflow: hidden;
                border-radius: 8px;
            }
            .seat-obj {
                position: absolute;
                background: #fff;
                border: 2px solid #999;
                color: #333;
                display: flex;
                justify-content: center;
                align-items: center;
                cursor: pointer;
                font-weight: bold;
                border-radius: 4px;
                user-select: none;
                transition: transform 0.1s;
                touch-action: none;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            }
            /* Seat Status Colors */
            .seat-obj.occupied { 
                background: #3498db; 
                color: white; 
                border-color: #2980b9; 
                flex-direction: column;
                justify-content: space-around;
                font-size: 0.8rem;
                padding: 2px;
            }
            .seat-obj.vacant { background: #fff; }
            
            /* Seat Details on Map */
            .seat-info-name { font-weight: bold; overflow: hidden; white-space: nowrap; text-overflow: ellipsis; max-width: 100%; }
            .seat-info-time { font-size: 0.75rem; background: rgba(0,0,0,0.2); padding: 2px 4px; border-radius: 4px; }

            /* Edit Mode Styles */
            .seat-obj.editable { cursor: move; border-style: dashed; border-color: var(--warning-color); background: #fffbe6; }
            .edit-controls {
                display: flex; gap: 10px; margin-bottom: 10px;
                padding: 10px; background: #f9f9f9; border-radius: 4px; border: 1px solid #ddd;
            }
    
            /* Category Tabs */
            .category-tabs {
                display: flex;
                flex-wrap: wrap; /* Changed from overflow-x: auto */
                background: #222;
                padding: 10px;
                gap: 5px; /* Reduced gap */
                border-bottom: 1px solid var(--border-color);
            }
            .cat-btn {
                background: #444; color: white; border: none;
                padding: 8px 15px; /* Slightly smaller padding */
                border-radius: 20px;
                white-space: nowrap; cursor: pointer;
                font-size: 0.95rem; transition: 0.2s;
                flex: 0 0 auto; /* Allow wrap */
            }
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
                grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
                gap: 15px;
                align-content: start;
            }
            .item-card {
                background: #ffffff;
                border: 1px solid var(--border-color);
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
                box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            }
            .item-card:active { transform: scale(0.95); background: #f0f0f0; }
            .item-name { font-weight: bold; font-size: 1rem; line-height: 1.3; overflow: hidden; }
            .item-price { color: var(--accent-color); font-size: 1.1rem; font-weight: bold; text-align: right; }
            
            /* Cart Area */
            .cart-header {
                padding: 15px;
                background: #f8f9fa;
                border-bottom: 1px solid var(--border-color);
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            .cart-list {
                flex: 1;
                overflow-y: auto;
                padding: 10px;
                background: #fff;
            }
            .cart-item {
                background: #f9f9f9;
                border: 1px solid #eee;
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
            .cart-item-name { font-weight: bold; font-size: 1rem; color: #333; }
            .cart-item-price { color: #666; font-size: 0.9rem; }
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
                background: #eee;
                border-radius: 20px;
                padding: 2px;
                border: 1px solid #ddd;
            }
            .qty-btn {
                width: 32px; height: 32px;
                border-radius: 50%;
                border: none;
                background: #fff;
                color: #333;
                font-size: 1.2rem;
                cursor: pointer;
                display: flex; justify-content: center; align-items: center;
                box-shadow: 0 1px 2px rgba(0,0,0,0.1);
            }
            .qty-val { width: 40px; text-align: center; font-weight: bold; color: #333; }
            .cast-select-btn {
                background: #fff; border: 1px solid #ccc;
                color: #555; padding: 5px 10px;
                border-radius: 4px; cursor: pointer;
                font-size: 0.85rem;
                flex: 1; text-align: center;
                white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
            }
            .cast-select-btn.selected { background: #e8f4fc; border-color: var(--accent-color); color: var(--accent-color); }
    
            /* Cart Footer */
            .cart-footer {
                padding: 20px;
                background: #f8f9fa;
                border-top: 1px solid var(--border-color);
            }
            .total-display {
                display: flex; justify-content: space-between; align-items: center;
                margin-bottom: 15px; color: #333;
            }
            .total-label { font-size: 1.2rem; font-weight: bold; color: #555; }
            .total-price { font-size: 1.5rem; font-weight: bold; color: var(--accent-color); }
            .checkout-btn {
                width: 100%; border: none; padding: 15px;
                background: var(--confirm-color);
                color: white;
                font-size: 1.2rem; font-weight: bold; border-radius: 8px;
                cursor: pointer; transition: 0.2s;
                box-shadow: 0 4px 6px rgba(46, 204, 113, 0.3);
            }
            .checkout-btn:active { transform: translateY(2px); box-shadow: none; }
            
            /* Input Visibility Fixes */
            input[type="date"], input[type="time"], select.form-control {
                background-color: #fff;
                color: #333;
                border: 1px solid #ccc;
                padding: 5px;
                border-radius: 4px;
            }

            /* Modal */
            .modal-overlay {
                position: fixed; top: 0; left: 0; right: 0; bottom: 0;
                background: rgba(0,0,0,0.5);
                display: none; align-items: center; justify-content: center;
                z-index: 1000;
                backdrop-filter: blur(2px);
            }
            .modal-content {
                background: #ffffff;
                width: 90%; max-width: 800px;
                max-height: 90vh;
                border-radius: 10px;
                display: flex; flex-direction: column;
                overflow: hidden;
                box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            }
            .modal-header {
                padding: 15px; background: #f8f9fa;
                font-weight: bold; font-size: 1.2rem;
                border-bottom: 1px solid var(--border-color);
                display: flex; justify-content: space-between;
                align-items: center;
                color: #333;
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
                background: #f9f9f9; padding: 15px;
                text-align: center; border-radius: 6px;
                cursor: pointer;
                border: 1px solid #eee;
                color: #333;
            }
            .cast-card:active { background: var(--accent-color); color: white; }
            .cast-card.active { background: var(--accent-color); color: white; }
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
    
    <!-- Hidden Form for Submission (Legacy/Direct checkout) - Keeping for now but might change -->
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
    </form>
    
    <header>
        <div style="display:flex; align-items:center;">
            <span class="shop-name"><?= htmlspecialchars($shop_info['name']) ?></span>
            <button onclick="location.href='index.php'" style="background:#555; color:white; border:none; padding:5px 10px; border-radius:4px;">EXIT</button>
        </div>
        <div class="header-controls">
            <!-- Modified Header: Just Status -->
            <button id="btnSelectSheet" onclick="openSheetModal()" style="background:#444; color:white; border:1px solid #666; padding:5px 15px; border-radius:4px; cursor:pointer;">
                <i class="fas fa-couch"></i> 座席マップ
            </button>
            <!-- 
            <input type="text" class="header-input" id="disp_customer_name" placeholder="顧客名" style="width:100px;">
            <select class="header-input" id="disp_issuer_id">
             ...
            </select> 
            -->
            <div id="workingSessionInfo" style="color:var(--accent-color); font-weight:bold; display:none;">
                <span id="workingSessionName"></span> 様 (注文入力中)
                <button onclick="cancelOrderMode()" style="background:#aaa; font-size:0.8rem; padding:2px 5px; margin-left:5px;">解除</button>
            </div>
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
            <div class="cart-header" style="flex-direction:column; align-items:flex-start;">
                <div style="font-size:1.1rem; font-weight:bold; margin-bottom:5px;">
                    <i class="fas fa-receipt"></i> 注文リスト
                </div>
                <div id="cartStatus" style="font-size:0.9rem; color:#666;">
                    座席を選択して注文を開始してください
                </div>
            </div>
            
            <div class="cart-list" id="cartList">
                <div style="text-align:center; color:#666; margin-top:50px;">
                    <i class="fas fa-shopping-cart" style="font-size:2rem;"></i><br>
                    No items
                </div>
            </div>
            <div class="cart-footer">
                <div class="total-display">
                    <span>Total (Current Order)</span>
                    <span id="totalAmount">¥0</span>
                </div>
                <!-- Action Button changes context -->
                <button id="mainActionButton" class="checkout-btn" onclick="submitOrder()" disabled style="background:#ccc;">座席未選択</button>
            </div>
        </div>
    </div>
    
    <!-- Cast Selection Modal (Existing) -->
    <div class="modal-overlay" id="castModal">
        <div class="modal-content">
            <div class="modal-header">
                <span>Select Cast for <span id="modalItemName" style="color:var(--accent-color)"></span></span>
                <span class="close-modal" onclick="closeModal('castModal')">&times;</span>
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
    
    <!-- Check-in Modal [NEW] -->
    <div class="modal-overlay" id="checkinModal">
        <div class="modal-content" style="max-width:400px;">
            <div class="modal-header">
                <span><i class="fas fa-sign-in-alt"></i> ご入店 (Check-in)</span>
                <span class="close-modal" onclick="closeModal('checkinModal')">&times;</span>
            </div>
            <div class="modal-body" style="padding:20px;">
                <h3 id="checkinSeatName" style="margin-top:0;">Seat Name</h3>
                
                <label style="display:block; margin-bottom:5px;">お客様名 (Customer Name)</label>
                <input type="text" id="checkinName" class="header-input" style="width:100%; margin-bottom:15px; padding:10px;" placeholder="お客様名">
                
                <label style="display:block; margin-bottom:5px;">人数 (People)</label>
                <select id="checkinPeople" class="header-input" style="width:100%; margin-bottom:15px; padding:10px;">
                    <option value="1">1名</option>
                    <option value="2">2名</option>
                    <option value="3">3名</option>
                    <option value="4">4名</option>
                    <option value="5">5名以上</option>
                </select>

                <div style="margin-bottom:20px; display:flex; align-items:center;">
                    <input type="checkbox" id="checkinIsNew" style="width:20px; height:20px;">
                    <label for="checkinIsNew" style="font-size:1.1rem; margin-left:10px;">新規 (New Customer)</label>
                </div>

                <label style="display:block; margin-bottom:5px;">入店時間 (Check-in Time)</label>
                <div style="display:flex; gap:10px; margin-bottom:15px;">
                    <input type="date" id="checkinDate" class="header-input" style="flex:1;">
                    <input type="time" id="checkinTime" class="header-input" style="flex:1;">
                </div>
                
                <button onclick="executeCheckin()" style="width:100%; padding:15px; background:var(--accent-color); color:white; border:none; border-radius:6px; font-weight:bold; font-size:1.1rem;">
                    チェックイン開始
                </button>
            </div>
        </div>
    </div>

    <!-- Session Menu Modal [NEW] -->
    <div class="modal-overlay" id="sessionModal">
        <div class="modal-content" style="max-width:500px;">
            <div class="modal-header">
                <span id="sessionModalTitle">Seat Operation</span>
                <span class="close-modal" onclick="closeModal('sessionModal')">&times;</span>
            </div>
            <div class="modal-body" style="padding:20px; display:flex; flex-direction:column; gap:15px;">
                <div style="background:#f0f8ff; padding:15px; border-radius:6px; margin-bottom:10px;">
                    <div id="sessionInfoName" style="font-weight:bold; font-size:1.2rem;">Guest Name</div>
                    <div id="sessionInfoTime" style="color:#666;">Started: 19:00 (45min)</div>
                    
                    <!-- Current Order List -->
                    <div style="margin-top:10px; border-top:1px solid #ccc; padding-top:10px;">
                        <div style="font-size:0.9rem; color:#555; margin-bottom:5px;">Check-in Orders:</div>
                        <div id="sessionCurrentOrders" style="max-height:150px; overflow-y:auto; font-size:0.9rem; margin-bottom:5px;">
                            <!-- Populated via JS -->
                            <div style="color:#999; text-align:center;">Loading...</div>
                        </div>
                    </div>
                    
                    
                    <div id="sessionTotal" style="color:var(--accent-color); font-weight:bold; font-size:1.2rem; text-align:right; border-top:2px solid #ddd; padding-top:5px;"></div>
                </div>
                
                <button onclick="startOrderForSession()" style="padding:20px; background:var(--confirm-color); color:white; border:none; border-radius:6px; font-size:1.2rem; display:flex; align-items:center; justify-content:center; gap:10px;">
                    <i class="fas fa-beer"></i> 追加注文 (Order)
                </button>
                
                <label style="display:block; margin-bottom:5px;">支払い方法 (Payment)</label>
                <select id="checkoutPayment" class="header-input" style="width:100%; margin-bottom:15px; padding:10px;">
                    <?php foreach($payments as $p): ?>
                        <option value="<?= $p['payment_type'] ?>"><?= $p['payment_name'] ?></option>
                    <?php endforeach; ?>
                </select>

                <label style="display:block; margin-bottom:5px;">調整額 (Adjust Price)</label>
                <input type="number" id="checkoutAdjust" class="header-input" style="width:100%; margin-bottom:15px; padding:10px;" value="0">

                <label style="display:block; margin-bottom:5px;">担当キャスト (Staff)</label>
                <select id="checkoutStaff" class="header-input" style="width:100%; margin-bottom:15px; padding:10px;">
                    <option value="0">未指定</option>
                    <?php foreach($casts as $c): ?>
                        <option value="<?= $c['cast_id'] ?>"><?= $c['cast_name'] ?></option>
                    <?php endforeach; ?>
                </select>

                <label style="display:block; margin-bottom:5px;">伝票起票者 (Issuer)</label>
                <select id="checkoutIssuer" class="header-input" style="width:100%; margin-bottom:15px; padding:10px;">
                    <option value="0">未指定</option>
                    <?php foreach($casts as $c): ?>
                        <option value="<?= $c['cast_id'] ?>"><?= $c['cast_name'] ?></option>
                    <?php endforeach; ?>
                </select>



                <div style="margin-bottom:20px; display:flex; align-items:center;">
                    <input type="checkbox" id="checkoutIsNew" style="width:20px; height:20px;">
                    <label for="checkoutIsNew" style="font-size:1.1rem; margin-left:10px;">新規 (New Customer)</label>
                </div>

                <button onclick="executeCheckout()" style="padding:20px; background:#e67e22; color:white; border:none; border-radius:6px; font-size:1.2rem; display:flex; align-items:center; justify-content:center; gap:10px;">
                    <i class="fas fa-file-invoice-dollar"></i> お会計 (Checkout)
                </button>
                
                <button onclick="showCancelConfirmation()" style="padding:10px; background:#e74c3c; color:white; border:none; border-radius:6px; font-size:1rem; margin-top:20px;">
                    <i class="fas fa-trash-alt"></i> キャンセル (Cancel Session)
                </button>
            </div>
        </div>
    </div>
    
    <!-- Seat Map Modal -->
    <div class="modal-overlay" id="checkoutConfirmModal">
        <div class="modal-content" style="max-width:600px;">
            <div class="modal-header">
                <span>お会計確認 (Checkout Confirmation)</span>
                <span class="close-modal" onclick="closeModal('checkoutConfirmModal')">&times;</span>
            </div>
            <div class="modal-body" style="padding:20px;">
                <div id="checkoutConfirmList" style="max-height:300px; overflow-y:auto; margin-bottom:20px; border:1px solid #ddd;">
                    <!-- Items go here -->
                </div>
                <div style="text-align:right; font-size:1.5rem; font-weight:bold; margin-bottom:20px;">
                    合計: <span id="checkoutConfirmTotal">¥0</span>
                </div>
                <div style="display:flex; gap:10px;">
                    <button onclick="closeModal('checkoutConfirmModal')" style="flex:1; padding:15px; background:#aaa; color:white; border:none; border-radius:6px; font-weight:bold;">キャンセル</button>
                    <button onclick="finalizeCheckout()" style="flex:1; padding:15px; background:#e67e22; color:white; border:none; border-radius:6px; font-weight:bold;">お会計実行</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Order Complete Modal -->
    <div class="modal-overlay" id="orderCompleteModal">
        <div class="modal-content" style="max-width:400px; text-align:center;">
            <div class="modal-body" style="padding:30px;">
                <i class="fas fa-check-circle" style="font-size:4rem; color:#2ecc71; margin-bottom:20px;"></i>
                <h2 style="margin-top:0;">注文完了</h2>
                <p>ご注文を承りました。</p>
                <button onclick="completeOrderFlow()" style="width:100%; padding:15px; margin-top:20px; background:#3498db; color:white; border:none; border-radius:6px; font-weight:bold; font-size:1.2rem;">
                    次の座席を選択 (Next)
                </button>
            </div>
        </div>
    </div>
    
    <!-- Cancel Confirmation Modal [NEW] -->
    <div class="modal-overlay" id="cancelConfirmModal">
        <div class="modal-content" style="max-width:400px; border-top: 5px solid #e74c3c;">
            <div class="modal-header" style="color:#e74c3c;">
                <span><i class="fas fa-exclamation-triangle"></i> キャンセル確認</span>
                <span class="close-modal" onclick="closeModal('cancelConfirmModal')">&times;</span>
            </div>
            <div class="modal-body" style="padding:20px;">
                <p style="font-weight:bold; color:#333; margin-bottom:15px;">
                    本当にこの座席のデータを消去してよろしいですか？
                </p>
                <p style="font-size:0.9rem; color:#666; margin-bottom:20px;">
                    ※データはデータベースから無効化され、復元できなくなります。<br>
                    ※お会計は実行されません。
                </p>
                
                <div style="background:#fff0f0; padding:15px; border-radius:6px; border:1px solid #ffcccc; margin-bottom:20px;">
                    <div style="display:flex; align-items:center;">
                        <input type="checkbox" id="cancelConfirmCheck" onchange="toggleCancelExecuteBtn()" style="width:20px; height:20px; margin-right:10px;">
                        <label for="cancelConfirmCheck" style="font-weight:bold; color:#d63031; cursor:pointer;">データを消去します</label>
                    </div>
                </div>

                <div style="display:flex; gap:10px;">
                    <button onclick="closeModal('cancelConfirmModal')" style="flex:1; padding:12px; background:#aaa; color:white; border:none; border-radius:6px;">戻る</button>
                    <button id="btnExecuteCancel" onclick="executeCancelSession()" disabled style="flex:1; padding:12px; background:#e74c3c; color:white; border:none; border-radius:6px; font-weight:bold; opacity:0.5; cursor:not-allowed;">実行する</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Seat Map Modal -->
    <div class="modal-overlay" id="sheetModal">
        <div class="modal-content" style="max-width:900px;">
            <div class="modal-header">

                <span>Select Seat / Layout <span id="selectedSheetName" style="font-size:0.9rem; color:#666; margin-left:10px;">(未選択)</span></span>
                <div style="display:flex; align-items:center; gap:10px;">
                    <label style="font-size:0.9rem;">
                        <input type="checkbox" id="editModeToggle" onchange="toggleEditMode()"> レイアウト編集
                    </label>
                    <span class="close-modal" id="sheetModalCloseBtn" onclick="closeModal('sheetModal')" style="display:none;">&times;</span>
                </div>
            </div>
            <div class="modal-body">
                <div id="editControls" class="edit-controls" style="display:none; gap:10px; align-items:center; flex-wrap:wrap;">
                    <button onclick="addNewSheet('circle')" style="background:#555; color:white; border:none; padding:5px 10px; border-radius:4px; font-weight:bold; border: 1px solid #aaa;">
                        <i class="fas fa-circle"></i> 座席 (円)
                    </button>
                    <button onclick="addNewSheet('rect_landscape')" style="background:#444; color:#ccc; border:none; padding:5px 10px; border-radius:4px;">
                        <i class="fas fa-square" style="transform: rotate(90deg);"></i> テーブル (横)
                    </button>
                    <button onclick="addNewSheet('rect_portrait')" style="background:#444; color:#ccc; border:none; padding:5px 10px; border-radius:4px;">
                        <i class="fas fa-square"></i> テーブル (縦)
                    </button>
                    <button id="toggleShapeBtn" onclick="toggleSeatShape()" style="background:#777; color:white; border:none; padding:5px 10px; border-radius:4px; display:none;">
                        <i class="fas fa-sync-alt"></i> 形状変更
                    </button>
                    <button id="deleteSheetBtn" onclick="deleteSheet()" style="background:#e74c3c; color:white; border:none; padding:5px 10px; border-radius:4px; display:none;">
                        <i class="fas fa-trash"></i> 削除
                    </button>
                    
                    <span style="border-left:1px solid #ccc; margin:0 5px; height:20px;"></span>
                    
                    <button onclick="resetLayout()" style="background:#c0392b; color:white; border:none; padding:5px 10px; border-radius:4px; margin-left:auto;">
                        <i class="fas fa-bomb"></i> 全削除(リセット)
                    </button>
                    
                    <div style="width:100%; display:flex; gap:5px; margin-top:5px; border-top:1px solid #ddd; padding-top:5px; display:none;" id="renameControls">
                        <input type="text" id="editSheetName" placeholder="座席名" style="border:1px solid #ccc; border-radius:4px; padding:3px; font-size:0.9rem; flex:1;">
                        <button onclick="updateSheetName()" style="background:#2ecc71; color:white; border:none; padding:3px 10px; border-radius:4px;">変更</button>
                    </div>
                    
                    <span style="font-size:0.8rem; color:#aaa; width:100%; margin-top:5px;">※テーブルの上に座席を配置できます</span>
                </div>
                <div id="seatMapContainer">
                    <!-- Seats generated here -->
                </div>
            </div>

        </div>
    </div>
    
    <script>
        let cart = [];
        let currentEditIndex = -1;
        let selectedSheetId = 0;
        let sheets = <?= json_encode($sheets) ?>;
        let isEditMode = false;
        let isNewItemCastSelection = false;
        let isSelectingForNewOrder = false; // Flag for deferred selection
        const shopId = <?= $shop_info['id'] ?>;
        
        // Real-time State
        let activeSessions = {}; // sheet_id -> session object
        let workingSessionId = 0; // If set, we are ordering for this session
    
        // --- Init ---
        // Auto open sheet modal if not selected
        window.addEventListener('load', () => {
             fetchSeatStatus();
             openSheetModal(); // Default to map
             // Polling every 30s
             setInterval(fetchSeatStatus, 30000);
        });

        function fetchSeatStatus() {
        if(!shopId) return Promise.resolve();
        return fetch('api/cast/seat_operation.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ action: 'get_status', shop_id: shopId })
            })
            .then(res => res.json())
            .then(data => {
                if(data.status === 'success') {
                    activeSessions = {};
                    if(data.sessions) {
                        data.sessions.forEach(s => {
                            activeSessions[s.sheet_id] = s;
                        });
                    }
                    if(document.getElementById('sheetModal').style.display === 'flex') {
                        renderSheets();
                    }
                }
            })
            .catch(e => console.error('Status poll error', e));
        }
    
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
                isNewItemCastSelection = true;
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
                        <div class="cast-select-btn ${item.castId > 0 ? 'selected' : ''}" onclick="isNewItemCastSelection=false; openCastModal(${index})">
                            <i class="fas fa-user"></i> ${castLabel}
                        </div>
                        <button class="qty-btn" style="background:#e74c3c; width:30px; height:30px; font-size:1rem;" onclick="removeItem(${index})"><i class="fas fa-trash"></i></button>
                    </div>
                </div>`;
            });
            
            container.innerHTML = html;
            document.getElementById('totalAmount').innerText = '¥' + total.toLocaleString();
            
            container.scrollTop = container.scrollHeight;
            
            // Enable button if items exist even if no session
            if(workingSessionId === 0) {
                const btn = document.getElementById('mainActionButton');
                if(cart.length > 0) {
                    btn.innerText = '座席を選択して注文';
                    btn.disabled = false;
                    btn.style.background = 'var(--confirm-color)'; // Green
                    btn.onclick = handleMainAction;
                } else {
                    btn.innerText = '座席未選択';
                    btn.disabled = true;
                    btn.style.background = '#ccc';
                    btn.onclick = null;
                }
            }
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
    
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
            if(modalId === 'castModal') {
                if(isNewItemCastSelection && currentEditIndex >= 0 && cart[currentEditIndex] && cart[currentEditIndex].castId === 0) {
                    cart.splice(currentEditIndex, 1);
                }
                isNewItemCastSelection = false;
                renderCart();
            }
        }
    
        function selectCast(id, name) {
            if(currentEditIndex >= 0 && currentEditIndex < cart.length) {
                cart[currentEditIndex].castId = id;
                cart[currentEditIndex].castName = name || '';
            }
            isNewItemCastSelection = false;
            closeModal('castModal');
        }
    
        // --- Sheet Map Logic ---
        function toggleSeatShape() {
            if(!selectedSheetId) return;
            const sheet = sheets.find(s => s.sheet_id == selectedSheetId);
            if(!sheet) return;
            
            // Toggle type
            sheet.type = (sheet.type === 'circle') ? 'rect' : 'circle';
            
            // Re-render
            renderSheets();
            
            // Re-select logic visual
            selectSheet(sheet.sheet_id, sheet.sheet_name);
            
            // Auto-save changes
            saveLayout();
            
            // Ensure editable
            if(isEditMode) {
                const el = document.querySelector(`.seat-obj[data-id="${sheet.sheet_id}"]`);
                if(el){
                    el.classList.add('editable');
                    // Re-attach events (renderSheets clears them but handleSeatInteraction handles correct mode)
                }
            }
        }
    
        function selectSheet(id, name) {
            // Update State
            selectedSheetId = id;
            
             // Fixed: Update label if exists
            const label = document.getElementById('selectedSheetName');
            if(label) label.innerText = name ? name : '(未選択)';

            // Visual Update in DOM
            document.querySelectorAll('.seat-obj').forEach(el => {
                if(el.dataset.id == id) el.classList.add('selected');
                else el.classList.remove('selected');
            });
            
            // Edit Mode UI
            if(isEditMode) {
                 document.getElementById('editSheetName').value = name;
                 document.getElementById('toggleShapeBtn').style.display = 'block';
                 document.getElementById('deleteSheetBtn').style.display = 'block';
                 // Show rename controls
                 document.getElementById('renameControls').style.display = 'flex';
            } else {
                // If not in edit mode, usually we don't show these, but let's be safe
                document.getElementById('toggleShapeBtn').style.display = 'none';
                document.getElementById('deleteSheetBtn').style.display = 'none';
                document.getElementById('renameControls').style.display = 'none';
            }
        }
        
        function openSheetModal() {
            try {
                document.getElementById('sheetModal').style.display = 'flex';
                renderSheets();
            } catch(e) {
                console.error(e);
                alert('Modal Error: ' + e.message);
            }
        }
    
        function renderSheets() {
        try {
            const container = document.getElementById('seatMapContainer');
            if(!container) throw new Error('Seat map container not found');
            container.innerHTML = '';
            
            if(!sheets) {
                console.warn('Sheets is undefined, initializing empty array');
                sheets = [];
            }

            sheets.forEach(sheet => {
                try {
                const el = document.createElement('div');
                const session = activeSessions[sheet.sheet_id];
                const isOccupied = !!session;
                
                el.className = `seat-obj ${sheet.type} ${isOccupied ? 'occupied' : 'vacant'}`;
                if(sheet.sheet_id == selectedSheetId) el.classList.add('selected');
                
                el.style.left = sheet.x_pos + '%';
                el.style.top = sheet.y_pos + '%';
                el.style.width = sheet.width + '%';
                el.style.height = sheet.height + '%';
                el.dataset.id = sheet.sheet_id;
                
                // Content
                let content = `<div class="seat-info-name">${sheet.sheet_name}</div>`;
                if(isOccupied && sheet.type !== 'rect') { // Only show details on seats, not tables? Or both?
                    // Calculate elapsed time
                    const start = new Date(session.start_time);
                    const now = new Date();
                    const diffMins = Math.floor((now - start) / 60000);
                    const hours = Math.floor(diffMins / 60);
                    const mins = diffMins % 60;
                    const timeStr = (hours > 0 ? hours + 'h' : '') + mins + 'm';
                    
                    content += `<div class="seat-info-name" style="font-size:0.7rem;">${session.customer_name}</div>`;
                    content += `<div class="seat-info-time">${timeStr}</div>`;
                }
                el.innerHTML = content;
                
                // Event Handling
                el.onpointerdown = (e) => handleSeatInteraction(e, sheet, el);
                
                container.appendChild(el);
                } catch(err) {
                    console.error('Error rendering individual sheet:', sheet, err);
                }
            });
        } catch(e) {
            console.error('Render Error:', e);
            alert('Render Error: ' + e.message);
        }
    }
    
    function handleSeatInteraction(e, sheet, el) {
        if(isEditMode) {
            selectSheet(sheet.sheet_id, sheet.sheet_name); 
            startDrag(e, sheet, el);
            return;
        }
        
        // Normal Mode
        selectSheet(sheet.sheet_id, sheet.sheet_name);
        
        const session = activeSessions[sheet.sheet_id];
        
        // If selecting for new order (Deferred)
        if(isSelectingForNewOrder) {
            if(session) {
                alert('注文データを持っているため、空席のみ選択可能です。\n(既存の座席に追加する場合は一度注文モードを解除してください)');
                return;
            }
            // Vacant - proceed to checkin as usual, but keep cart
            // Fallthrough to regular checkin logic below
        }
        
        document.getElementById('sessionModalTitle').innerText = `座席番号 (${sheet.sheet_name})`;
        
        if(session) {
            // Unpack session info
            document.getElementById('sessionInfoName').innerText = session.customer_name + ' 様 (' + session.people_count + '名)';
            // Calc time
            const start = new Date(session.start_time);
            const now = new Date();
            const diffMins = Math.floor((now - start) / 60000);
            document.getElementById('sessionInfoTime').innerText = `入店時間: ${session.start_time.substring(11,16)} (${diffMins} min)`;
            document.getElementById('sessionTotal').innerText = `Current Orders: ¥${Number(session.current_order_total || 0).toLocaleString()}`;
            
            // Store target session ID
            document.getElementById('sessionModal').dataset.sessionId = session.session_id;
            
            // Pre-fill New Customer checkbox
            document.getElementById('checkoutIsNew').checked = (parseInt(session.is_new_customer) === 1);
            
            document.getElementById('sessionModal').style.display = 'flex';
            
            // FETCH DETAIL
            fetchSessionDetailsForView(session.session_id);
        } else {
            // Vacant -> Check-in
            document.getElementById('checkinSeatName').innerText = sheet.sheet_name;
            document.getElementById('checkinModal').dataset.sheetId = sheet.sheet_id;
            document.getElementById('checkinName').value = '';
            document.getElementById('checkinPeople').value = '1';
            
            // Default to current time
            const now = new Date();
            const yyyy = now.getFullYear();
            const mm = String(now.getMonth() + 1).padStart(2, '0');
            const dd = String(now.getDate()).padStart(2, '0');
            const hh = String(now.getHours()).padStart(2, '0');
            const min = String(now.getMinutes()).padStart(2, '0');
            
            document.getElementById('checkinDate').value = `${yyyy}-${mm}-${dd}`;
            document.getElementById('checkinTime').value = `${hh}:${min}`;
            
            document.getElementById('checkinModal').style.display = 'flex';
        }
    }

    // --- Real-time Actions ---
    function executeCheckin() {
        const sheetId = document.getElementById('checkinModal').dataset.sheetId;
        const name = document.getElementById('checkinName').value;
        const people = document.getElementById('checkinPeople').value;
        const dateVal = document.getElementById('checkinDate').value;
        const timeVal = document.getElementById('checkinTime').value;
        
        // if(!name) { alert('お客様名を入力してください'); return; }
        if(!dateVal || !timeVal) { alert('入店時間を入力してください'); return; }
        
        const startTime = `${dateVal} ${timeVal}:00`;
        const isNew = document.getElementById('checkinIsNew').checked ? 1 : 0;
        
        fetch('api/cast/seat_operation.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                action: 'checkin',
                shop_id: shopId,
                sheet_id: sheetId,
                customer_name: name,
                people_count: people,
                start_time: startTime,
                is_new_customer: isNew
            })
        })
        .then(res => res.json())
        .then(data => {
            if(data.status === 'success') {
                closeModal('checkinModal');
                fetchSeatStatus(); // Refresh map
                
                if(data.session_id) {
                    // Always enter order mode for the newly checked-in session
                    workingSessionId = data.session_id;
                    const sName = document.getElementById('checkinName').value || 'Guest';
                    
                    document.getElementById('workingSessionInfo').style.display = 'inline-block';
                    document.getElementById('workingSessionName').innerText = sName;
                    document.getElementById('cartStatus').innerText = '注文を入力中...';
                    
                    const btn = document.getElementById('mainActionButton');
                    btn.innerText = '注文を確定する';
                    btn.disabled = false; // Enabled to allow clicking (validation inside)
                    btn.style.background = 'var(--confirm-color)';
                    btn.onclick = submitOrder;
                    
                    // If deferred order exists or items in cart, submit immediately
                if(isSelectingForNewOrder || cart.length > 0) {
                    isSelectingForNewOrder = false;
                    // Do not submit automatically.
                    // Just stay in Order Mode with items in cart.
                    // Ideally, we force a re-render of the cart/buttons to ensure they are green.
                    renderCart(); 
                    alert('お席を確定しました。注文内容を確認して「注文を確定する」ボタンを押してください。');
                }
                } else {
                    alert('Check-in success but Session ID missing.');
                }
            } else {
                alert('Check-in Failed: ' + data.message);
            }
        })
        .catch(e => alert('Error: ' + e.message));
    }

    function startOrderForSession() {
        const sessionId = document.getElementById('sessionModal').dataset.sessionId;
        // Find session data to show in header
        let sessionName = 'Guest';
        for(let sid in activeSessions) {
            if(activeSessions[sid].session_id == sessionId) {
                sessionName = activeSessions[sid].customer_name;
                break;
            }
        }
        
        workingSessionId = sessionId;
        cart = []; // Clear cart for new order
        renderCart();
        
        // Update UI Mode
        closeModal('sessionModal');
        closeModal('sheetModal'); // Close map to show menu
        
        document.getElementById('workingSessionInfo').style.display = 'inline-block';
        document.getElementById('workingSessionName').innerText = sessionName;
        document.getElementById('cartStatus').innerText = '注文を入力中...';
        
        const btn = document.getElementById('mainActionButton');
        btn.innerText = '注文を確定する';
        btn.disabled = false;
        btn.style.background = 'var(--confirm-color)';
        btn.onclick = submitOrder;
    }
    
    function cancelOrderMode() {
        workingSessionId = 0;
        cart = [];
        renderCart();
        document.getElementById('workingSessionInfo').style.display = 'none';
        document.getElementById('cartStatus').innerText = '座席を選択して注文を開始してください';
        
        const btn = document.getElementById('mainActionButton');
        btn.innerText = '座席未選択';
        btn.disabled = true;
        btn.style.background = '#ccc';
        btn.onclick = null;
        
        openSheetModal(); // Return to map
    }

    function handleMainAction() {
        if(workingSessionId) {
            submitOrder();
        } else {
            // Deferred Selection
            if(cart.length === 0) return;
            isSelectingForNewOrder = true;
            openSheetModal();
            alert('注文データを持ったまま座席を選択してください（空席のみ選択可能）');
        }
    }

    function submitOrder(onSuccess = null) {
        if(!workingSessionId) return;
        if(cart.length === 0) { alert('商品が選択されていません'); return; }
        
        if(!confirm('注文を確定してよろしいですか？')) return;
        
        return fetch('api/cast/seat_operation.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                action: 'add_order',
                shop_id: shopId,
                session_id: workingSessionId,
                items: cart
            })
        })
        .then(res => res.json())
        .then(data => {
            if(data.status === 'success') {
                if(onSuccess) {
                    onSuccess();
                } else {
                    showOrderComplete();
                }
            } else {
                alert('Order Failed: ' + data.message);
            }
        })
        .catch(e => alert('Error: ' + e.message));
    }

    function executeCheckout() {
        const sessionId = document.getElementById('sessionModal').dataset.sessionId;
        // Don't confirm here immediately. Fetch details for confirmation modal.
        
        fetch('api/cast/seat_operation.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                action: 'get_session_details',
                shop_id: shopId,
                session_id: sessionId
            })
        })
        .then(res => res.json())
        .then(data => {
            if(data.status === 'success') {
                showCheckoutConfirmation(data.orders);
            } else {
                alert('注文詳細の取得に失敗しました: ' + data.message);
            }
        })
        .catch(e => alert('通信エラー: ' + e.message));
    }

    function showCheckoutConfirmation(orders) {
        const container = document.getElementById('checkoutConfirmList');
        let html = '<div style="margin-bottom:15px; display:flex; align-items:center; justify-content:flex-end;">';
        
        // Default to current time or session end time if logic allows
        const now = new Date();
        // Format to YYYY-MM-DDTHH:mm
        const pad = (n) => n.toString().padStart(2, '0');
        const defaultTime = `${now.getFullYear()}-${pad(now.getMonth()+1)}-${pad(now.getDate())}T${pad(now.getHours())}:${pad(now.getMinutes())}`;
        
        html += `<label style="margin-right:10px; font-weight:bold;">チェックアウト時間:</label>`;
        html += `<input type="datetime-local" id="checkoutTimeInput" value="${defaultTime}" style="padding:5px; border:1px solid #ccc; border-radius:4px;">`;
        html += '</div>';

        html += '<table style="width:100%; border-collapse:collapse;">';
        html += '<tr style="background:#f0f0f0; border-bottom:1px solid #ccc;"><th style="padding:5px; text-align:left;">商品名</th><th style="padding:5px;">単価</th><th style="padding:5px;">数量</th><th style="padding:5px;">小計</th></tr>';
        
        let total = 0;
        if(orders) {
            orders.forEach(o => {
                const sub = o.price * o.quantity;
                total += sub;
                let nameHtml = o.item_name;
                if(o.cast_name) {
                    nameHtml += ` <span style="font-size:0.85em; color:#fff; background:#e056fd; padding:2px 4px; border-radius:3px; margin-left:4px;">${o.cast_name}</span>`;
                }
                
                html += `<tr style="border-bottom:1px solid #eee;">
                    <td style="padding:8px;">${nameHtml}</td>
                    <td style="padding:8px; text-align:right;">¥${Number(o.price).toLocaleString()}</td>
                    <td style="padding:8px; text-align:center;">${o.quantity}</td>
                    <td style="padding:8px; text-align:right;">¥${sub.toLocaleString()}</td>
                </tr>`;
            });
        }
        html += '</table>';

        const adjust = parseInt(document.getElementById('checkoutAdjust').value || 0);
        let tax = 0;
        let grandTotal = total;
        
        // Calculate Tax (Assuming 10% on food/drink, but using simple 10% for now as standard)
        // If tax is already included in price, we might just show "Internal Tax".
        // Assuming prices are tax-exclusive based on "Add Tax" button usuage in other POS, OR user wants Breakdown.
        // Let's assume prices are Tax INCLUDED for now (common in night industry) unless specified otherwise.
        // User asked "Subtotal and Sales Tax and Total?". 
        // If prices are Tax Included: Tax = Total * 10/110. Subtotal = Total - Tax.
        // If prices are Tax Excluded: Tax = Total * 0.10. Total = Subtotal + Tax.
        // I will assume Tax Included (內税) as safest default for cafes, showing breakdown.
        // Wait, typical POS "Subtotal, Tax, Total" implies Tax Added.
        // Let's implement standard "Tax 10% Added" logic for clear indication.
        
        // Revised Logic:
        // Subtotal = Sum of Item Prices
        // Tax = Subtotal * 0.10
        // Grand Total = Subtotal + Tax + Adjust
        
        tax = Math.floor(total * 0.10);
        grandTotal = total + tax + adjust;

        html += `<div style="margin-top:15px; border-top:2px solid #ddd; padding-top:10px;">
            <div style="display:flex; justify-content:space-between; margin-bottom:5px;">
                <span>小計 (Subtotal):</span>
                <span>¥${total.toLocaleString()}</span>
            </div>
            <div style="display:flex; justify-content:space-between; margin-bottom:5px;">
                <span>消費税 (Tax 10%):</span>
                <span>¥${tax.toLocaleString()}</span>
            </div>`;
            
        if(adjust !== 0) {
            html += `<div style="display:flex; justify-content:space-between; margin-bottom:5px; color:red;">
                <span>調整額 (Adjust):</span>
                <span>¥${adjust.toLocaleString()}</span>
            </div>`;
        }
            
        html += `</div>`;
        
        container.innerHTML = html;
        document.getElementById('checkoutConfirmTotal').innerText = '¥' + grandTotal.toLocaleString();
        document.getElementById('checkoutConfirmModal').style.display = 'flex';
    }

    function finalizeCheckout() {
        const sessionId = document.getElementById('sessionModal').dataset.sessionId;
        
        fetch('api/cast/seat_operation.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                action: 'checkout',
                shop_id: shopId,
                session_id: sessionId,
                payment_type: document.getElementById('checkoutPayment').value,
                adjust_price: document.getElementById('checkoutAdjust').value,
                staff_id: document.getElementById('checkoutStaff').value,
                issuer_id: document.getElementById('checkoutIssuer').value,
                is_new_customer: document.getElementById('checkoutIsNew').checked ? 1 : 0,
                checkout_time: document.getElementById('checkoutTimeInput') ? document.getElementById('checkoutTimeInput').value : null
            })
        })
        .then(res => res.json())
        .then(data => {
            if(data.status === 'success') {
                alert('お会計完了しました。');
                closeModal('checkoutConfirmModal');
                closeModal('sessionModal');
                fetchSeatStatus();
            } else {
                alert('Checkout Failed: ' + data.message);
                // Keep modal open for retry? Or close?
            }
        })
        .catch(e => alert('Error: ' + e.message));
    }
    
    // --- Cancel Session Logic ---
    function showCancelConfirmation() {
        document.getElementById('cancelConfirmCheck').checked = false;
        toggleCancelExecuteBtn();
        document.getElementById('cancelConfirmModal').style.display = 'flex';
    }

    function toggleCancelExecuteBtn() {
        const checked = document.getElementById('cancelConfirmCheck').checked;
        const btn = document.getElementById('btnExecuteCancel');
        if(checked) {
            btn.disabled = false;
            btn.style.opacity = '1';
            btn.style.cursor = 'pointer';
        } else {
            btn.disabled = true;
            btn.style.opacity = '0.5';
            btn.style.cursor = 'not-allowed';
        }
    }

    function executeCancelSession() {
        const sessionId = document.getElementById('sessionModal').dataset.sessionId;
        if(!sessionId) return;

        const btn = document.getElementById('btnExecuteCancel');
        btn.disabled = true;
        btn.innerText = '処理中...';

        fetch('api/cast/seat_operation.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                action: 'cancel_session',
                shop_id: shopId,
                session_id: sessionId
            })
        })
        .then(res => res.json())
        .then(data => {
            if(data.status === 'success') {
                alert('キャンセル処理が完了しました。\n座席は空席になります。');
                closeModal('cancelConfirmModal');
                closeModal('sessionModal');
                fetchSeatStatus();
            } else {
                alert('キャンセル失敗: ' + data.message);
                btn.disabled = false;
                btn.innerText = '実行する';
                toggleCancelExecuteBtn(); // Re-check state logic
            }
        })
        .catch(e => {
            alert('通信エラー: ' + e.message);
            btn.disabled = false;
            btn.innerText = '実行する';
            toggleCancelExecuteBtn();
        });
    }

        function selectSheet(id, name) {
            selectedSheetId = id;
            document.getElementById('selectedSheetName').innerText = name;
            document.getElementById('input_sheet_no').value = id;
            
            // Update visual selection
            document.querySelectorAll('.seat-obj').forEach(obj => obj.classList.remove('selected'));
            const target = document.querySelector(`.seat-obj[data-id="${id}"]`);
            if(target) target.classList.add('selected');
            
            // Show edit buttons if in edit mode
            if(isEditMode) {
                document.getElementById('toggleShapeBtn').style.display = 'inline-block';
                document.getElementById('deleteSheetBtn').style.display = 'inline-block';
                document.getElementById('renameControls').style.display = 'flex';
                document.getElementById('editSheetName').value = name;
            } else {
                document.getElementById('toggleShapeBtn').style.display = 'none';
                document.getElementById('deleteSheetBtn').style.display = 'none';
                document.getElementById('renameControls').style.display = 'none';
                closeModal('sheetModal');
            }
        }
    
        // --- Edit Mode Logic ---
        function toggleEditMode() {
            isEditMode = document.getElementById('editModeToggle').checked;
            document.getElementById('editControls').style.display = isEditMode ? 'flex' : 'none';
            document.querySelectorAll('.seat-obj').forEach(el => {
                if(isEditMode) el.classList.add('editable');
                else el.classList.remove('editable');
            });
            
            // Hide selection buttons if exiting edit mode
            if(!isEditMode) {
                document.getElementById('toggleShapeBtn').style.display = 'none';
                document.getElementById('deleteSheetBtn').style.display = 'none';
                document.getElementById('renameControls').style.display = 'none';
            } else if(selectedSheetId) {
                // Show if already selected
                document.getElementById('toggleShapeBtn').style.display = 'inline-block';
                document.getElementById('deleteSheetBtn').style.display = 'inline-block';
                document.getElementById('renameControls').style.display = 'flex';
                // Pre-fill name
                const sheet = sheets.find(s => s.sheet_id == selectedSheetId);
                if(sheet) document.getElementById('editSheetName').value = sheet.sheet_name;
            }
        }
        
        function updateSheetName() {
            if(!selectedSheetId) return;
            const newName = document.getElementById('editSheetName').value;
            if(!newName) return;
            
            const sheet = sheets.find(s => s.sheet_id == selectedSheetId);
            if(sheet) {
                sheet.sheet_name = newName;
                renderSheets();
                // update visual
                document.getElementById('selectedSheetName').innerText = newName;
                saveLayout();
            }
        }
    
        function resetLayout() {
            if(!confirm('【警告】\nこの店舗の座席レイアウトを全て削除してリセットします。\n本当によろしいですか？')) return;
            if(!confirm('本当に全て消えます。\nよろしいですか？')) return;

            fetch('api/cast/delete_all_sheets.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ shop_id: shopId })
            })
            .then(res => res.json())
            .then(data => {
                if(data.status === 'success') {
                    sheets = [];
                    renderSheets();
                    alert('レイアウトをリセットしました。');
                } else {
                    alert('リセットに失敗しました: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(e => {
                console.error(e);
                alert('通信エラー');
            });
        }
    
        function startDrag(e, sheet, el) {
            e.preventDefault();
            const container = document.getElementById('seatMapContainer');
            const rect = container.getBoundingClientRect();
            
            const startX = e.clientX;
            const startY = e.clientY;
            const startLeft = sheet.x_pos;
            const startTop = sheet.y_pos;
    
            document.onpointermove = (moveEvent) => {
                const dx = moveEvent.clientX - startX;
                const dy = moveEvent.clientY - startY;
                
                // Convert px to %
                const dxPercent = (dx / rect.width) * 100;
                const dyPercent = (dy / rect.height) * 100;
                
                let newX = startLeft + dxPercent;
                let newY = startTop + dyPercent;
                
                // Boundary check
                newX = Math.max(0, Math.min(100 - sheet.width, newX));
                newY = Math.max(0, Math.min(100 - sheet.height, newY));
                
                el.style.left = newX + '%';
                el.style.top = newY + '%';
                
                sheet.x_pos = Math.round(newX);
                sheet.y_pos = Math.round(newY);
            };
    
            document.onpointerup = () => {
                document.onpointermove = null;
                document.onpointerup = null;
                saveLayout();
            };
        }
    
        function saveLayout() {
            // Optimistic update already done in memory object 'sheets'
            // Send batch update
            const updates = sheets.map(s => ({
                id: s.sheet_id,
                x: s.x_pos,
                y: s.y_pos,
                w: s.width,
                h: s.height,
                name: s.sheet_name,
                type: s.type || 'rect'
            }));
    
            fetch('api/cast/update_sheet_layout.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ updates: updates })
            }).then(res => res.json()).then(data => {
                if(data.status !== 'success') {
                    console.error('Save failed', data);
                }
            });
        }
    
        function addNewSheet(type = 'rect') {
            if (!shopId) {
                alert('Shop ID not found. Cannot add seat.');
                return;
            }
            // type can be 'rect_landscape', 'rect_portrait', 'circle'
            
            // Default dimensions
            let initW = 10; 
            let initH = 10;
            let dbType = 'rect'; 

            // Adjust size based on request (Targeting ~1024x500 canvas)
            if (type === 'rect_landscape') {
                initW = 12; // ~120px
                initH = 16; // ~80px
                dbType = 'rect';
            } else if (type === 'rect_portrait') {
                initW = 8;  // ~80px
                initH = 24; // ~120px
                dbType = 'rect';
            } else if (type === 'circle') {
                initW = 4;  // ~40px
                initH = 8;  // ~40px
                dbType = 'circle';
            }
            
            console.log('Adding seat:', type, initW, initH);

            fetch('api/cast/add_sheet.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    shop_id: shopId,
                    name: 'New Seat',
                    x: 40, y: 40, 
                    w: initW, 
                    h: initH,
                    type: dbType
                })
            })
            .then(res => res.json())
            .then(data => {
                if(data.status === 'success') {
                    // Refetch or append locally
                    const newSheet = {
                        sheet_id: data.sheet_id,
                        shop_id: shopId,
                        sheet_name: 'New Seat',
                        x_pos: 40, y_pos: 40, 
                        width: initW, 
                        height: initH,
                        type: dbType
                    };
                    sheets.push(newSheet);
                    renderSheets();
                    
                    // Re-apply editable class
                    if (isEditMode) {
                        setTimeout(() => {
                            const el = document.querySelector(`.seat-obj[data-id="${data.sheet_id}"]`);
                            if(el) {
                                el.classList.add('editable');
                                el.onmousedown = (e) => startDrag(e, newSheet, el);
                                el.ontouchstart = (e) => startDrag(e, newSheet, el);
                            }
                        }, 50);
                    }
                } else {
                    alert('Error adding sheet: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error adding sheet:', error);
                alert('Add Seat Failed: ' + error.message);
            });
        }

        function deleteSheet() {
            if(!selectedSheetId) return;
            if(!confirm('選択した座席を削除しますか？')) return;

            fetch('api/cast/delete_sheet.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    shop_id: shopId,
                    sheet_id: selectedSheetId
                })
            })
            .then(res => res.json())
            .then(data => {
                if(data.status === 'success') {
                    // Remove locally
                    sheets = sheets.filter(s => s.sheet_id != selectedSheetId);
                    renderSheets();
                    
                    // Unselect
                    selectedSheetId = 0;
                    document.getElementById('selectedSheetName').innerText = '(未選択)';
                    document.getElementById('input_sheet_no').value = '';
                    
                    // Hide buttons
                    document.getElementById('toggleShapeBtn').style.display = 'none';
                    document.getElementById('deleteSheetBtn').style.display = 'none';
                    
                } else {
                    alert('削除に失敗しました: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(e => {
                console.error(e);
                alert('通信エラーが発生しました');
            });
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
            if(!selectedSheetId) { 
                alert('座席を選択してください'); 
                openSheetModal();
                return; 
            }
    
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
            document.getElementById('input_sheet_no').value = selectedSheetId;
    
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
        
        function fetchSessionDetailsForView(sessionId) {
            const container = document.getElementById('sessionCurrentOrders');
            container.innerHTML = '<div style="color:#999; text-align:center;">Loading...</div>';
            
            fetch('api/cast/seat_operation.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    action: 'get_session_details',
                    shop_id: shopId,
                    session_id: sessionId
                })
            })
            .then(res => res.json())
            .then(data => {
                if(data.status === 'success') {
                    if(!data.orders || data.orders.length === 0) {
                        container.innerHTML = '<div style="text-align:center; color:#ccc;">No orders yet</div>';
                        return;
                    }
                    let html = '<table style="width:100%; border-collapse:collapse;">';
                    let subTotal = 0;
                    data.orders.forEach(o => {
                       let castHtml = '';
                       if(o.cast_name && o.cast_name !== '') {
                           castHtml = `<span style="font-size:0.8rem; color:#888; margin-left:5px;">(${o.cast_name})</span>`;
                       }
                       const rowPrice = o.price * o.quantity;
                       subTotal += rowPrice;
                       
                       html += `<tr>
                        <td style="padding:2px;">${o.item_name}</td>
                        <td style="padding:2px; text-align:right;">x${o.quantity}${castHtml}</td>
                        <td style="padding:2px; text-align:right;">¥${Number(rowPrice).toLocaleString()}</td>
                       </tr>`; 
                    });
                    html += '</table>';
                    container.innerHTML = html;
                    
                    // Calc Tax and Total
                    const tax = Math.floor(subTotal * 0.1);
                    const total = subTotal + tax;
                    
                    const totalEl = document.getElementById('sessionTotal');
                    totalEl.innerHTML = `
                        <div style="display:flex; justify-content:space-between; font-size:1rem; color:#555;">
                            <span>小計</span>
                            <span>¥${subTotal.toLocaleString()}</span>
                        </div>
                        <div style="display:flex; justify-content:space-between; font-size:1rem; color:#555;">
                            <span>消費税 (10%)</span>
                            <span>¥${tax.toLocaleString()}</span>
                        </div>
                        <div style="display:flex; justify-content:space-between; font-size:1.3rem; margin-top:5px; border-top:1px solid #ccc; padding-top:5px;">
                            <span>合計</span>
                            <span>¥${total.toLocaleString()}</span>
                        </div>
                    `;
                } else {
                    container.innerText = 'Error loading details';
                }
            })
            .catch(e => {
                container.innerText = 'Conn Error';
            });
        }
    
        function addHidden(form, name, value) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            input.value = value;
            input.className = 'dynamic-item';
            form.appendChild(input);
        }

        // Order Complete Flow
        function showOrderComplete() {
            document.getElementById('orderCompleteModal').style.display = 'flex';
        }

        function completeOrderFlow() {
            closeModal('orderCompleteModal');
            cancelOrderMode();
            fetchSeatStatus();
            openSheetModal(); // Direct user to seat selection
        }
    </script>
    
    </body>
    </html>
