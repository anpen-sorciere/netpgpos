<?php
// BASE注文データ同期
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
    <title>BASE注文データ同期 - <?= htmlspecialchars($shop_name, ENT_QUOTES) ?>管理システム</title>
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
        
        .order-item {
            background-color: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 5px;
            padding: 10px;
            margin-bottom: 10px;
        }
        
        .order-item h4 {
            margin: 0 0 5px 0;
            color: #2c3e50;
        }
        
        .order-item p {
            margin: 2px 0;
            color: #6c757d;
            font-size: 0.9em;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1><i class="fas fa-shopping-cart"></i> BASE注文データ同期</h1>
        
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
            echo '<h2><i class="fas fa-sync-alt"></i> 注文データ同期実行</h2>';
            echo '<p>BASEから注文データを取得して、データベースに保存します。</p>';
            echo '</div>';
            
            // 注文データを取得
            echo '<div class="result-section">';
            echo '<h3><i class="fas fa-download"></i> データ取得中...</h3>';
            
            $orders = $baseApi->getOrders(100, 0);
            
            if (isset($orders['orders']) && is_array($orders['orders'])) {
                $order_count = count($orders['orders']);
                echo '<div class="info">';
                echo '<p><i class="fas fa-info-circle"></i> ' . $order_count . '件の注文データを取得しました。</p>';
                echo '</div>';
                
                // デバッグ: 実際のデータ構造を表示
                echo '<div class="result-section">';
                echo '<h3><i class="fas fa-bug"></i> デバッグ情報</h3>';
                echo '<pre>' . htmlspecialchars(print_r($orders, true)) . '</pre>';
                echo '</div>';
                
                // データベースに保存
                $pdo = connect();
                $saved_count = 0;
                $skipped_count = 0;
                
                foreach ($orders['orders'] as $order) {
                    try {
                        // デバッグ: 個別の注文データ構造を表示
                        echo '<div class="result-section">';
                        echo '<h4>注文データ構造:</h4>';
                        echo '<pre>' . htmlspecialchars(print_r($order, true)) . '</pre>';
                        echo '</div>';
                        
                        // 個別注文詳細APIは利用できないため、スキップ
                        
                        // 注文IDを取得（BASE APIの実際の構造）
                        $order_id = isset($order['unique_key']) ? $order['unique_key'] : null;
                        
                        if (!$order_id) {
                            echo '<div class="error">';
                            echo '<p>注文IDが取得できませんでした。データ構造: ' . htmlspecialchars(print_r($order, true)) . '</p>';
                            echo '</div>';
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
                            VALUES (?, ?, ?, ?, ?, ?)
                        ");
                        
                        // BASE APIの実際の構造に合わせてデータを取得
                        $order_date = null;
                        if (isset($order['ordered'])) {
                            $order_date = date('Y-m-d H:i:s', $order['ordered']);
                        }
                        
                        $customer_name = '';
                        if (isset($order['first_name']) && isset($order['last_name'])) {
                            $customer_name = $order['last_name'] . ' ' . $order['first_name'];
                        }
                        
                        $customer_email = ''; // BASE APIでは顧客メールアドレスは取得できない
                        
                        $total_amount = isset($order['total']) ? $order['total'] : 0;
                        
                        // ステータスを日本語に変換
                        $status_en = isset($order['dispatch_status']) ? $order['dispatch_status'] : '';
                        $status = '';
                        switch ($status_en) {
                            case 'ordered':
                                $status = '注文済み';
                                break;
                            case 'dispatched':
                                $status = '発送済み';
                                break;
                            case 'cancelled':
                                $status = 'キャンセル';
                                break;
                            case 'completed':
                                $status = '完了';
                                break;
                            default:
                                $status = $status_en;
                                break;
                        }
                        
                        $stmt->execute([
                            $order_id,
                            $order_date,
                            $customer_name,
                            $customer_email,
                            $total_amount,
                            $status
                        ]);
                        
                        $saved_count++;
                        
                        // 注文商品データを保存（BASE APIでは商品詳細は別途取得が必要）
                        // 現在のAPIレスポンスには商品詳細が含まれていないため、スキップ
                        // 商品データは別途商品APIで取得する必要があります
                        
                    } catch (Exception $e) {
                        echo '<div class="error">';
                        echo '<p>注文ID: ' . htmlspecialchars($order_id ?? '不明') . ' の保存でエラーが発生しました: ' . htmlspecialchars($e->getMessage()) . '</p>';
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
                echo '<p><i class="fas fa-info-circle"></i> 注文データが見つかりませんでした。</p>';
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
