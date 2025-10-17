<?php
// PHPエラーレポートを有効にする
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once '../common/dbconnect.php'; // データベース接続ファイルを読み込む
require_once '../common/functions.php'; // 共通関数ファイルを読み込む

// データベースに接続
$pdo = connect();

// データベースからキャスト名の一覧を取得
$cast_names_array = [];
$casts_with_id = [];
try {
    $casts = cast_get_all($pdo, true);
    // 取得した連想配列からcast_nameとcast_idを抽出
    $cast_names_array = array_column($casts, 'cast_name');
    $casts_with_id = $casts;
    // 配列の要素数が多い順にソート（長い名前を先にマッチさせるため）
    usort($cast_names_array, function($a, $b) {
        return mb_strlen($b) - mb_strlen($a);
    });
} catch (PDOException $e) {
    // エラーハンドリング
    // 必要に応じてログに記録
}

// 不要なヘッダーをPHP側で定義
$unnecessary_headers = [
    '氏(請求先)', '名(請求先)', '郵便番号(請求先)', '都道府県(請求先)', '住所(請求先)', '住所2(請求先)', '電話番号(請求先)', 'メールアドレス(請求先)',
    '代引き手数料', '配送時間帯', '注文メモ', '調整金額', '特典', '税率', '送料', '支払い方法', '購入元', '配送日',
    '郵便番号(配送先)', '都道府県(配送先)', '住所(配送先)', '住所2(配送先)', '電話番号(配送先)'
];

