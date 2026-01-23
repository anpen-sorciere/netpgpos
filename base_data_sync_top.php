<?php
// エラー表示を有効化
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../common/config.php';
require_once __DIR__ . '/../common/dbconnect.php';
require_once __DIR__ . '/../common/functions.php';
session_start();

$utype = 0;
if (isset($_GET['utype'])) {
    $utype = $_GET['utype'];
    $_SESSION['utype'] = $utype;
} elseif (isset($_SESSION['utype'])) {
    $utype = $_SESSION['utype'];
} else {
    // utypeが設定されていない場合にエラーを表示
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
    <title>BASEデータ同期 - <?= htmlspecialchars($shop_name, ENT_QUOTES) ?>管理システム</title>
    <link href="https://use.fontawesome.com/releases/v5.6.1/css/all.css" rel="stylesheet">
    <link href="https://unpkg.com/sanitize.css" rel="stylesheet"/>
    <link rel="stylesheet" href="css/style.css">
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
        
        .sync-section p {
            color: #6c757d;
            margin-bottom: 15px;
            line-height: 1.6;
        }
        
        .feature-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .feature-card {
            background-color: #fff;
            border-radius: 8px;
            padding: 20px;
            border: 1px solid #e9ecef;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .feature-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        
        .feature-card h3 {
            color: #3498db;
            margin-top: 0;
            margin-bottom: 10px;
            font-size: 1.1em;
        }
        
        .feature-card p {
            color: #6c757d;
            font-size: 0.9em;
            margin-bottom: 15px;
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8em;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .status-planned {
            background-color: #fff3cd;
            color: #856404;
        }
        
        .status-development {
            background-color: #d1ecf1;
            color: #0c5460;
        }
        
        .status-completed {
            background-color: #d4edda;
            color: #155724;
        }
        
        .coming-soon {
            text-align: center;
            padding: 40px 20px;
            background-color: #f8f9fa;
            border-radius: 12px;
            border: 2px dashed #dee2e6;
            margin-top: 30px;
        }
        
        .coming-soon i {
            font-size: 3em;
            color: #6c757d;
            margin-bottom: 15px;
        }
        
        .coming-soon h3 {
            color: #495057;
            margin-bottom: 10px;
        }
        
        .coming-soon p {
            color: #6c757d;
            margin-bottom: 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1><i class="fas fa-sync-alt"></i> BASEデータ同期</h1>
        
        <?php
        // BASE API認証チェック
        try {
            require_once __DIR__ . '/api/classes/base_api_client.php';
            $baseApi = new BaseApiClient();

            if ($baseApi->needsAuth()) {
                // 認証が必要な場合
                echo '<div class="sync-section">';
                echo '<h2><i class="fas fa-key"></i> BASE API認証</h2>';
                echo '<p>BASE APIを使用するには認証が必要です。以下のボタンをクリックして認証を行ってください。</p>';
                echo '<div class="control-buttons">';
                echo '<a href="' . $baseApi->getAuthUrl() . '" class="btn btn-primary">';
                echo '<i class="fas fa-sign-in-alt"></i> BASE API認証を開始';
                echo '</a>';
                echo '</div>';
                echo '</div>';
            } else {
                // 認証済みの場合
                echo '<div class="sync-section">';
                echo '<h2><i class="fas fa-check-circle"></i> BASE API認証済み</h2>';
                echo '<p>BASE API認証が完了しています。以下の機能をご利用いただけます。</p>';
                echo '<div class="feature-grid">';
                echo '<div class="feature-card">';
                echo '<h3><i class="fas fa-shopping-cart"></i> 注文データ同期</h3>';
                echo '<p>BASEから注文情報を取得し、売上データとして管理します。</p>';
                echo '<a href="api/base_order_sync.php?utype=' . htmlspecialchars($utype) . '" class="btn btn-primary">同期実行</a>';
                echo '</div>';
                echo '<div class="feature-card">';
                echo '<h3><i class="fas fa-chart-line"></i> 売上データ分析</h3>';
                echo '<p>同期した注文データを基に、売上分析レポートを表示します。</p>';
                echo '<a href="api/base_sales_analysis.php?utype=' . htmlspecialchars($utype) . '" class="btn btn-primary">分析表示</a>';
                echo '</div>';
                echo '<div class="feature-card">';
                echo '<h3><i class="fas fa-file-csv"></i> CSVデータ同期</h3>';
                echo '<p>BASE管理画面からCSVファイルをエクスポートして、商品詳細を含むデータを同期します。</p>';
                echo '<a href="api/base_csv_sync.php?utype=' . htmlspecialchars($utype) . '" class="btn btn-primary">CSV同期</a>';
                echo '</div>';
                // 商品データ同期は権限の問題で一時的に無効化
                /*
                echo '<div class="feature-card">';
                echo '<h3><i class="fas fa-box"></i> 商品データ同期</h3>';
                echo '<p>商品マスタ情報をBASEから同期し、在庫管理に活用します。</p>';
                echo '<a href="api/base_product_sync.php?utype=' . htmlspecialchars($utype) . '" class="btn btn-primary">同期実行</a>';
                echo '</div>';
                */
                echo '</div>';
                echo '</div>';
            }
        } catch (Exception $e) {
            // エラーが発生した場合
            echo '<div class="sync-section">';
            echo '<h2><i class="fas fa-exclamation-triangle"></i> エラー</h2>';
            echo '<p>BASE API連携でエラーが発生しました: ' . htmlspecialchars($e->getMessage()) . '</p>';
            echo '</div>';
        }
        ?>

        <div class="sync-section">
            <h2><i class="fas fa-info-circle"></i> 機能概要</h2>
            <p>BASEというECシステムで運営しているサイトの売り上げデータを自前で管理するための同期機能です。</p>
            <p>BASEのAPIを利用して、注文データ、商品データ、顧客データなどを自動的に取得・同期し、独自の分析や管理を行えるようにします。</p>
        </div>
        
        <div class="feature-grid">
            <div class="feature-card">
                <h3><i class="fas fa-shopping-cart"></i> 注文データ同期</h3>
                <p>BASEから注文情報を取得し、売上データとして管理します。</p>
                <span class="status-badge status-planned">計画中</span>
            </div>
            
            <div class="feature-card">
                <h3><i class="fas fa-box"></i> 商品データ同期</h3>
                <p>商品マスタ情報をBASEから同期し、在庫管理に活用します。</p>
                <span class="status-badge status-planned">計画中</span>
            </div>
            
            <div class="feature-card">
                <h3><i class="fas fa-users"></i> 顧客データ同期</h3>
                <p>顧客情報をBASEから取得し、CRM機能として活用します。</p>
                <span class="status-badge status-planned">計画中</span>
            </div>
            
            <div class="feature-card">
                <h3><i class="fas fa-chart-line"></i> 売上分析</h3>
                <p>同期したデータを基に、詳細な売上分析レポートを作成します。</p>
                <span class="status-badge status-planned">計画中</span>
            </div>
            
            <div class="feature-card">
                <h3><i class="fas fa-database"></i> データベース設計</h3>
                <p>BASEデータ用のテーブル設計とデータ構造の最適化を行います。</p>
                <span class="status-badge status-development">開発中</span>
            </div>
            
            <div class="feature-card">
                <h3><i class="fas fa-cog"></i> API連携設定</h3>
                <p>BASEのAPI認証設定と接続テスト機能を実装します。</p>
                <span class="status-badge status-development">開発中</span>
            </div>
        </div>
        
        <div class="sync-section">
            <h2><i class="fas fa-tasks"></i> 今後の実装予定</h2>
            <ul style="color: #6c757d; line-height: 1.8;">
                <li>BASE API認証機能の実装</li>
                <li>注文データ取得・同期機能</li>
                <li>商品マスタ同期機能</li>
                <li>顧客データ同期機能</li>
                <li>売上データ分析・レポート機能</li>
                <li>自動同期スケジュール機能</li>
                <li>エラーハンドリング・ログ機能</li>
            </ul>
        </div>
        
        <div class="coming-soon">
            <i class="fas fa-tools"></i>
            <h3>開発準備中</h3>
            <p>BASEデータ同期機能は現在開発準備中です。<br>
            詳細な仕様設計とAPI連携の準備を進めています。</p>
        </div>
        
        <div class="control-buttons">
            <a href="index.php?utype=<?= htmlspecialchars($utype) ?>" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> メニューに戻る
            </a>
        </div>
    </div>
</body>
</html>
