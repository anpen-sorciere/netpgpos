<?php
// エラー表示
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

require_once(__DIR__ . '/../../common/dbconnect.php');
require_once(__DIR__ . '/../../common/functions.php');

// セントラルキッチン側の受注一覧プレースホルダー
$utype = 0;
if (isset($_GET['utype'])) {
    $utype = (int)$_GET['utype'];
    $_SESSION['utype'] = $utype;
} elseif (isset($_SESSION['utype'])) {
    $utype = (int)$_SESSION['utype'];
}

$shop = get_shop_info($utype);
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>キッチン受注（テスト）</title>
    <link href="https://unpkg.com/sanitize.css" rel="stylesheet"/>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        @keyframes blink {
            0%, 50% { background-color: #fff9c4; }
            25%, 75% { background-color: #fafafa; }
        }
        .audio-test {
            margin: 8px 0;
            padding: 8px;
            background: #f0f8ff;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>キッチン受注（テスト）</h1>
        <p>受信対象: 全店舗 / 端末店舗: <?= h($shop['name']) ?>（utype: <?= h((string)$utype) ?>）</p>
        <div class="control" style="margin:8px 0;">
            <span style="display:inline-block; padding:2px 8px; border-radius:12px; background:#e8eefc; color:#1a56db; font-size:12px;">未着手</span>
            <span style="display:inline-block; padding:2px 8px; border-radius:12px; background:#fff4e5; color:#b45309; font-size:12px;">調理中</span>
            <span style="display:inline-block; padding:2px 8px; border-radius:12px; background:#e7f8ec; color:#047857; font-size:12px;">提供済み</span>
            <span style="display:inline-block; padding:2px 8px; border-radius:12px; background:#fdecec; color:#b91c1c; font-size:12px;">キャンセル</span>
        </div>
        
        <div class="audio-test">
            <strong>🔊 音声読み上げテスト:</strong>
            <button onclick="announceNewOrder('1024')" style="margin:2px; padding:4px 8px;">ソルシエール読み上げ</button>
            <button onclick="announceNewOrder('2')" style="margin:2px; padding:4px 8px;">レーヴェス読み上げ</button>
            <button onclick="announceNewOrder('3')" style="margin:2px; padding:4px 8px;">コレクト読み上げ</button>
            <button onclick="showAvailableVoices()" style="margin:2px; padding:4px 8px;">利用可能音声確認</button>
            <span style="font-size:12px; color:#666;">※若い日本人女性の声で読み上げます</span>
        </div>

        <div class="control" style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
            <label>店舗フィルタ</label>
            <select id="shop_filter">
                <option value="0">全店</option>
                <option value="1024">ソルシエール</option>
                <option value="2">レーヴェス</option>
                <option value="3">コレクト</option>
            </select>
            <label>状態</label>
            <select id="status_filter">
                <option value="">すべて</option>
                <option value="pending">未着手</option>
                <option value="in_progress">調理中</option>
                <option value="served">提供済み</option>
                <option value="canceled">キャンセル</option>
            </select>
            <button class="btn btn-primary" id="reload">更新</button>
        </div>

        <div id="list"></div>

        <div class="control-buttons">
            <a href="../index.php?utype=<?= h((string)$utype) ?>" class="btn btn-secondary">メニューへ戻る</a>
        </div>
    </div>

    <script>
    (function(){
        const listEl = document.getElementById('list');
        const shopSel = document.getElementById('shop_filter');
        const statusSel = document.getElementById('status_filter');
        const reloadBtn = document.getElementById('reload');

        // 音声読み上げシステム
        let lastOrders = new Set();
        let speechSynthesis = window.speechSynthesis;
        
        function announceNewOrder(shopUtype) {
            const shopNames = {
                '1024': 'ソルシエール',
                '2': 'レーヴェス', 
                '3': 'コレクト'
            };
            
            const shopName = shopNames[String(shopUtype)] || '不明な店舗';
            const message = `${shopName}からオーダー入りました`;
            
            // 既存の読み上げを停止
            speechSynthesis.cancel();
            
            // 利用可能な音声一覧を取得
            const voices = speechSynthesis.getVoices();
            let selectedVoice = null;
            
            // 若い日本人女性の音声を優先選択
            const preferredVoices = [
                'Microsoft Haruka Desktop - Japanese (Japan)',
                'Microsoft Ayumi Desktop - Japanese (Japan)', 
                'Google 日本語',
                'Microsoft Sayaka Desktop - Japanese (Japan)',
                'Microsoft Zira Desktop - English (United States)', // フォールバック
                'Google 日本語 Female'
            ];
            
            // 優先順位で音声を検索
            for (const voiceName of preferredVoices) {
                selectedVoice = voices.find(voice => 
                    voice.name.includes(voiceName) || 
                    (voice.lang.startsWith('ja') && voice.name.toLowerCase().includes('female')) ||
                    (voice.lang.startsWith('ja') && voice.name.includes('女性'))
                );
                if (selectedVoice) break;
            }
            
            // 日本語音声が見つからない場合は最初の日本語音声を使用
            if (!selectedVoice) {
                selectedVoice = voices.find(voice => voice.lang.startsWith('ja'));
            }
            
            // 音声読み上げ設定
            const utterance = new SpeechSynthesisUtterance(message);
            utterance.lang = 'ja-JP';
            utterance.rate = 0.7;   // 少しゆっくりめ
            utterance.pitch = 1.1;  // 少し高めの声
            utterance.volume = 0.9; // 音量を上げる
            
            // 選択した音声を設定
            if (selectedVoice) {
                utterance.voice = selectedVoice;
                console.log(`使用音声: ${selectedVoice.name} (${selectedVoice.lang})`);
            }
            
            // 音声読み上げ実行
            speechSynthesis.speak(utterance);
            console.log(`音声読み上げ: ${message}`);
        }
        
        function checkForNewOrders(currentRows) {
            const currentOrderIds = new Set(currentRows.map(r => r.order_id));
            
            // 新しいオーダーを検出
            for (const row of currentRows) {
                if (!lastOrders.has(row.order_id) && row.item_status === 'pending') {
                    // 新しい未着手オーダーを発見
                    announceNewOrder(row.shop_utype);
                    
                    // 視覚的にも通知（オーダーカードを点滅）
                    setTimeout(() => {
                        const orderCard = document.querySelector(`[data-order-id="${row.order_id}"]`);
                        if (orderCard) {
                            orderCard.style.animation = 'blink 0.5s 3';
                        }
                    }, 100);
                }
            }
            
            lastOrders = currentOrderIds;
        }

        function shopName(utype){
            if(String(utype)==='1024') return 'ソルシエール';
            if(String(utype)==='2') return 'レーヴェス';
            if(String(utype)==='3') return 'コレクト';
            return '不明';
        }

        function statusLabel(status){
            switch(status){
                case 'pending': return '未着手';
                case 'in_progress': return '調理中';
                case 'served': return '提供済み';
                case 'canceled': return 'キャンセル';
                default: return status;
            }
        }

        function statusBadge(status){
            const map = {
                pending: {bg:'#e8eefc', fg:'#1a56db'},
                in_progress: {bg:'#fff4e5', fg:'#b45309'},
                served: {bg:'#e7f8ec', fg:'#047857'},
                canceled: {bg:'#fdecec', fg:'#b91c1c'}
            };
            const c = map[status] || {bg:'#eee', fg:'#333'};
            return `<span style="display:inline-block; padding:2px 8px; border-radius:12px; background:${c.bg}; color:${c.fg}; font-size:12px;">${statusLabel(status)}</span>`;
        }

        async function load(){
            const params = new URLSearchParams();
            const shop = parseInt(shopSel.value,10);
            const status = statusSel.value;
            if(shop>0) params.set('shop_utype', String(shop));
            if(status) params.set('status', status);
            const res = await fetch('./api/list_orders.php?'+params.toString());
            const json = await res.json().catch(()=>({ok:false,error:'invalid json'}));
            if(!json.ok){
                listEl.textContent = 'エラー: '+(json.error||'unknown');
                return;
            }
            const currentRows = json.rows||[];
            checkForNewOrders(currentRows);
            render(currentRows);
        }

        function render(rows){
            if(!rows.length){
                listEl.textContent = 'オーダーはありません';
                return;
            }
            const frag = document.createDocumentFragment();
            let currentOrder = null;
            let orderWrap = null;
            rows.forEach(r=>{
                if(currentOrder!==r.order_id){
                    currentOrder = r.order_id;
                    orderWrap = document.createElement('div');
                    orderWrap.className = 'card';
                    orderWrap.setAttribute('data-order-id', r.order_id);
                    orderWrap.style.padding = '10px';
                    orderWrap.style.margin = '8px 0';
                    orderWrap.style.border = '1px solid #ddd';
                    orderWrap.style.borderRadius = '6px';
                    orderWrap.style.backgroundColor = '#fafafa';

                    const head = document.createElement('div');
                    head.className = 'control';
                    head.style.display = 'flex';
                    head.style.justifyContent = 'space-between';
                    head.style.alignItems = 'center';
                    head.style.marginBottom = '8px';
                    head.style.paddingBottom = '6px';
                    head.style.borderBottom = '1px solid #eee';
                    head.innerHTML = `
                        <div style="font-weight:bold; font-size:16px;">
                            注文 #${r.order_id} | 店舗: ${shopName(r.shop_utype)} | テーブル: ${r.table_number}
                        </div>
                        <div style="font-size:12px; color:#666;">${r.created_at}</div>
                    `;
                    orderWrap.appendChild(head);

                    frag.appendChild(orderWrap);
                }
                
                // テーブル形式で明細表示
                if (!orderWrap.querySelector('.items-table')) {
                    const tableDiv = document.createElement('div');
                    tableDiv.className = 'items-table';
                    tableDiv.innerHTML = `
                        <table style="width:100%; border-collapse:collapse; font-size:14px;">
                            <thead>
                                <tr style="background:#f5f5f5; font-weight:bold;">
                                    <td style="padding:6px; border:1px solid #ddd; text-align:left;">商品名</td>
                                    <td style="padding:6px; border:1px solid #ddd; text-align:center; width:60px;">数量</td>
                                    <td style="padding:6px; border:1px solid #ddd; text-align:center; width:60px;">取消</td>
                                    <td style="padding:6px; border:1px solid #ddd; text-align:center; width:60px;">残</td>
                                    <td style="padding:6px; border:1px solid #ddd; text-align:center; width:80px;">状態</td>
                                    <td style="padding:6px; border:1px solid #ddd; text-align:center; width:200px;">操作</td>
                                </tr>
                            </thead>
                            <tbody class="items-tbody">
                            </tbody>
                        </table>
                    `;
                    orderWrap.appendChild(tableDiv);
                }
                
                const tbody = orderWrap.querySelector('.items-tbody');
                const row = document.createElement('tr');
                row.style.backgroundColor = r.item_status === 'pending' ? '#fff9f9' : '#fff';
                const remain = (parseInt(r.quantity,10) - parseInt(r.canceled_quantity,10));
                
                row.innerHTML = `
                    <td style="padding:8px; border:1px solid #ddd; vertical-align:top;">
                        <div style="font-weight:600; margin-bottom:4px;">${r.item_name}</div>
                        <div style="font-size:11px; color:#666;">ID: ${r.order_item_id}</div>
                    </td>
                    <td style="padding:8px; border:1px solid #ddd; text-align:center; vertical-align:top;">${r.quantity}</td>
                    <td style="padding:8px; border:1px solid #ddd; text-align:center; vertical-align:top;">${r.canceled_quantity}</td>
                    <td style="padding:8px; border:1px solid #ddd; text-align:center; vertical-align:top;"><strong>${remain}</strong></td>
                    <td style="padding:8px; border:1px solid #ddd; text-align:center; vertical-align:top;">${statusBadge(r.item_status)}</td>
                    <td style="padding:8px; border:1px solid #ddd; text-align:center; vertical-align:top;">
                        <div style="display:flex; flex-direction:column; gap:4px; align-items:center;">
                            <div style="display:flex; gap:4px;">
                                <button class="btn btn-primary to_in_progress" style="padding:4px 8px; font-size:11px;">調理開始</button>
                                <button class="btn btn-primary to_served" style="padding:4px 8px; font-size:11px;">提供済み</button>
                            </div>
                            <div style="display:flex; gap:4px; align-items:center; font-size:11px;">
                                <input type="number" min="1" max="${remain}" value="1" style="width:50px; padding:2px;" class="cancel_qty">
                                <button class="btn btn-secondary cancel" style="padding:4px 8px; font-size:11px;">取消</button>
                            </div>
                        </div>
                    </td>
                `;
                
                const qtyEl = row.querySelector('.cancel_qty');
                row.querySelector('.to_in_progress').addEventListener('click', async ()=>{
                    const form = new FormData();
                    form.set('order_item_id', String(r.order_item_id));
                    form.set('status', 'in_progress');
                    const res = await fetch('./api/update_item_status.php',{method:'POST',body:form});
                    const json = await res.json().catch(()=>({ok:false,error:'invalid json'}));
                    if(json.ok){ load(); } else { alert('エラー: '+(json.error||'unknown')); }
                });
                row.querySelector('.to_served').addEventListener('click', async ()=>{
                    const form = new FormData();
                    form.set('order_item_id', String(r.order_item_id));
                    form.set('status', 'served');
                    const res = await fetch('./api/update_item_status.php',{method:'POST',body:form});
                    const json = await res.json().catch(()=>({ok:false,error:'invalid json'}));
                    if(json.ok){ load(); } else { alert('エラー: '+(json.error||'unknown')); }
                });
                row.querySelector('.cancel').addEventListener('click', async ()=>{
                    const qty = parseInt(qtyEl.value||'0',10);
                    if(!(qty>0)) return;
                    const form = new FormData();
                    form.set('order_item_id', String(r.order_item_id));
                    form.set('cancel_qty', String(qty));
                    const res = await fetch('./api/cancel_item.php',{method:'POST',body:form});
                    const json = await res.json().catch(()=>({ok:false,error:'invalid json'}));
                    if(json.ok){ load(); } else { alert('エラー: '+(json.error||'unknown')); }
                });
                tbody.appendChild(row);
            });
            listEl.innerHTML='';
            listEl.appendChild(frag);
        }

        // 利用可能音声確認関数
        function showAvailableVoices() {
            const voices = speechSynthesis.getVoices();
            const japaneseVoices = voices.filter(voice => voice.lang.startsWith('ja'));
            
            console.log('利用可能な日本語音声:');
            japaneseVoices.forEach(voice => {
                console.log(`- ${voice.name} (${voice.lang}) - 女性: ${voice.name.toLowerCase().includes('female') || voice.name.includes('女性') ? 'Yes' : 'No'}`);
            });
            
            alert(`利用可能な日本語音声: ${japaneseVoices.length}個\n詳細はブラウザのコンソール（F12）で確認してください`);
        }
        
        // 音声読み上げ関数をグローバルに公開
        window.announceNewOrder = announceNewOrder;
        window.showAvailableVoices = showAvailableVoices;
        
        reloadBtn.addEventListener('click', load);
        load();
        setInterval(load, 5000);
    })();
    </script>
</body>
</html>