// PHP配列をJavaScriptで使えるようにJSONに変換
$unnecessary_headers_json = json_encode($unnecessary_headers);
$cast_names_json = json_encode($cast_names_array, JSON_UNESCAPED_UNICODE);
$casts_with_id_json = json_encode($casts_with_id, JSON_UNESCAPED_UNICODE);
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CSVアップローダー</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .error-bar {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            background-color: #f44336; /* 赤色 */
            color: white;
            padding: 15px;
            text-align: center;
            font-weight: bold;
            display: none;
            z-index: 1000;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .error-bar-close {
            color: white;
            font-size: 20px;
            font-weight: bold;
            cursor: pointer;
            padding: 0 10px;
            border: none;
            background: none;
        }
        .unregistered-table, .registered-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .unregistered-table th, .unregistered-table td, .registered-table th, .registered-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        .unregistered-table th, .registered-table th {
            background-color: #f2f2f2;
        }
        .error-row {
            background-color: #ffe6e6 !important; /* !important で既存のスタイルを上書き */
        }
        .unregistered-data-message {
            color: #d32f2f;
            font-weight: bold;
            margin-bottom: 10px;
        }
        .highlight-unregistered {
            border: 2px solid #f44336;
        }
        .error-cell {
            background-color: #f9dcdc;
            color: #d32f2f;
            font-weight: bold;
        }
        .header-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .registered-data-section {
            margin-top: 40px;
        }
        .registered-data-section h2 {
            margin-bottom: 10px;
        }
        .search-controls {
            display: flex;
            gap: 15px;
            align-items: center;
            margin-bottom: 20px;
        }
        .edit-row-btn, .delete-row-btn {
            cursor: pointer;
            padding: 5px 10px;
            border-radius: 4px;
            border: none;
            color: white;
        }
        .edit-row-btn {
            background-color: #4CAF50;
        }
        .delete-row-btn {
            background-color: #f44336;
        }
        .save-edit-btn {
            background-color: #2196F3;
            color: white;
            padding: 5px 10px;
            border-radius: 4px;
            border: none;
        }
    </style>
</head>
<body>
    <div id="error-bar" class="error-bar" style="display: none;">
        <span id="error-message-text"></span>
        <button class="error-bar-close" onclick="document.getElementById('error-bar').style.display='none';">&times;</button>
    </div>

    <div class="container">
        <div class="header-section">
            <h1>CSVアップロード</h1>
            <button id="delete-button" style="background-color: #d9534f; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer;">テストデータを削除</button>
        </div>
        <div class="control-panel">
            <div class="form-section">
                <h2>コントロールパネル</h2>
                <div class="radio-group">
                    <label><input type="radio" name="store" value="1" required> ソルシエール</label>
                    <label><input type="radio" name="store" value="2"> レーヴェス</label>
                </div>
                <input type="file" id="csvFile" accept=".csv">
                <div id="fileNameDisplay" style="margin-top: 5px; font-weight: bold; color: #333;"></div>
            </div>
            <div class="button-group">
                <button id="upload-button" style="display: none;">CSVを読み込む</button>
                <button id="register-button" style="display: none;">データベースに登録</button>
            </div>
        </div>
        <div class="message-container">
            <div id="statusMessage"></div>
        </div>
        <div id="loading" style="display: none;">処理中...</div>
        <div id="csv-table-container">
        </div>

        <div id="unregistered-container" class="unregistered-section" style="display: none;">
            <h3>登録失敗データの手動入力</h3>
            <p class="unregistered-data-message">以下の商品はデータベースに登録されていません。修正して登録してください。</p>
            <div id="unregistered-table-container"></div>
            <div class="manual-buttons">
                <button id="manual-register-button">入力内容で登録</button>
                <button id="manual-cancel-button">キャンセルして次へ</button>
            </div>
        </div>

        <hr>

        <div id="registered-data-section" class="registered-data-section">
            <h2>登録済みデータ</h2>
            <div class="search-controls">
                <label for="search-date">日付:</label>
                <input type="date" id="search-date">
                <label for="search-cast">キャスト:</label>
                <select id="search-cast">
                    <option value="">すべて</option>
                    </select>
                <label for="search-keyword">キーワード:</label>
                <input type="text" id="search-keyword" placeholder="注文ID, 商品名">
                <button id="search-button">検索</button>
            </div>
            <div id="registered-data-container">
                </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const csvFile = document.getElementById('csvFile');
            const uploadButton = document.getElementById('upload-button');
            const registerButton = document.getElementById('register-button');
            const deleteButton = document.getElementById('delete-button');
            const manualRegisterButton = document.getElementById('manual-register-button');
            const manualCancelButton = document.getElementById('manual-cancel-button');
            const tableContainer = document.getElementById('csv-table-container');
            const unregisteredContainer = document.getElementById('unregistered-container');
            const unregisteredTableContainer = document.getElementById('unregistered-table-container');
            const registeredDataContainer = document.getElementById('registered-data-container');
            const searchDateInput = document.getElementById('search-date');
            const searchCastSelect = document.getElementById('search-cast');
            const searchKeywordInput = document.getElementById('search-keyword');
            const searchButton = document.getElementById('search-button');
            const loading = document.getElementById('loading');
            const statusMessage = document.getElementById('statusMessage');
            const errorBar = document.getElementById('error-bar');
            const errorMessageText = document.getElementById('error-message-text');
            const storeRadios = document.getElementsByName('store');
            const fileNameDisplay = document.getElementById('fileNameDisplay');

            const unnecessaryHeaders = <?php echo $unnecessary_headers_json; ?>;
            const castNames = <?php echo $cast_names_json; ?>;
            const castsWithId = <?php echo $casts_with_id_json; ?>;
            const removeItems = [
                '商品オプション「お客様名」',
                '商品オプション「キャスト名」',
                '商品オプション「フレーバー」',
                '商品オプション「リッチオムライス組み合わせ」',
                '商品オプション「ご来店年月日と時間」',
                '商品オプション「丼サイズ」'
            ];
            
            let originalData = [];
            let dataToRegister = [];
            let headers = [];
            let customerMap = {};
            let castMap = {};
            let noteMap = {};
            let failedRows = [];

            // キャスト選択肢を動的に生成
            castsWithId.forEach(cast => {
                const option = document.createElement('option');
                option.value = cast.cast_name;
                option.textContent = cast.cast_name;
                searchCastSelect.appendChild(option);
            });

            function displayMessage(message, isError = false) {
                statusMessage.textContent = message;
                statusMessage.className = isError ? 'error-message' : 'success-message';
                statusMessage.style.display = 'block';

                if (isError) {
                    errorMessageText.textContent = message;
                    errorBar.style.display = 'flex';
                    setTimeout(() => {
                        errorBar.style.display = 'none';
                    }, 5000);
                } else {
                    errorBar.style.display = 'none';
                }
            }
            
            function extractCastName(itemName, castOptionName = null) {
                if (castOptionName) {
                    if (castOptionName.endsWith('ちゃん')) {
                        const normalizedName = castOptionName.trim().replace(/ちゃん$/, '');
                        for (const castName of castNames) {
                            if (normalizedName === castName) {
                                return castName;
                            }
                        }
                    }
                    return castOptionName.trim();
                }
                
                for (const castName of castNames) {
                    if (itemName.includes(castName)) {
                        return castName;
                    }
                }
                
                return 'その他';
            }
            
            function processCSV(csvText) {
                const lines = csvText.trim().split('\n');
                if (lines.length <= 1) {
                    displayMessage('CSVファイルに有効なデータがありません。', true);
                    loading.style.display = 'none';
                    return;
                }
                headers = lines[0].split(',').map(h => h.trim().replace(/"/g, ''));
            
                const allData = lines.slice(1).map(line => {
                    const row = {};
                    let inQuote = false;
                    let currentCell = '';
                    let colIndex = 0;
            
                    for (let i = 0; i < line.length; i++) {
                        const char = line[i];
                        if (char === '"') {
                            if (i > 0 && line[i-1] === '"' && inQuote) {
                                currentCell += '"';
                            } else {
                                inQuote = !inQuote;
                            }
                        } else if (char === ',' && !inQuote) {
                            if (headers[colIndex]) {
                                row[headers[colIndex]] = currentCell.replace(/^"|"$/g, '').trim();
                            }
                            currentCell = '';
                            colIndex++;
                        } else {
                            currentCell += char;
                        }
                    }
                    if (headers[colIndex]) {
                        row[headers[colIndex]] = currentCell.replace(/^"|"$/g, '').trim();
                    }
                    return row;
                });
            
                allData.forEach(row => {
                    const orderId = row['注文ID'];
                    const itemName = row['商品名'];
                    const variation = row['バリエーション'];
            
                    if (itemName === '商品オプション「お客様名」' && variation) {
                        customerMap[orderId] = variation;
                    }
                    if (itemName === '商品オプション「キャスト名」' && variation) {
                        castMap[orderId] = variation;
                    }
                    if (row['注文メモ']) {
                        noteMap[orderId] = row['注文メモ'];
                    }
                });
            
                originalData = allData.filter(row => {
                    const itemName = row['商品名'];
                    const castName = row['キャスト名'];
                    return !removeItems.includes(itemName) && !(castName && castName.includes('下谷あゆ'));
                });
            
                dataToRegister = originalData.map(row => {
                    const castOption = castMap[row['注文ID']] || '';
                    const finalCastName = extractCastName(row['商品名'], castOption);
            
                    const newRow = {
                        "注文ID": row['注文ID'],
                        "注文日時": formatDate(row['注文日時']),
                        "数量": row['数量'],
                        "商品名": row['商品名'],
                        "キャスト名": finalCastName,
                        "商品ID": row['商品ID'],
                        "備考": noteMap[row['注文ID']] || '' // 備考を追加
                    };
                    return newRow;
                });

                failedRows = dataToRegister.filter(row => row['キャスト名'] === 'その他').map(row => {
                    return {
                        ...row,
                        '備考': noteMap[row['注文ID']] || ''
                    };
                });

                if (failedRows.length > 0) {
                    displayMessage(`データベースに登録されていないキャスト名が${failedRows.length}件見つかりました。手動で選択してください。`, true);
                    displayTable(failedRows, true, unregisteredTableContainer);
                    unregisteredContainer.style.display = 'block';
                    registerButton.style.display = 'none';
                    manualRegisterButton.disabled = true;
                } else {
                    displayMessage(`CSVの読み込みが完了しました。${originalData.length}件のデータが見つかりました。`);
                    displayTable(dataToRegister, false, tableContainer);
                    if (originalData.length > 0) {
                        registerButton.style.display = 'block';
                    }
                }
            }

            function displayTable(data, isFailed = false, targetContainer) {
                targetContainer.innerHTML = '';
            
                const table = document.createElement('table');
                table.classList.add(isFailed ? 'unregistered-table' : 'registered-table');
            
                const headersToShow = [
                    '注文ID', '注文日時', '商品名', '数量', '価格', 'キャスト名', '備考'
                ];
                if (!isFailed) {
                    headersToShow.push('操作');
                }
                
                const thead = table.createTHead();
                const headerRow = thead.insertRow();
                headersToShow.forEach(header => {
                    const th = document.createElement('th');
                    th.textContent = header;
                    headerRow.appendChild(th);
                });
            
                const tbody = table.createTBody();
                let lastOrderId = null;
                let isOdd = true;
            
                data.forEach(row => {
                    const orderId = row['注文ID'];
                    const itemName = row['商品名'];
                    const castName = row['キャスト名'];
            
                    if (orderId !== lastOrderId) {
                        isOdd = !isOdd;
                        lastOrderId = orderId;
                    }
            
                    const newRow = tbody.insertRow();
                    newRow.className = isOdd ? 'highlight-odd' : 'highlight-even';

                    if (isFailed) {
                        newRow.classList.add('error-row');
                    }
            
                    if (!itemName.includes('商品オプション')) {
                        newRow.classList.add('bold-row');
                    }
            
                    headersToShow.forEach(header => {
                        const td = newRow.insertCell();
                        
                        if (isFailed && header === 'キャスト名') {
                            const select = document.createElement('select');
                            select.className = 'manual-cast-select';
                            select.dataset.orderId = row['注文ID'];
                            select.dataset.itemName = row['商品名'];
                            
                            const emptyOption = document.createElement('option');
                            emptyOption.value = '';
                            emptyOption.textContent = '選択してください';
                            select.appendChild(emptyOption);

                            castsWithId.forEach(cast => {
                                const option = document.createElement('option');
                                option.value = cast.cast_name;
                                option.textContent = cast.cast_name;
                                select.appendChild(option);
                            });

                            select.addEventListener('change', checkManualInputs);
                            td.appendChild(select);
                            td.classList.add('error-cell');
                        } else if (header === '操作' && !isFailed) {
                            const editBtn = document.createElement('button');
                            editBtn.textContent = '編集';
                            editBtn.className = 'edit-row-btn';
                            editBtn.dataset.id = row['id'];
                            td.appendChild(editBtn);

                            const deleteBtn = document.createElement('button');
                            deleteBtn.textContent = '削除';
                            deleteBtn.className = 'delete-row-btn';
                            deleteBtn.dataset.id = row['id'];
                            td.appendChild(deleteBtn);
                        } else {
                            const cellData = row[header] || '';
                            td.textContent = cellData;
                        }
                    });
                });
                targetContainer.appendChild(table);

                if (!isFailed) {
                    setupEditDeleteListeners(table);
                }
            }

            function checkManualInputs() {
                const manualSelects = unregisteredTableContainer.querySelectorAll('.manual-cast-select');
                let allSelected = true;
                if (manualSelects.length === 0) {
                    allSelected = false;
                } else {
                    manualSelects.forEach(select => {
                        if (select.value === '') {
                            allSelected = false;
                        }
                    });
                }
                manualRegisterButton.disabled = !allSelected;
            }

            function sendDataToBackend(action, data = null, storeId = null) {
                const payload = { action };
                if (data) {
                    payload.data = data;
                }
                if (storeId) {
                    payload.store_id = storeId;
                }

                return fetch('base_sales_register.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(payload)
                });
            }

            async function fetchRegisteredData(params = {}) {
                loading.style.display = 'block';
                registeredDataContainer.innerHTML = '';
                displayMessage('登録済みデータを読み込み中...');
                
                try {
                    const response = await fetch('base_sales_register.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({ action: 'fetch_sales', params: params })
                    });
                    const result = await response.json();
                    loading.style.display = 'none';

                    if (result.status === 'success') {
                        if (result.data.length > 0) {
                            displayTable(result.data, false, registeredDataContainer);
                            displayMessage(`登録済みデータを${result.data.length}件表示しました。`);
                        } else {
                            displayMessage('該当するデータが見つかりませんでした。', true);
                        }
                    } else {
                        displayMessage(`データの取得に失敗しました: ${result.message}`, true);
                    }
                } catch (error) {
                    loading.style.display = 'none';
                    displayMessage(`通信エラーが発生しました: ${error.message}`, true);
                }
            }

            function setupEditDeleteListeners(table) {
                table.querySelectorAll('.edit-row-btn').forEach(button => {
                    button.addEventListener('click', (e) => {
                        const row = e.target.closest('tr');
                        const cells = row.querySelectorAll('td');
                        
                        cells.forEach((cell, index) => {
                            const headerText = table.querySelector('thead th:nth-child(' + (index + 1) + ')').textContent;
                            if (headerText === 'キャスト名' || headerText === '数量' || headerText === '備考') {
                                const originalText = cell.textContent;
                                cell.innerHTML = '';
                                
                                if (headerText === 'キャスト名') {
                                    const select = document.createElement('select');
                                    select.className = 'edit-cast-select';
                                    castsWithId.forEach(cast => {
                                        const option = document.createElement('option');
                                        option.value = cast.cast_name;
                                        option.textContent = cast.cast_name;
                                        if (cast.cast_name === originalText) {
                                            option.selected = true;
                                        }
                                        select.appendChild(option);
                                    });
                                    cell.appendChild(select);
                                } else {
                                    const input = document.createElement('input');
                                    input.type = headerText === '数量' ? 'number' : 'text';
                                    input.value = originalText;
                                    cell.appendChild(input);
                                }
                            }
                        });

                        // 編集ボタンを保存ボタンに切り替える
                        const saveBtn = document.createElement('button');
                        saveBtn.textContent = '保存';
                        saveBtn.className = 'save-edit-btn';
                        saveBtn.dataset.id = e.target.dataset.id;
                        e.target.replaceWith(saveBtn);
                        
                        // 削除ボタンを無効化
                        const deleteBtn = row.querySelector('.delete-row-btn');
                        if (deleteBtn) {
                            deleteBtn.disabled = true;
                        }

                        saveBtn.addEventListener('click', handleSaveEdit);
                    });
                });

                table.querySelectorAll('.delete-row-btn').forEach(button => {
                    button.addEventListener('click', (e) => {
                        const id = e.target.dataset.id;
                        if (confirm('このデータを本当に削除しますか？')) {
                            sendDataToBackend('delete_sales', { id: id })
                                .then(response => response.json())
                                .then(data => {
                                    if (data.status === 'success') {
                                        displayMessage('データを正常に削除しました。');
                                        fetchRegisteredData(); // データを再読み込み
                                    } else {
                                        displayMessage(`削除に失敗しました: ${data.message}`, true);
                                    }
                                })
                                .catch(error => {
                                    displayMessage(`通信エラーが発生しました: ${error.message}`, true);
                                });
                        }
                    });
                });
            }

            async function handleSaveEdit(e) {
                const id = e.target.dataset.id;
                const row = e.target.closest('tr');
                const castNameElement = row.querySelector('.edit-cast-select');
                const quantityElement = row.querySelector('input[type="number"]');
                const noteElement = row.querySelector('input[type="text"]');

                const updatedData = {
                    id: id,
                    cast_name: castNameElement.value,
                    quantity: quantityElement.value,
                    note: noteElement.value
                };

                try {
                    const response = await sendDataToBackend('update_sales', { data: updatedData });
                    const result = await response.json();

                    if (result.status === 'success') {
                        displayMessage('データを正常に更新しました。');
                        fetchRegisteredData(); // データを再読み込み
                    } else {
                        displayMessage(`更新に失敗しました: ${result.message}`, true);
                    }
                } catch (error) {
                    displayMessage(`通信エラーが発生しました: ${error.message}`, true);
                }
            }

            // 初期表示として登録済みデータを読み込む
            fetchRegisteredData();

            csvFile.addEventListener('change', () => {
                const file = csvFile.files[0];
                const selectedStore = document.querySelector('input[name="store"]:checked');

                if (!selectedStore) {
                    displayMessage('店舗を選択してください。', true);
                    csvFile.value = '';
                    fileNameDisplay.textContent = '';
                    return;
                }

                if (!file) {
                    fileNameDisplay.textContent = '';
                    return;
                }

                loading.style.display = 'block';
                statusMessage.style.display = 'none';
                tableContainer.innerHTML = '';
                unregisteredContainer.style.display = 'none';
                registerButton.style.display = 'none';
                failedRows = [];
                manualRegisterButton.disabled = true;

                const reader = new FileReader();
                reader.onload = function(e) {
                    const text = e.target.result;
                    const lines = text.trim().split('\n');

                    if (lines.length > 1) {
                        const secondLine = lines[1].split(',')[1].replace(/^"|"$/g, '').trim();
                        const dateParts = secondLine.split(' ')[0].split('-');
                        const year = dateParts[0];
                        const month = dateParts[1];
                        let newFileName = '';

                        if (file.name.startsWith('sorciereosk-official-ec')) {
                            newFileName = `${year}年${month}月ソルシエール遠隔.csv`;
                        } else if (file.name.startsWith('revesosk-official-ec')) {
                            newFileName = `${year}年${month}月レーヴェス遠隔.csv`;
                        }
                        
                        if (newFileName) {
                            fileNameDisplay.textContent = `ファイル名: ${newFileName}`;
                        } else {
                            fileNameDisplay.textContent = `ファイル名: ${file.name}`;
                        }
                    } else {
                        fileNameDisplay.textContent = `ファイル名: ${file.name}`;
                    }
                    
                    processCSV(text);
                    loading.style.display = 'none';
                };
                reader.onerror = () => {
                    loading.style.display = 'none';
                    displayMessage('ファイルの読み込み中にエラーが発生しました。', true);
                };
                reader.readAsText(file, 'Shift_JIS');
            });

            uploadButton.addEventListener('click', () => {
                const file = csvFile.files[0];
                if (!file) {
                    displayMessage('CSVファイルを選択してください。', true);
                } else {
                    displayMessage('ファイルを選択すると自動的に読み込みが開始されます。', false);
                }
            });

            registerButton.addEventListener('click', () => {
                loading.style.display = 'block';
                statusMessage.style.display = 'none';

                const selectedStore = document.querySelector('input[name="store"]:checked').value;

                sendDataToBackend('register_sales', dataToRegister, selectedStore)
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('ネットワークエラー: ' + response.statusText);
                        }
                        return response.json();
                    })
                    .then(data => {
                        loading.style.display = 'none';
                        if (data.status === 'success') {
                            displayMessage(`データを正常に登録しました。登録件数: ${data.registered_count}件`);
                            registerButton.style.display = 'none';
                            tableContainer.innerHTML = '';
                            csvFile.value = ''; // ファイル選択をクリア
                            fileNameDisplay.textContent = ''; // ファイル名表示をクリア
                            fetchRegisteredData(); // データを再読み込み
                        } else if (data.status === 'partial_success') {
                            displayMessage(`一部のデータを登録しました。成功件数: ${data.registered_count}件、失敗件数: ${data.failed_data.length}件。`, true);
                            failedRows = data.failed_data;
                            displayTable(failedRows, true, unregisteredTableContainer);
                            unregisteredContainer.style.display = 'block';
                            registerButton.style.display = 'none';
                        } else {
                            displayMessage(`登録に失敗しました: ${data.message}`, true);
                        }
                    })
                    .catch(error => {
                        loading.style.display = 'none';
                        displayMessage(`通信エラーが発生しました: ${error.message}`, true);
                    });
            });

            manualRegisterButton.addEventListener('click', () => {
                const newDataToRegister = failedRows.map(row => {
                    const newRow = { ...row };
                    const selectElement = unregisteredTableContainer.querySelector(`select[data-order-id='${row['注文ID']}'][data-item-name='${row['商品名']}']`);
                    
                    if (selectElement && selectElement.value) {
                          newRow['キャスト名'] = selectElement.value;
                    }
                    return newRow;
                });

                const selectedStore = document.querySelector('input[name="store"]:checked').value;

                loading.style.display = 'block';
                statusMessage.style.display = 'none';

                sendDataToBackend('register_sales', newDataToRegister, selectedStore)
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('ネットワークエラー: ' + response.statusText);
                        }
                        return response.json();
                    })
                    .then(data => {
                        loading.style.display = 'none';
                        if (data.status === 'success' || data.status === 'partial_success') {
                            displayMessage(`手動入力データを正常に登録しました。登録件数: ${data.registered_count}件`);
                            unregisteredContainer.style.display = 'none';
                            tableContainer.innerHTML = '';
                            registerButton.style.display = 'none';
                            csvFile.value = ''; // ファイル選択をクリア
                            fileNameDisplay.textContent = ''; // ファイル名表示をクリア
                            fetchRegisteredData(); // データを再読み込み
                        } else {
                            displayMessage(`手動入力データの登録に失敗しました: ${data.message}`, true);
                        }
                    })
                    .catch(error => {
                        loading.style.display = 'none';
                        displayMessage(`通信エラーが発生しました: ${error.message}`, true);
                    });
            });

            manualCancelButton.addEventListener('click', () => {
                unregisteredContainer.style.display = 'none';
                tableContainer.innerHTML = '';
                registerButton.style.display = 'none';
                displayMessage('手動登録をキャンセルしました。', true);
            });

            deleteButton.addEventListener('click', () => {
                if (confirm('本当にテストデータを削除してもよろしいですか？この操作は元に戻せません。')) {
                    loading.style.display = 'block';
                    statusMessage.style.display = 'none';
                    tableContainer.innerHTML = '';
                    unregisteredContainer.style.display = 'none';
                    registerButton.style.display = 'none';

                    sendDataToBackend('delete_test_data')
                        .then(response => {
                            if (!response.ok) {
                                throw new Error('ネットワークエラー: ' + response.statusText);
                            }
                            return response.json();
                        })
                        .then(data => {
                            loading.style.display = 'none';
                            if (data.status === 'success') {
                                displayMessage('テストデータが正常に削除されました。');
                                csvFile.value = ''; // ファイル選択をクリア
                                fileNameDisplay.textContent = ''; // ファイル名表示をクリア
                                fetchRegisteredData(); // データを再読み込み
                            } else {
                                displayMessage(`データの削除に失敗しました: ${data.message}`, true);
                            }
                        })
                        .catch(error => {
                            loading.style.display = 'none';
                            displayMessage(`通信エラーが発生しました: ${error.message}`, true);
                        });
                }
            });

            searchButton.addEventListener('click', () => {
                const params = {
                    date: searchDateInput.value,
                    cast_name: searchCastSelect.value,
                    keyword: searchKeywordInput.value
                };
                fetchRegisteredData(params);
            });

            function formatDate(dateStr) {
                const parts = dateStr.replace(/\//g, '-').split(' ');
                const dateParts = parts[0].split('-');
                const year = dateParts[0];
                const month = dateParts[1].padStart(2, '0');
                const day = dateParts[2].padStart(2, '0');
                const timeParts = parts[1] ? parts[1].split(':') : ['00', '00', '00'];
                const hour = timeParts[0].padStart(2, '0');
                const minute = timeParts[1].padStart(2, '0');
                const second = timeParts[2] ? timeParts[2].padStart(2, '0') : '00';
                
                return `${year}-${month}-${day} ${hour}:${minute}:${second}`;
            }
        });
    </script>
</body>
</html>
