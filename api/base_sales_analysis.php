<?php
// BASE売上データ分析
error_reporting(E_ALL);
ini_set('display_errors', 1);

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
    <title>BASE売上データ分析 - <?= htmlspecialchars($shop_name, ENT_QUOTES) ?>管理システム</title>
    <link href="https://use.fontawesome.com/releases/v5.6.1/css/all.css" rel="stylesheet">
    <link href="https://unpkg.com/sanitize.css" rel="stylesheet"/>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .analysis-section {
            background-color: #f8f9fa;
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 25px;
            border: 1px solid #e9ecef;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }
        
        .analysis-section h2 {
            color: #2c3e50;
            margin-top: 0;
            margin-bottom: 20px;
            border-left: 5px solid #3498db;
            padding-left: 15px;
            font-size: 1.3em;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            background-color: #fff;
            border-radius: 8px;
            padding: 20px;
            border: 1px solid #e9ecef;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            text-align: center;
        }
        
        .stat-card h3 {
            color: #3498db;
            margin: 0 0 10px 0;
            font-size: 2em;
        }
        
        .stat-card p {
            color: #6c757d;
            margin: 0;
            font-size: 0.9em;
        }
        
        .order-list {
            background-color: #fff;
            border-radius: 8px;
            padding: 20px;
            border: 1px solid #e9ecef;
            margin-bottom: 20px;
        }
        
        .order-item {
            background-color: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 5px;
            padding: 15px;
            margin-bottom: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .order-info {
            flex: 1;
        }
        
        .order-info h4 {
            margin: 0 0 5px 0;
            color: #2c3e50;
        }
        
        .order-info p {
            margin: 2px 0;
            color: #6c757d;
            font-size: 0.9em;
        }
        
        .order-amount {
            font-size: 1.2em;
            font-weight: bold;
            color: #28a745;
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 0.8em;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .status-注文済み {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .status-発送済み {
            background-color: #d4edda;
            color: #155724;
        }
        
        .status-キャンセル {
            background-color: #f8d7da;
            color: #721c24;
        }
        
        .status-完了 {
            background-color: #d1ecf1;
            color: #0c5460;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1><i class="fas fa-chart-line"></i> BASE売上データ分析</h1>
        
        <?php
        try {
            $pdo = connect();
            
            // 基本統計情報を取得
            $stmt = $pdo->query("SELECT COUNT(*) as total_orders, SUM(total_amount) as total_sales FROM base_orders");
            $stats = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // 日別売上を取得
            $stmt = $pdo->query("
                SELECT DATE(order_date) as order_date, 
                       COUNT(*) as order_count, 
                       SUM(total_amount) as daily_sales 
                FROM base_orders 
                WHERE order_date IS NOT NULL 
                GROUP BY DATE(order_date) 
                ORDER BY order_date DESC 
                LIMIT 10
            ");
            $daily_sales = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // ステータス別統計
            $stmt = $pdo->query("
                SELECT status, COUNT(*) as count 
                FROM base_orders 
                GROUP BY status 
                ORDER BY count DESC
            ");
            $status_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // 最新の注文を取得
            $stmt = $pdo->query("
                SELECT * FROM base_orders 
                ORDER BY order_date DESC 
                LIMIT 20
            ");
            $recent_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo '<div class="analysis-section">';
            echo '<h2><i class="fas fa-chart-bar"></i> 基本統計</h2>';
            echo '<div class="stats-grid">';
            echo '<div class="stat-card">';
            echo '<h3>' . number_format($stats['total_orders']) . '</h3>';
            echo '<p>総注文数</p>';
            echo '</div>';
            echo '<div class="stat-card">';
            echo '<h3>¥' . number_format($stats['total_sales']) . '</h3>';
            echo '<p>総売上</p>';
            echo '</div>';
            echo '<div class="stat-card">';
            echo '<h3>¥' . number_format($stats['total_sales'] / max($stats['total_orders'], 1)) . '</h3>';
            echo '<p>平均注文金額</p>';
            echo '</div>';
            echo '</div>';
            echo '</div>';
            
            echo '<div class="analysis-section">';
            echo '<h2><i class="fas fa-calendar-alt"></i> 日別売上（最新10日）</h2>';
            echo '<div class="order-list">';
            foreach ($daily_sales as $day) {
                echo '<div class="order-item">';
                echo '<div class="order-info">';
                echo '<h4>' . htmlspecialchars($day['order_date']) . '</h4>';
                echo '<p>' . $day['order_count'] . '件の注文</p>';
                echo '</div>';
                echo '<div class="order-amount">¥' . number_format($day['daily_sales']) . '</div>';
                echo '</div>';
            }
            echo '</div>';
            echo '</div>';
            
            echo '<div class="analysis-section">';
            echo '<h2><i class="fas fa-tasks"></i> ステータス別統計</h2>';
            echo '<div class="stats-grid">';
            foreach ($status_stats as $status) {
                echo '<div class="stat-card">';
                echo '<h3>' . $status['count'] . '</h3>';
                echo '<p>' . htmlspecialchars($status['status']) . '</p>';
                echo '</div>';
            }
            echo '</div>';
            echo '</div>';
            
            echo '<div class="analysis-section">';
            echo '<h2><i class="fas fa-shopping-cart"></i> 最新の注文（最新20件）</h2>';
            echo '<div class="order-list">';
            foreach ($recent_orders as $order) {
                $status_class = 'status-' . $order['status'];
                echo '<div class="order-item">';
                echo '<div class="order-info">';
                echo '<h4>' . htmlspecialchars($order['customer_name']) . '</h4>';
                echo '<p>注文日: ' . htmlspecialchars($order['order_date']) . '</p>';
                echo '<p>注文ID: ' . htmlspecialchars($order['base_order_id']) . '</p>';
                echo '<span class="status-badge ' . $status_class . '">' . htmlspecialchars($order['status']) . '</span>';
                echo '</div>';
                echo '<div class="order-amount">¥' . number_format($order['total_amount']) . '</div>';
                echo '</div>';
            }
            echo '</div>';
            echo '</div>';
            
        } catch (Exception $e) {
            echo '<div class="analysis-section">';
            echo '<h2><i class="fas fa-exclamation-triangle"></i> エラー</h2>';
            echo '<p>データベースエラーが発生しました: ' . htmlspecialchars($e->getMessage()) . '</p>';
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
