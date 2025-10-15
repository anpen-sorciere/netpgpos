<?php
require_once('./dbconnect.php');
session_start();

$pdo = connect();
$error = [];

// HTMLエスケープ処理
function h($str)
{
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

// カテゴリと税区分を取得
function get_categories($pdo) {
    $stmt = $pdo->query("SELECT category_id, category_name FROM category_mst ORDER BY category_id");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function get_tax_types($pdo) {
    $stmt = $pdo->query("SELECT tax_type_id, tax_type_name FROM tax_mst ORDER BY tax_type_id");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// 商品一覧を取得
function get_items($pdo) {
    $stmt = $pdo->query("
        SELECT 
            im.item_id, 
            im.item_name,
            im.item_yomi,
            cm.category_name, 
            im.price, 
            im.back_price,
            im.cost,
            tm.tax_type_name
        FROM item_mst AS im
        LEFT JOIN category_mst AS cm ON im.category = cm.category_id
        LEFT JOIN tax_mst AS tm ON im.tax_type_id = tm.tax_type_id
        ORDER BY im.item_id DESC
    ");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// 商品の登録、修正、削除
if (!empty($_POST)) {
    if (isset($_POST['insert'])) {
        // 新規登録
        $stmt = $pdo->prepare("INSERT INTO item_mst (item_name, item_yomi, category, price, back_price, cost, tax_type_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $_POST['item_name'], 
            $_POST['item_yomi'],
            $_POST['category'], 
            $_POST['price'], 
            $_POST['back_price'],
            $_POST['cost'],
            $_POST['tax_type_id']
        ]);
        header('Location: item_mst.php');
        exit();
    } elseif (isset($_POST['update'])) {
        // 修正
        $stmt = $pdo->prepare("UPDATE item_mst SET item_name=?, item_yomi=?, category=?, price=?, back_price=?, cost=?, tax_type_id=? WHERE item_id=?");
        $stmt->execute([
            $_POST['item_name'], 
            $_POST['item_yomi'],
            $_POST['category'], 
            $_POST['price'], 
            $_POST['back_price'],
            $_POST['cost'],
            $_POST['tax_type_id'],
            $_POST['item_id']
        ]);
        header('Location: item_mst.php');
        exit();
    } elseif (isset($_POST['delete'])) {
        // 削除
        $stmt = $pdo->prepare("DELETE FROM item_mst WHERE item_id=?");
        $stmt->execute([$_POST['item_id']]);
        header('Location: item_mst.php');
        exit();
    }
}

// 修正用データ取得
$edit_item = null;
if (isset($_GET['edit_id'])) {
    $stmt = $pdo->prepare("SELECT * FROM item_mst WHERE item_id = ?");
    $stmt->execute([$_GET['edit_id']]);
    $edit_item = $stmt->fetch(PDO::FETCH_ASSOC);
}

// カテゴリと税区分を取得
$categories = get_categories($pdo);
$tax_types = get_tax_types($pdo);
$items = get_items($pdo);

?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>商品マスタ管理</title>
    <link href="https://use.fontawesome.com/releases/v5.6.1/css/all.css" rel="stylesheet">
    <link href="https://unpkg.com/sanitize.css" rel="stylesheet"/>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .container {
            max-width: 900px;
        }
        .form-section {
            padding-bottom: 20px;
            margin-bottom: 30px;
            border-bottom: 2px solid #ddd;
        }
        .table-section h2 {
            margin-top: 0;
        }
        .table-section table {
            width: 100%;
            border-collapse: collapse;
        }
        .table-section th, .table-section td {
            padding: 12px;
            border: 1px solid #ccc;
            text-align: left;
        }
        .table-section th {
            background-color: #f8f8f8;
            color: #555;
        }
        .table-section td {
            font-size: 0.9em;
        }
        .action-buttons {
            display: flex;
            gap: 5px;
        }
        .action-buttons .btn {
            padding: 8px 12px;
            font-size: 0.8em;
            border-radius: 4px;
            box-shadow: none;
            transition: background-color 0.2s;
        }
        .action-buttons .btn-edit {
            background-color: #2ecc71;
            color: #fff;
        }
        .action-buttons .btn-delete {
            background-color: #e74c3c;
            color: #fff;
        }
        .action-buttons .btn-edit:hover {
            background-color: #27ae60;
        }
        .action-buttons .btn-delete:hover {
            background-color: #c0392b;
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>商品マスタ管理</h1>
        </header>

        <section class="form-section">
            <h2><?php echo $edit_item ? '商品情報の修正' : '商品の新規登録'; ?></h2>
            <form action="item_mst.php" method="post">
                <?php if ($edit_item) : ?>
                    <input type="hidden" name="item_id" value="<?= h($edit_item['item_id']) ?>">
                    <input type="hidden" name="update" value="1">
                <?php else : ?>
                    <input type="hidden" name="insert" value="1">
                <?php endif; ?>
                <table class="form-table">
                    <tr>
                        <th><label for="item_name">商品名</label></th>
                        <td><input id="item_name" type="text" name="item_name" value="<?= h($edit_item['item_name'] ?? '') ?>" required></td>
                    </tr>
                    <tr>
                        <th><label for="item_yomi">商品名ヨミガナ</label></th>
                        <td><input id="item_yomi" type="text" name="item_yomi" value="<?= h($edit_item['item_yomi'] ?? '') ?>"></td>
                    </tr>
                    <tr>
                        <th><label for="category">カテゴリー</label></th>
                        <td>
                            <select name="category" id="category">
                                <?php foreach ($categories as $category) : ?>
                                    <option value="<?= h($category['category_id']) ?>"
                                        <?php if ($edit_item && $edit_item['category'] == $category['category_id']) echo 'selected'; ?>>
                                        <?= h($category['category_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="price">価格</label></th>
                        <td><input id="price" type="number" name="price" value="<?= h($edit_item['price'] ?? '') ?>" required></td>
                    </tr>
                    <tr>
                        <th><label for="back_price">バック価格</label></th>
                        <td><input id="back_price" type="number" name="back_price" value="<?= h($edit_item['back_price'] ?? '') ?>"></td>
                    </tr>
                    <tr>
                        <th><label for="cost">仕入価格</label></th>
                        <td><input id="cost" type="number" name="cost" value="<?= h($edit_item['cost'] ?? '') ?>"></td>
                    </tr>
                    <tr>
                        <th><label for="tax_type_id">税区分</label></th>
                        <td>
                            <select name="tax_type_id" id="tax_type_id">
                                <?php foreach ($tax_types as $tax_type) : ?>
                                    <option value="<?= h($tax_type['tax_type_id']) ?>"
                                        <?php if ($edit_item && $edit_item['tax_type_id'] == $tax_type['tax_type_id']) echo 'selected'; ?>>
                                        <?= h($tax_type['tax_type_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                </table>
                <div class="control-buttons">
                    <button type="submit" class="btn btn-primary"><?php echo $edit_item ? '更新' : '登録'; ?></button>
                    <?php if ($edit_item) : ?>
                        <a href="item_mst.php" class="btn btn-secondary">キャンセル</a>
                    <?php endif; ?>
                    <a href="index.php" class="btn btn-secondary">メニューへ</a>
                </div>
            </form>
        </section>

        <section class="table-section">
            <h2>商品一覧</h2>
            <table class="result-table">
                <thead>
                    <tr>
                        <th>商品ID</th>
                        <th>商品名</th>
                        <th>ヨミガナ</th>
                        <th>カテゴリー</th>
                        <th>価格</th>
                        <th>バック価格</th>
                        <th>仕入価格</th>
                        <th>税区分</th>
                        <th>原価率</th>
                        <th>利益</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item) : ?>
                        <?php
                            $price = (float)($item['price'] ?? 0);
                            $cost = (float)($item['cost'] ?? 0);
                            $back_price = (float)($item['back_price'] ?? 0);
                            $total_cost = $cost + $back_price;
                            $profit = $price - $total_cost;
                            $cost_rate = ($price > 0) ? round($total_cost / $price * 100, 1) : 0;
                        ?>
                        <tr>
                            <td><?= h($item['item_id']) ?></td>
                            <td><?= h($item['item_name']) ?></td>
                            <td><?= h($item['item_yomi']) ?></td>
                            <td><?= h($item['category_name']) ?></td>
                            <td><?= h($item['price']) ?></td>
                            <td><?= h($item['back_price']) ?></td>
                            <td><?= h($item['cost']) ?></td>
                            <td><?= h($item['tax_type_name']) ?></td>
                            <td><?= $cost_rate ?>%</td>
                            <td><?= number_format($profit) ?></td>
                            <td class="action-buttons">
                                <a href="item_mst.php?edit_id=<?= h($item['item_id']) ?>" class="btn btn-edit">修正</a>
                                <form action="item_mst.php" method="post" onsubmit="return confirm('本当に削除しますか？');">
                                    <input type="hidden" name="item_id" value="<?= h($item['item_id']) ?>">
                                    <input type="hidden" name="delete" value="1">
                                    <button type="submit" class="btn btn-delete">削除</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>
    </div>
</body>
</html>