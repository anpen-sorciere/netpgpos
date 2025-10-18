<?php
// BASE CSVデータ同期
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ファイルアップロード制限を緩和
ini_set('upload_max_filesize', '50M');
ini_set('post_max_size', '50M');
ini_set('max_execution_time', 300); // 5分
ini_set('memory_limit', '256M');

require_once '../../common/dbconnect.php';
require_once '../../common/functions.php';

session_start();

$utype = 0;
if (isset($_GET['utype'])) {
    $utype = $_GET['utype'];
    $_SESSION['utype'] = $utype;
} elseif (isset($_SESSION['utype'])) {
    $utype = $_SESSION['utype'];
} else {
    echo "ユーザータイプ情報が無効です。";
    exit();
}

// ショップ名の取得
$shop_name = '';
if ($utype == 1024) {
    $shop_name = 'ソルシエール';
} elseif ($utype == 2) {
    $shop_name = 'レーヴェス';
} elseif ($utype == 3) {
    $shop_name = 'コレクト';
} else {
    exit();
}

?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BASE CSVデータ同期 - <?= htmlspecialchars($shop_name, ENT_QUOTES) ?>管理システム</title>
    <link href="https://use.fontawesome.com/releases/v5.6.1/css/all.css" rel="stylesheet">
    <link href="https://unpkg.com/sanitize.css" rel="stylesheet"/>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .sync-section {
            background-color: #f8f9fa;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 25px;
            border: 1px solid #e9ecef;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }
        
        .sync-section h2 {
            color: #2c3e50;
            margin-top: 0;
            margin-bottom: 20px;
            border-left: 5px solid #3498db;
            padding-left: 15px;
            font-size: 1.3em;
        }
        
        .upload-area {
            border: 2px dashed #dee2e6;
            border-radius: 8px;
            padding: 40px;
            text-align: center;
            background-color: #f8f9fa;
            margin-bottom: 20px;
        }
        
        .upload-area:hover {
            border-color: #3498db;
            background-color: #e3f2fd;
        }
        
        .upload-area i {
            font-size: 3em;
            color: #6c757d;
            margin-bottom: 15px;
        }
        
        .upload-area h3 {
            color: #495057;
            margin-bottom: 10px;
        }
        
        .upload-area p {
            color: #6c757d;
            margin-bottom: 0;
        }
        
        .file-input {
            margin-top: 20px;
        }
        
        .success {
            color: #28a745;
            background-color: #d4edda;
            border: 1px solid #c3e6cb;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 15px;
        }
        
        .error {
            color: #dc3545;
            background-color: #f8d7da;
            border: 1px solid #f5c6cb;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 15px;
        }
        
        .info {
            color: #0c5460;
            background-color: #d1ecf1;
            border: 1px solid #bee5eb;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 15px;
        }
        
        .step-list {
            list-style: none;
            padding: 0;
        }
        
        .step-list li {
            background-color: #fff;
            border: 1px solid #e9ecef;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
        }
        
        .step-list li i {
            color: #3498db;
            margin-right: 15px;
            font-size: 1.2em;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1><i class="fas fa-file-csv"></i> BASE CSVデータ同期</h1>
        
        <div class="sync-section">
            <h2><i class="fas fa-info-circle"></i> CSVデータ同期について</h2>
            <p>BASE APIでは商品詳細が取得できないため、BASE管理画面からCSVファイルをエクスポートして同期する方法をご案内します。</p>
            
            <div class="info">
                <h3><i class="fas fa-lightbulb"></i> この方法の利点</h3>
                <ul>
                    <li>商品名、数量、単価などの詳細情報が取得できます</li>
                    <li>BASE APIの権限制限を回避できます</li>
                    <li>過去の注文データも一括で取得できます</li>
                </ul>
            </div>
        </div>
        
        <div class="sync-section">
            <h2><i class="fas fa-list-ol"></i> CSVエクスポート手順</h2>
            <ol class="step-list">
                <li>
                    <i class="fas fa-sign-in-alt"></i>
                    <div>
                        <strong>BASE管理画面にログイン</strong><br>
                        <a href="https://admin.thebase.com/" target="_blank">https://admin.thebase.com/</a> にアクセス
                    </div>
                </li>
                <li>
                    <i class="fas fa-shopping-cart"></i>
                    <div>
                        <strong>注文管理画面に移動</strong><br>
                        左メニューから「注文管理」をクリック
                    </div>
                </li>
                <li>
                    <i class="fas fa-download"></i>
                    <div>
                        <strong>CSVエクスポート</strong><br>
                        「CSVダウンロード」ボタンをクリックしてファイルをダウンロード
                    </div>
                </li>
                <li>
                    <i class="fas fa-upload"></i>
                    <div>
                        <strong>CSVファイルをアップロード</strong><br>
                        下記のアップロードエリアにファイルをドラッグ&ドロップ
                    </div>
                </li>
            </ol>
        </div>
        
        <?php
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
            try {
                $uploaded_file = $_FILES['csv_file'];
                
                if ($uploaded_file['error'] !== UPLOAD_ERR_OK) {
                    $error_messages = [
                        UPLOAD_ERR_INI_SIZE => 'ファイルサイズがphp.iniのupload_max_filesizeを超えています。',
                        UPLOAD_ERR_FORM_SIZE => 'ファイルサイズがHTMLフォームのMAX_FILE_SIZEを超えています。',
                        UPLOAD_ERR_PARTIAL => 'ファイルが部分的にしかアップロードされませんでした。',
                        UPLOAD_ERR_NO_FILE => 'ファイルがアップロードされませんでした。',
                        UPLOAD_ERR_NO_TMP_DIR => '一時フォルダが見つかりません。',
                        UPLOAD_ERR_CANT_WRITE => 'ファイルの書き込みに失敗しました。',
                        UPLOAD_ERR_EXTENSION => 'ファイルアップロードが拡張機能によって停止されました。'
                    ];
                    
                    $error_code = $uploaded_file['error'];
                    $error_message = isset($error_messages[$error_code]) ? $error_messages[$error_code] : '不明なエラーが発生しました。';
                    
                    throw new Exception('ファイルのアップロードに失敗しました: ' . $error_message . ' (エラーコード: ' . $error_code . ')');
                }
                
                if ($uploaded_file['type'] !== 'text/csv' && pathinfo($uploaded_file['name'], PATHINFO_EXTENSION) !== 'csv') {
                    throw new Exception('CSVファイルを選択してください。');
                }
                
                // CSVファイルの文字エンコーディングを検出・変換
                $csv_content = file_get_contents($uploaded_file['tmp_name']);
                
                // 文字エンコーディングを検出
                $encoding = mb_detect_encoding($csv_content, ['UTF-8', 'SJIS', 'EUC-JP', 'JIS'], true);
                
                if ($encoding === false) {
                    $encoding = 'SJIS'; // デフォルトはShift_JIS
                }
                
                echo '<div class="info">';
                echo '<p><i class="fas fa-info-circle"></i> 検出された文字エンコーディング: ' . htmlspecialchars($encoding) . '</p>';
                echo '</div>';
                
                // UTF-8に変換
                if ($encoding !== 'UTF-8') {
                    $csv_content = mb_convert_encoding($csv_content, 'UTF-8', $encoding);
                }
                
                $lines = explode("\n", $csv_content);
                
                echo '<div class="success">';
                echo '<h3><i class="fas fa-check-circle"></i> CSVファイル読み込み成功</h3>';
                echo '<p>ファイル名: ' . htmlspecialchars($uploaded_file['name']) . '</p>';
                echo '<p>ファイルサイズ: ' . number_format($uploaded_file['size']) . ' bytes</p>';
                echo '<p>行数: ' . count($lines) . ' 行</p>';
                echo '</div>';
                
                // CSVの内容を表示（最初の5行のみ）
                echo '<div class="sync-section">';
                echo '<h2><i class="fas fa-table"></i> CSV内容プレビュー（最初の5行）</h2>';
                echo '<div style="background-color: #f8f9fa; padding: 15px; border-radius: 5px; overflow-x: auto;">';
                echo '<pre>' . htmlspecialchars(implode("\n", array_slice($lines, 0, 5))) . '</pre>';
                echo '</div>';
                echo '</div>';
                
                // CSVデータを解析してデータベースに保存
                echo '<div class="sync-section">';
                echo '<h2><i class="fas fa-database"></i> データベース同期処理</h2>';
                
                $pdo = connect();
                $saved_count = 0;
                $skipped_count = 0;
                $error_count = 0;
                
                // CSVのヘッダー行をスキップ
                $header = array_shift($lines);
                echo '<div class="info">';
                echo '<p><i class="fas fa-info-circle"></i> ヘッダー行: ' . htmlspecialchars($header) . '</p>';
                echo '</div>';
                
                foreach ($lines as $line_num => $line) {
                    if (empty(trim($line))) continue;
                    
                    $data = str_getcsv($line);
                    
                    try {
                        // BASE CSVの列構造に応じてデータを取得
                        // 0:注文ID, 1:注文日時, 2:姓, 3:名, 4:郵便番号, 5:都道府県, 6:住所1, 7:住所2, 8:電話番号, 9:メールアドレス
                        // 10:配送先姓, 11:配送先名, 12:配送先郵便番号, 13:配送先都道府県, 14:配送先住所1, 15:配送先住所2, 16:配送先電話番号
                        // 17:商品名, 18:商品ID, 19:商品コード, 20:商品, 21:単価, 22:支払い方法, 23:数量, 24:合計金額, 25:送料, 26:商品ID, 27:注文ID, 28:配送方法, 29:配送先, 30:配送先住所, 31:配送先電話番号, 32:配送先メールアドレス
                        $order_id = isset($data[0]) ? $data[0] : '';
                        $order_date = isset($data[1]) ? $data[1] : '';
                        $last_name = isset($data[2]) ? $data[2] : '';
                        $first_name = isset($data[3]) ? $data[3] : '';
                        $customer_name = $last_name . ' ' . $first_name;
                        $customer_email = isset($data[9]) ? $data[9] : '';
                        $product_name = isset($data[17]) ? $data[17] : '';
                        $product_id = isset($data[18]) ? $data[18] : '';
                        $price = isset($data[21]) ? floatval($data[21]) : 0;
                        $quantity = isset($data[23]) ? intval($data[23]) : 0;
                        $total_amount = isset($data[24]) ? floatval($data[24]) : 0;
                        
                        if (empty($order_id)) {
                            $error_count++;
                            continue;
                        }
                        
                        // 重複チェック
                        $stmt = $pdo->prepare("SELECT id FROM base_orders WHERE base_order_id = ?");
                        $stmt->execute([$order_id]);
                        
                        if ($stmt->fetch()) {
                            $skipped_count++;
                            continue;
                        }
                        
                        // 注文データを保存
                        $stmt = $pdo->prepare("
                            INSERT INTO base_orders (base_order_id, order_date, customer_name, customer_email, total_amount, status) 
                            VALUES (?, ?, ?, '', ?, 'CSV同期')
                        ");
                        
                        $order_date_formatted = null;
                        if (!empty($order_date)) {
                            $order_date_formatted = date('Y-m-d H:i:s', strtotime($order_date));
                        }
                        
                        $stmt->execute([
                            $order_id,
                            $order_date_formatted,
                            $customer_name,
                            $total_amount
                        ]);
                        
                        // 商品データも保存（商品名がある場合）
                        if (!empty($product_name)) {
                            $stmt = $pdo->prepare("
                                INSERT INTO base_order_items (base_order_id, product_id, product_name, quantity, price) 
                                VALUES (?, '', ?, ?, ?)
                            ");
                            
                            $stmt->execute([
                                $order_id,
                                $product_name,
                                $quantity,
                                $price
                            ]);
                        }
                        
                        $saved_count++;
                        
                    } catch (Exception $e) {
                        $error_count++;
                        echo '<div class="error">';
                        echo '<p>行 ' . ($line_num + 2) . ' の処理でエラー: ' . htmlspecialchars($e->getMessage()) . '</p>';
                        echo '</div>';
                    }
                }
                
                echo '<div class="success">';
                echo '<h3><i class="fas fa-check-circle"></i> CSV同期完了</h3>';
                echo '<p>保存件数: ' . $saved_count . '件</p>';
                echo '<p>スキップ件数: ' . $skipped_count . '件（既存データ）</p>';
                echo '<p>エラー件数: ' . $error_count . '件</p>';
                echo '</div>';
                echo '</div>';
                
            } catch (Exception $e) {
                echo '<div class="error">';
                echo '<h3><i class="fas fa-exclamation-triangle"></i> エラー</h3>';
                echo '<p>' . htmlspecialchars($e->getMessage()) . '</p>';
                echo '</div>';
            }
        }
        ?>
        
        <div class="sync-section">
            <h2><i class="fas fa-upload"></i> CSVファイルアップロード</h2>
            
            <div class="info">
                <h3><i class="fas fa-info-circle"></i> アップロード制限</h3>
                <ul>
                    <li>最大ファイルサイズ: <?= ini_get('upload_max_filesize') ?></li>
                    <li>最大POSTサイズ: <?= ini_get('post_max_size') ?></li>
                    <li>最大実行時間: <?= ini_get('max_execution_time') ?>秒</li>
                    <li>最大メモリ使用量: <?= ini_get('memory_limit') ?></li>
                </ul>
            </div>
            
            <form method="POST" enctype="multipart/form-data">
                <div class="upload-area">
                    <i class="fas fa-cloud-upload-alt"></i>
                    <h3>CSVファイルをアップロード</h3>
                    <p>BASE管理画面からダウンロードしたCSVファイルを選択してください</p>
                    <div class="file-input">
                        <input type="file" name="csv_file" accept=".csv" required>
                    </div>
                </div>
                <div class="control-buttons">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-upload"></i> CSVファイルをアップロード
                    </button>
                </div>
            </form>
        </div>
        
        <div class="control-buttons">
            <a href="../base_data_sync_top.php?utype=<?= htmlspecialchars($utype) ?>" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> BASEデータ同期に戻る
            </a>
        </div>
    </div>
</body>
</html>
