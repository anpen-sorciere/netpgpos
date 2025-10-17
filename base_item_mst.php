<?php
require_once('./dbconnect.php');
require_once('./functions.php');
session_start();

$pdo = connect();
$error = [];

// 商品一覧を取得
function get_base_items($pdo) {
    $stmt = $pdo->query("
        SELECT
            bim.item_id,
            bim.item_name,
            bim.price,
            bim.back_price,
            bim.cost,
            bim.base_item_id,
            im.item_name AS parent_item_name
        FROM base_item_mst AS bim
        LEFT JOIN item_mst AS im ON bim.item_mst_id = im.item_id
        ORDER BY bim.item_id DESC
    ");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// 商品の登録、修正、削除
if (!empty($_POST)) {
    if (isset($_POST['insert'])) {
        // 新規登録
        $stmt = $pdo->prepare("INSERT INTO base_item_mst (item_name, price, back_price, cost, base_item_id, item_mst_id) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $_POST['item_name'],
            $_POST['price'],
            $_POST['back_price'],
            $_POST['cost'],
            $_POST['base_item_id'],
            $_POST['item_mst_id']
        ]);
        header('Location: base_item_mst.php');
        exit();
    } elseif (isset($_POST['update'])) {
        // 修正
        $stmt = $pdo->prepare("UPDATE base_item_mst SET item_name=?, price=?, back_price=?, cost=?, base_item_id=?, item_mst_id=? WHERE item_id=?");
        $stmt->execute([
            $_POST['item_name'],
            $_POST['price'],
            $_POST['back_price'],
            $_POST['cost'],
            $_POST['base_item_id'],
            $_POST['item_mst_id'],
            $_POST['item_id']
        ]);
        header('Location: base_item_mst.php');
        exit();
    } elseif (isset($_POST['delete'])) {
        // 削除
        $stmt = $pdo->prepare("DELETE FROM base_item_mst WHERE item_id=?");
        $stmt->execute([$_POST['item_id']]);
        header('Location: base_item_mst.php');
        exit();
    }
}

// 修正用データ取得
$edit_item = null;
if (isset($_GET['edit_id'])) {
    $stmt = $pdo->prepare("SELECT * FROM base_item_mst WHERE item_id = ?");
    $stmt->execute([$_GET['edit_id']]);
    $edit_item = $stmt->fetch(PDO::FETCH_ASSOC);
}

// 参照元のitem_mstデータを取得
$parent_items = item_get_all($pdo);
$items = get_base_items($pdo);

// 選択されたitem_mst_idからcostを取得する（修正フォーム用）
$selected_cost = 0;
if ($edit_item && isset($edit_item['item_mst_id'])) {
    $parent_item = item_get($pdo, $edit_item['item_mst_id']);
    if ($parent_item) {
        $selected_cost = $parent_item['cost'];
    }
}

?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BASE商品マスタ管理</title>
    <link href="https://use.fontawesome.com/releases/v5.6.1/css/all.css" rel="stylesheet">
    <link href="https://unpkg.com/sanitize.css" rel="stylesheet"/>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .container { max-width: 900px; }
        .form-section { padding-bottom: 20px; margin-bottom: 30px; border-bottom: 2px solid #ddd; }
        .table-section h2 { margin-top: 0; }
        .table-section table { width: 100%; border-collapse: collapse; }
        .table-section th, .table-section td { padding: 12px; border: 1px solid #ccc; text-align: left; }
        .table-section th { background-color: #f8f8f8; color: #555; }
        .table-section td { font-size: 0.9em; }
        .action-buttons { display: flex; gap: 5px; }
        .action-buttons .btn { padding: 8px 12px; font-size: 0.8em; border-radius: 4px; box-shadow: none; transition: background-color 0.2s; }
        .action-buttons .btn-edit { background-color: #2ecc71; color: #fff; }
        .action-buttons .btn-delete { background-color: #e74c3c; color: #fff; }
        .action-buttons .btn-edit:hover { background-color: #27ae60; }
        .action-buttons .btn-delete:hover { background-color: #c0392b; }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>BASE商品マスタ管理</h1>
        </header>

        <section class="form-section">
            <h2><?php echo $edit_item ? 'BASE商品情報の修正' : 'BASE商品の新規登録'; ?></h2>
            <form action="base_item_mst.php" method="post">
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
                        <th><label for="price">販売価格(税込)</label></th>
                        <td><input id="price" type="number" name="price" value="<?= h($edit_item['price'] ?? '') ?>" required></td>
                    </tr>
                    <tr>
                        <th><label for="back_price">バック金額</label></th>
                        <td><input id="back_price" type="number" name="back_price" value="<?= h($edit_item['back_price'] ?? '') ?>"></td>
                    </tr>
                    <tr>
                        <th><label for="item_mst_id">元の商品</label></th>
                        <td>
                            <select name="item_mst_id" id="item_mst_id" onchange="updateCost(this.value)">
                                <option value="">選択してください</option>
                                <?php foreach ($parent_items as $parent_item) : ?>
                                    <option value="<?= h($parent_item['item_id']) ?>"
                                        data-cost="<?= h($parent_item['cost']) ?>"
                                        <?php if ($edit_item && $edit_item['item_mst_id'] == $parent_item['item_id']) echo 'selected'; ?>>
                                        <?= h($parent_item['item_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="cost">仕入価格</label></th>
                        <td>
                            <input id="cost" type="number" name="cost" value="<?= h($edit_item['cost'] ?? '') ?>" readonly>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="base_item_id">BASE商品ID</label></th>
                        <td><input id="base_item_id" type="number" name="base_item_id" value="<?= h($edit_item['base_item_id'] ?? '') ?>" required></td>
                    </tr>
                </table>
                <div class="control-buttons">
                    <button type="submit" class="btn btn-primary"><?php echo $edit_item ? '更新' : '登録'; ?></button>
                    <?php if ($edit_item) : ?>
                        <a href="base_item_mst.php" class="btn btn-secondary">キャンセル</a>
                    <?php endif; ?>
                    <a href="index.php" class="btn btn-secondary">メニューへ</a>
                </div>
            </form>
        </section>

        <section class="table-section">
            <h2>BASE商品一覧</h2>
            <table class="result-table">
                <thead>
                    <tr>
                        <th>商品ID</th>
                        <th>商品名</th>
                        <th>販売価格</th>
                        <th>バック金額</th>
                        <th>仕入価格</th>
                        <th>BASE商品ID</th>
                        <th>元の商品名</th>
                        <th>操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item) : ?>
                        <tr>
                            <td><?= h($item['item_id']) ?></td>
                            <td><?= h($item['item_name']) ?></td>
                            <td><?= h($item['price']) ?></td>
                            <td><?= h($item['back_price']) ?></td>
                            <td><?= h($item['cost']) ?></td>
                            <td><?= h($item['base_item_id']) ?></td>
                            <td><?= h($item['parent_item_name'] ?? '-') ?></td>
                            <td class="action-buttons">
                                <a href="base_item_mst.php?edit_id=<?= h($item['item_id']) ?>" class="btn btn-edit">修正</a>
                                <form action="base_item_mst.php" method="post" onsubmit="return confirm('本当に削除しますか？');">
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
    <script>
        function updateCost(itemId) {
            const selectElement = document.getElementById('item_mst_id');
            const costInput = document.getElementById('cost');
            const selectedOption = selectElement.querySelector(`option[value="${itemId}"]`);
            if (selectedOption && selectedOption.dataset.cost) {
                costInput.value = selectedOption.dataset.cost;
            } else {
                costInput.value = '';
            }
        }
    </script>
</body>
</html>
