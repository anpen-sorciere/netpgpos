document.addEventListener('DOMContentLoaded', () => {
    const uploadButton = document.getElementById('upload-button');
    const registerButton = document.getElementById('register-button');
    const csvFile = document.getElementById('csvFile');
    const loading = document.getElementById('loading');
    const statusMessage = document.getElementById('statusMessage');
    const csvTableContainer = document.getElementById('csvTableContainer');
    const modal = document.getElementById('manualEntryModal');
    const modalItemName = document.getElementById('modalItemName');
    const modalPrice = document.getElementById('modalPrice');
    const modalBackPrice = document.getElementById('modalBackPrice');
    const modalCost = document.getElementById('modalCost');
    const modalRegisterBtn = document.getElementById('modalRegisterBtn');
    const modalSkipBtn = document.getElementById('modalSkipBtn');

    let parsedData = [];
    let unregisteredItems = [];
    let currentUnregisteredIndex = 0;

    uploadButton.addEventListener('click', () => {
        const file = csvFile.files[0];
        if (!file) {
            displayMessage('ファイルを選択してください。', true);
            return;
        }

        const selectedStore = document.querySelector('input[name="store"]:checked');
        if (!selectedStore) {
            displayMessage('店舗を選択してください。', true);
            return;
        }

        displayMessage('CSVを読み込み中です...', false, 'loading');
        csvTableContainer.innerHTML = '';
        registerButton.style.display = 'none';

        const reader = new FileReader();
        reader.onload = (e) => {
            const text = e.target.result;
            parseCSV(text);
        };
        reader.onerror = () => {
            displayMessage('ファイルの読み込み中にエラーが発生しました。', true);
        };
        reader.readAsText(file, 'Shift_JIS');
    });

    registerButton.addEventListener('click', async () => {
        if (parsedData.length === 0) {
            displayMessage('登録するデータがありません。', true);
            return;
        }
        const selectedStore = document.querySelector('input[name="store"]:checked').value;
        await registerSales(parsedData, selectedStore);
    });

    async function registerSales(data, storeId) {
        displayMessage('売上を登録しています...', false, 'loading');
        try {
            const response = await fetch('base_sales_register.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'register_sales', store_id: storeId, data: data })
            });
            const result = await response.json();

            displayMessage('', false, 'none');

            if (result.status === 'success') {
                displayMessage(`全${result.registered_count}件の売上を登録しました。`);
                registerButton.style.display = 'none';
                csvTableContainer.innerHTML = '';
            } else if (result.status === 'partial_success') {
                displayMessage(`${result.registered_count}件の売上を登録しました。`);
                unregisteredItems = result.failed_data.filter(item => item.unregisteredItemId);

                if (unregisteredItems.length > 0) {
                    currentUnregisteredIndex = 0;
                    showManualEntryModal();
                } else {
                    // 未登録商品以外で失敗した場合
                    displayMessage(`一部のデータの登録に失敗しました。詳細: ${JSON.stringify(result.failed_data)}`, true);
                }
            } else {
                displayMessage(`登録に失敗しました: ${result.message}`, true);
            }
        } catch (e) {
            displayMessage(`通信エラーが発生しました: ${e.message}`, true);
        }
    }

    async function showManualEntryModal() {
        const currentItem = unregisteredItems[currentUnregisteredIndex];
        modalItemName.textContent = currentItem['商品名'] + ' (商品ID: ' + (currentItem['商品ID'] || '不明') + ')';
        modalPrice.value = currentItem['金額'] ? parseInt(currentItem['金額'].replace(/,/g, ''), 10) : '';
        modalBackPrice.value = 0;
        modalCost.value = 0;
        modal.style.display = 'flex';
    }

    modalRegisterBtn.addEventListener('click', async () => {
        const currentItem = unregisteredItems[currentUnregisteredIndex];
        const newPrice = modalPrice.value;
        const newBackPrice = modalBackPrice.value;
        const newCost = modalCost.value;

        if (!newPrice) {
            displayMessage('販売価格は必須です。', true);
            return;
        }

        modal.style.display = 'none';
        displayMessage('商品を登録しています...', false, 'loading');

        try {
            const response = await fetch('base_sales_register.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'register_item',
                    item_name: currentItem['商品名'],
                    price: newPrice,
                    back_price: newBackPrice,
                    cost: newCost,
                    base_item_id: currentItem['商品ID']
                })
            });
            const result = await response.json();

            if (result.status === 'success') {
                // 成功したら次の未登録商品を処理
                currentUnregisteredIndex++;
                if (currentUnregisteredIndex < unregisteredItems.length) {
                    showManualEntryModal();
                } else {
                    // 全ての未登録商品の手動登録が完了したら、再度「データベースに登録」ボタンを押して登録を完了させる
                    displayMessage('未登録商品の手動登録が完了しました。再度「データベースに登録」ボタンを押してください。');
                    registerButton.style.display = 'block';
                }
            } else {
                displayMessage(`商品登録に失敗しました: ${result.message}`, true);
            }
        } catch (e) {
            displayMessage(`通信エラーが発生しました: ${e.message}`, true);
        }
    });

    modalSkipBtn.addEventListener('click', () => {
        modal.style.display = 'none';
        currentUnregisteredIndex++;
        if (currentUnregisteredIndex < unregisteredItems.length) {
            showManualEntryModal();
        } else {
            displayMessage('手動登録が必要な商品の処理が完了しました。', false);
        }
    });

    function parseCSV(csvText) {
        const lines = csvText.trim().split(/\r\n|\n/);
        if (lines.length < 2) {
            displayMessage('CSVファイルにデータがありません。', true);
            return;
        }

        const headers = parseCSVLine(lines[0]);
        const dataRows = lines.slice(1).map(line => parseCSVLine(line));

        parsedData = dataRows.map(row => {
            const obj = {};
            headers.forEach((header, index) => {
                obj[header] = row[index] || '';
            });
            return obj;
        });

        createTable(headers, dataRows);
        displayMessage('', false, 'none');
        registerButton.style.display = 'block';
    }

    function parseCSVLine(line) {
        const result = [];
        let inQuote = false;
        let currentItem = '';
        for (let i = 0; i < line.length; i++) {
            const char = line[i];
            if (char === '"') {
                if (inQuote && i + 1 < line.length && line[i + 1] === '"') {
                    currentItem += '"';
                    i++;
                } else {
                    inQuote = !inQuote;
                }
            } else if (char === ',' && !inQuote) {
                result.push(currentItem);
                currentItem = '';
            } else {
                currentItem += char;
            }
        }
        result.push(currentItem);
        return result;
    }

    function createTable(headers, data) {
        if (data.length === 0) {
            displayMessage('CSVファイルにデータがありません。', true);
            return;
        }

        let tableHtml = '<table id="csvTable"><thead><tr>';
        headers.forEach(header => {
            tableHtml += `<th>${header}</th>`;
        });
        tableHtml += '</tr></thead><tbody>';

        data.forEach(row => {
            tableHtml += '<tr>';
            row.forEach(cell => {
                tableHtml += `<td>${cell}</td>`;
            });
            tableHtml += '</tr>';
        });

        tableHtml += '</tbody></table>';
        csvTableContainer.innerHTML = tableHtml;
    }

    function displayMessage(message, isError = false, type = 'status') {
        loading.style.display = 'none';
        statusMessage.style.display = 'none';

        if (type === 'loading') {
            loading.style.display = 'block';
            loading.textContent = message;
        } else {
            statusMessage.style.display = 'block';
            statusMessage.textContent = message;
            statusMessage.style.color = isError ? '#e74c3c' : '#2c3e50';
        }
    }
});
