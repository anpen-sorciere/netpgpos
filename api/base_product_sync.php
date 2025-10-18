<?php
// BASE商品データ同期
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../../common/dbconnect.php';
require_once '../../common/functions.php';
require_once 'base_api_client.php';

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
    <title>BASE商品データ同期 - <?= htmlspecialchars($shop_name, ENT_QUOTES) ?>管理システム</title>
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
        
        .result-section {
            background-color: #fff;
            border-radius: 8px;
            padding: 20px;
            border: 1px solid #e9ecef;
            margin-bottom: 20px;
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
        
        .product-item {
            background-color: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 5px;
            padding: 10px;
            margin-bottom: 10px;
        }
        
        .product-item h4 {
            margin: 0 0 5px 0;
            color: #2c3e50;
        }
        
        .product-item p {
            margin: 2px 0;
            color: #6c757d;
            font-size: 0.9em;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1><i class="fas fa-box"></i> BASE商品データ同期</h1>
        
        <?php
        try {
            // BASE API認証チェック
            $baseApi = new BaseApiClient();
            
            if ($baseApi->needsAuth()) {
                echo '<div class="error">';
                echo '<h2><i class="fas fa-exclamation-triangle"></i> 認証エラー</h2>';
                echo '<p>BASE API認証が必要です。認証を行ってから再度お試しください。</p>';
                echo '<a href="../base_data_sync_top.php?utype=' . htmlspecialchars($utype) . '" class="btn btn-primary">BASEデータ同期に戻る</a>';
                echo '</div>';
                exit();
            }
            
            echo '<div class="sync-section">';
            echo '<h2><i class="fas fa-sync-alt"></i> 商品データ同期実行</h2>';
            echo '<p>BASEから商品データを取得して、データベースに保存します。</p>';
            echo '</div>';
            
            // 商品データを取得
            echo '<div class="result-section">';
            echo '<h3><i class="fas fa-download"></i> データ取得中...</h3>';
            
            // デバッグ: 利用可能なエンドポイントをテスト
            echo '<div class="result-section">';
            echo '<h3><i class="fas fa-bug"></i> エンドポイントテスト</h3>';
            
            $test_endpoints = [
                'items',
                'products', 
                'goods',
                'shop',
                'orders'
            ];
            
            foreach ($test_endpoints as $endpoint) {
                try {
                    $test_result = $baseApi->makeRequest($endpoint);
                    echo '<div class="success">';
                    echo '<p><i class="fas fa-check"></i> ' . $endpoint . ' エンドポイント: 成功</p>';
                    echo '</div>';
                } catch (Exception $e) {
                    echo '<div class="error">';
                    echo '<p><i class="fas fa-times"></i> ' . $endpoint . ' エンドポイント: ' . htmlspecialchars($e->getMessage()) . '</p>';
                    echo '</div>';
                }
            }
            echo '</div>';
            
            $products = $baseApi->getProducts(100, 0);
            
            if (isset($products['products']) && is_array($products['products'])) {
                $product_count = count($products['products']);
                echo '<div class="info">';
                echo '<p><i class="fas fa-info-circle"></i> ' . $product_count . '件の商品データを取得しました。</p>';
                echo '</div>';
                
                // デバッグ: 実際のデータ構造を表示
                echo '<div class="result-section">';
                echo '<h3><i class="fas fa-bug"></i> デバッグ情報</h3>';
                echo '<pre>' . htmlspecialchars(print_r($products, true)) . '</pre>';
                echo '</div>';
                
                // データベースに保存
                $pdo = connect();
                $saved_count = 0;
                $skipped_count = 0;
                
                foreach ($products['products'] as $product) {
                    try {
                        // デバッグ: 個別の商品データ構造を表示
                        echo '<div class="result-section">';
                        echo '<h4>商品データ構造:</h4>';
                        echo '<pre>' . htmlspecialchars(print_r($product, true)) . '</pre>';
                        echo '</div>';
                        
                        // 商品IDを取得（BASE APIの実際の構造）
                        $product_id = isset($product['product_id']) ? $product['product_id'] : null;
                        
                        if (!$product_id) {
                            echo '<div class="error">';
                            echo '<p>商品IDが取得できませんでした。データ構造: ' . htmlspecialchars(print_r($product, true)) . '</p>';
                            echo '</div>';
                            continue;
                        }
                        
                        // 重複チェック
                        $stmt = $pdo->prepare("SELECT id FROM base_products WHERE product_id = ?");
                        $stmt->execute([$product_id]);
                        
                        if ($stmt->fetch()) {
                            $skipped_count++;
                            continue;
                        }
                        
                        // 商品データを保存
                        $stmt = $pdo->prepare("
                            INSERT INTO base_products (product_id, product_name, price, stock, status, created_at) 
                            VALUES (?, ?, ?, ?, ?, NOW())
                        ");
                        
                        // BASE APIの実際の構造に合わせてデータを取得
                        $product_name = isset($product['title']) ? $product['title'] : '';
                        $price = isset($product['price']) ? $product['price'] : 0;
                        $stock = isset($product['stock']) ? $product['stock'] : 0;
                        $status = isset($product['status']) ? $product['status'] : '';
                        
                        $stmt->execute([
                            $product_id,
                            $product_name,
                            $price,
                            $stock,
                            $status
                        ]);
                        
                        $saved_count++;
                        
                    } catch (Exception $e) {
                        echo '<div class="error">';
                        echo '<p>商品ID: ' . htmlspecialchars($product_id ?? '不明') . ' の保存でエラーが発生しました: ' . htmlspecialchars($e->getMessage()) . '</p>';
                        echo '</div>';
                    }
                }
                
                echo '<div class="success">';
                echo '<h3><i class="fas fa-check-circle"></i> 同期完了</h3>';
                echo '<p>保存件数: ' . $saved_count . '件</p>';
                echo '<p>スキップ件数: ' . $skipped_count . '件（既存データ）</p>';
                echo '</div>';
                
            } else {
                echo '<div class="info">';
                echo '<p><i class="fas fa-info-circle"></i> 商品データが見つかりませんでした。</p>';
                echo '</div>';
            }
            
            echo '</div>';
            
        } catch (Exception $e) {
            echo '<div class="error">';
            echo '<h2><i class="fas fa-exclamation-triangle"></i> エラー</h2>';
            echo '<p>BASE API連携でエラーが発生しました: ' . htmlspecialchars($e->getMessage()) . '</p>';
            echo '</div>';
        }
        ?>
        
        <div class="control-buttons">
            <a href="../base_data_sync_top.php?utype=<?= htmlspecialchars($utype) ?>" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> BASEデータ同期に戻る
            </a>
        </div>
    </div>
</body>
</html>
