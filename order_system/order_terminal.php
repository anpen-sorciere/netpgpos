<?php
// エラー表示
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

require_once(__DIR__ . '/../dbconnect.php');
require_once(__DIR__ . '/../functions.php');

// 店舗判定（スマホ側は店舗内利用を想定）
$utype = 0;
if (isset($_GET['utype'])) {
    $utype = (int)$_GET['utype'];
    $_SESSION['utype'] = $utype;
} elseif (isset($_SESSION['utype'])) {
    $utype = (int)$_SESSION['utype'];
}

$shop = get_shop_info($utype);

// 商品一覧を取得（receipt_input.php と同様に item_mst から）
$items = [];
try {
	$pdo = connect();
	$items = item_get_all($pdo);
} catch (Throwable $e) {
	error_log('order_terminal items load error: ' . $e->getMessage());
} finally {
	if (isset($pdo)) { disconnect($pdo); }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>店内オーダー（テスト）</title>
    <link href="https://unpkg.com/sanitize.css" rel="stylesheet"/>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <div class="container">
        <h1>店内オーダー（テスト）</h1>
        <p>店舗: <?= h($shop['name']) ?>（utype: <?= h((string)$utype) ?>）</p>

        <form id="orderForm" class="form">
            <input type="hidden" name="shop_utype" value="<?= h((string)$utype) ?>">

            <div class="control">
                <label>テーブル番号</label>
                <select name="table_number" id="table_number">
                    <?php for ($i=1; $i<=10; $i++): ?>
                        <option value="<?= $i ?>"><?= $i ?></option>
                    <?php endfor; ?>
                </select>
            </div>

            <div id="items">
                <!-- dynamic rows here -->
            </div>

            <div class="control-buttons">
                <button type="button" class="btn btn-secondary" id="addRow">行を追加</button>
                <button type="button" class="btn btn-primary" id="submitOrder">送信</button>
                <a href="../index.php?utype=<?= h((string)$utype) ?>" class="btn btn-secondary">メニューへ戻る</a>
            </div>
        </form>

        <div id="result" style="margin-top:12px"></div>
    </div>

    <script>
    const ITEMS = <?php echo json_encode(array_map(function($it){
		return [
			'item_id' => (int)$it['item_id'],
			'item_name' => (string)$it['item_name'],
			'price' => isset($it['price']) ? (int)$it['price'] : 0,
			'category' => isset($it['category']) ? (int)$it['category'] : null
		];
	}, $items), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); ?>;
    (function(){
        const itemsEl = document.getElementById('items');
        const addBtn = document.getElementById('addRow');
        const submitBtn = document.getElementById('submitOrder');

        function createRow(){
			const wrap = document.createElement('div');
			wrap.className = 'control';
			const row = document.createElement('div');
			row.style.display = 'flex';
			row.style.gap = '8px';
			row.style.alignItems = 'center';
			row.style.flexWrap = 'wrap';

			const sel = document.createElement('select');
			sel.className = 'item_select';
			sel.style.minWidth = '220px';
			const opt0 = document.createElement('option');
			opt0.value = '';
			opt0.textContent = '';
			sel.appendChild(opt0);
			ITEMS.forEach(it => {
				const op = document.createElement('option');
				op.value = String(it.item_id);
				op.textContent = it.item_name;
				op.setAttribute('data-price', String(it.price || 0));
				sel.appendChild(op);
			});

			const priceView = document.createElement('span');
			priceView.className = 'price_view';
			priceView.textContent = '';
			sel.addEventListener('change', ()=>{
				const op = sel.selectedOptions[0];
				const p = op ? parseInt(op.getAttribute('data-price') || '0', 10) : 0;
				priceView.textContent = p ? `単価:${p}` : '';
			});

			const qty = document.createElement('input');
			qty.type = 'number';
			qty.min = '1';
			qty.value = '1';
			qty.className = 'quantity';
			qty.style.width = '90px';

			const rm = document.createElement('button');
			rm.type = 'button';
			rm.className = 'btn btn-secondary remove';
			rm.textContent = '削除';
			rm.addEventListener('click', ()=>{ wrap.remove(); });

			row.appendChild(sel);
			row.appendChild(priceView);
			row.appendChild(qty);
			row.appendChild(rm);
			wrap.appendChild(row);
			return wrap;
		}

        addBtn.addEventListener('click',()=>{
            itemsEl.appendChild(createRow());
        });

        // 初期行
        itemsEl.appendChild(createRow());

        submitBtn.addEventListener('click', async ()=>{
            const shop_utype = document.querySelector('input[name=shop_utype]').value;
            const table_number = document.querySelector('#table_number').value;
            const rows = Array.from(itemsEl.querySelectorAll('.control'));
            const items = rows.map(r=>{
                const sel = r.querySelector('.item_select');
                const op = sel && sel.selectedOptions[0];
                const item_id = op && op.value ? parseInt(op.value, 10) : null;
                const unit_price = op ? parseInt(op.getAttribute('data-price') || '0', 10) : 0;
                const quantity = parseInt(r.querySelector('.quantity').value || '0', 10);
                return { item_id, unit_price, quantity };
            }).filter(x=>x.item_id && x.quantity>0);

            if(items.length===0){
                alert('明細を1行以上入力してください');
                return;
            }

            const payload = { shop_utype: parseInt(shop_utype,10), table_number: parseInt(table_number,10), items };
            const res = await fetch('./api/create_order.php', {
                method:'POST',
                headers:{'Content-Type':'application/json'},
                body: JSON.stringify(payload)
            });
            const json = await res.json().catch(()=>({ok:false,error:'invalid json'}));
            const resultEl = document.getElementById('result');
            if(json.ok){
                resultEl.textContent = '送信しました。オーダーID: '+json.order_id;
                itemsEl.innerHTML = '';
                itemsEl.appendChild(createRow());
            }else{
                resultEl.textContent = 'エラー: '+(json.error||'unknown');
            }
        });
    })();
    </script>
</body>
</html>


