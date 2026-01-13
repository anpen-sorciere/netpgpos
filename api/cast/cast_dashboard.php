<?php
session_start();
if (!isset($_SESSION['cast_id'])) {
    header('Location: cast_login.php');
    exit;
}

require_once __DIR__ . '/../../../common/config.php';
// require_once __DIR__ . '/../../../common/dbconnect.php'; // 変数名衝突回避のため自前で接続

$cast_name = $_SESSION['cast_name'];
$items = [];

try {
    $pdo = new PDO(
        "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
        $user,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // キャスト本人のアイテムを取得
    // 注文日時が新しい順
    // 期間指定があれば追加するが、まずは全期間（LIMIT付き）
    // サプライズのLogic: item_surprise_date
    
    $sql = "
        SELECT 
            oi.*, 
            o.order_date, 
            o.is_surprise as order_surprise, 
            o.surprise_date as order_surprise_date
        FROM base_order_items oi
        JOIN base_orders o ON oi.base_order_id = o.base_order_id
        WHERE oi.cast_name = :cname
        ORDER BY o.order_date DESC
        LIMIT 200
    ";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':cname' => $cast_name]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $today = date('Y-m-d');

    foreach ($rows as $row) {
        // プライバシーフィルター
        $sDate = $row['item_surprise_date'];
        
        // サプライズ日付設定があり、かつ今日より未来の場合 -> 非表示
        if ($sDate && $sDate > $today) {
            continue;
        }

        $items[] = $row;
    }

} catch (PDOException $e) {
    $error = "データ取得エラー: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cast Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background-color: #fce4ec; padding-bottom: 50px; }
        .header { background: white; padding: 15px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; }
        .welcome { color: #d81b60; font-weight: bold; }
        .item-card { background: white; border-radius: 10px; padding: 15px; margin-bottom: 15px; border-left: 5px solid #e91e63; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .item-date { font-size: 0.85em; color: #888; margin-bottom: 5px; }
        .item-title { font-weight: bold; font-size: 1.1em; color: #333; margin-bottom: 5px; }
        .item-meta { display: flex; justify-content: space-between; font-size: 0.9em; color: #555; }
        .customer-name { color: #d81b60; font-weight: 500; }
        .surprise-badge { background: #ffc107; color: black; padding: 2px 6px; border-radius: 4px; font-size: 0.8em; font-weight: bold; }
        .btn-logout { font-size: 0.85em; }
    </style>
</head>
<body>
    <div class="header">
        <div class="welcome">Welcome, <?= htmlspecialchars($cast_name) ?></div>
        <a href="cast_logout.php" class="btn btn-outline-secondary btn-sm btn-logout">ログアウト</a>
    </div>

    <div class="container">
        <h5 class="mb-3 text-secondary"><i class="fas fa-history"></i> 最近の注文 (<?= count($items) ?>件)</h5>

        <?php if (empty($items)): ?>
            <div class="alert alert-light text-center">
                まだ注文履歴がありません。<br>
                <small class="text-muted">（※モニター画面が開かれたときにデータが同期されます）</small>
            </div>
        <?php else: ?>
            <?php foreach ($items as $item): ?>
                <div class="item-card">
                    <div class="item-date">
                        <?= date('Y/m/d H:i', strtotime($item['order_date'])) ?>
                        <?php if ($item['item_surprise_date']): ?>
                            <span class="surprise-badge"><i class="fas fa-gift"></i> サプライズ (<?= $item['item_surprise_date'] ?>)</span>
                        <?php endif; ?>
                    </div>
                    <div class="item-title">
                        <?= htmlspecialchars($item['product_name'] ?? '') ?>
                        <span class="badge bg-secondary rounded-pill">x<?= $item['quantity'] ?></span>
                    </div>
                    <div class="item-meta">
                        <div>
                            お名前: <span class="customer-name"><?= htmlspecialchars($item['customer_name_from_option'] ?? 'なし') ?></span> 様
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</body>
</html>
