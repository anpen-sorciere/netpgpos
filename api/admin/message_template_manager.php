<?php
/**
 * 定型文管理画面（管理者用）
 */
session_start();
// TODO: 管理者認証を追加

require_once __DIR__ . '/../../../common/config.php';

$pdo = new PDO(
    "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
    $user,
    $password,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

// 保存処理
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'save') {
        $id = $_POST['id'] ?? null;
        $template_abbreviation = $_POST['template_abbreviation'] ?? '';
        $template_name = $_POST['template_name'] ?? '';
        $template_body = $_POST['template_body'] ?? '';
        $icon_class = $_POST['icon_class'] ?? 'fas fa-envelope';
        $display_order = $_POST['display_order'] ?? 0;
        $is_active = isset($_POST['is_active']) ? 1 : 0;
        $allow_cast_use = isset($_POST['allow_cast_use']) ? 1 : 0;
        
        if ($id) {
            // 更新
            $stmt = $pdo->prepare("
                UPDATE reply_message_templates 
                SET template_name = ?, template_abbreviation = ?, template_body = ?, icon_class = ?, 
                    display_order = ?, is_active = ?, allow_cast_use = ?
                WHERE id = ?
            ");
            $stmt->execute([$template_name, $template_abbreviation, $template_body, $icon_class, $display_order, $is_active, $allow_cast_use, $id]);
        } else {
            // 新規
            $stmt = $pdo->prepare("
                INSERT INTO reply_message_templates 
                (template_name, template_abbreviation, template_body, icon_class, display_order, is_active, allow_cast_use)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$template_name, $template_abbreviation, $template_body, $icon_class, $display_order, $is_active, $allow_cast_use]);
        }
        
        header('Location: message_template_manager.php?saved=1');
        exit;
    } elseif ($action === 'delete') {
        $id = $_POST['id'] ?? null;
        if ($id) {
            $stmt = $pdo->prepare("DELETE FROM reply_message_templates WHERE id = ?");
            $stmt->execute([$id]);
        }
        header('Location: message_template_manager.php?deleted=1');
        exit;
    }
}

// 一覧取得
$stmt = $pdo->query("
    SELECT * FROM reply_message_templates 
    ORDER BY display_order ASC, id ASC
");
$templates = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>定型文管理</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container my-5">
        <h2><i class="fas fa-envelope"></i> 返信メール定型文管理</h2>
        <p class="text-muted">キャストが選択する定型文を管理します。変数: {customer_name}, {product_name}, {order_id}, {cast_name}</p>
        
        <?php if (isset($_GET['saved'])): ?>
            <div class="alert alert-success">保存しました</div>
        <?php endif; ?>
        <?php if (isset($_GET['deleted'])): ?>
            <div class="alert alert-info">削除しました</div>
        <?php endif; ?>
        
        <button class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#editModal" onclick="editTemplate(null)">
            <i class="fas fa-plus"></i> 新規追加
        </button>
        
        <table class="table table-bordered">
            <thead class="table-light">
                <tr>
                    <th style="width: 5%">順序</th>
                    <th style="width: 20%">タイトル</th>
                    <th style="width: 10%">略称</th>
                    <th style="width: 35%">本文</th>
                    <th style="width: 10%">キャスト</th>
                    <th style="width: 10%">状態</th>
                    <th style="width: 10%">操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($templates as $tmpl): ?>
                    <tr>
                        <td><?= $tmpl['display_order'] ?></td>
                        <td>
                            <i class="<?= htmlspecialchars($tmpl['icon_class']) ?>"></i>
                            <?= htmlspecialchars($tmpl['template_name']) ?>
                        </td>
                        <td><span class="badge bg-info text-dark"><?= htmlspecialchars($tmpl['template_abbreviation'] ?? '-') ?></span></td>
                        <td style="font-size: 0.9em; white-space: pre-wrap;"><?= htmlspecialchars(mb_substr($tmpl['template_body'], 0, 100)) ?><?= mb_strlen($tmpl['template_body']) > 100 ? '...' : '' ?></td>
                        <td><?= $tmpl['allow_cast_use'] ? '<span class="badge bg-success">可</span>' : '<span class="badge bg-secondary">不可</span>' ?></td>
                        <td><?= $tmpl['is_active'] ? '<span class="badge bg-primary">有効</span>' : '<span class="badge bg-secondary">無効</span>' ?></td>
                        <td>
                            <button class="btn btn-sm btn-outline-primary" onclick='editTemplate(<?= json_encode($tmpl) ?>)'>編集</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    
    <!-- 編集モーダル -->
    <div class="modal fade" id="editModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="id" id="tmpl_id">
                    
                    <div class="modal-header">
                        <h5 class="modal-title" id="modalTitle">新規定型文</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">タイトル（キャスト選択時の表示名）</label>
                            <input type="text" class="form-control" name="template_name" id="tmpl_name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">略称（管理者識別用）</label>
                            <input type="text" class="form-control" name="template_abbreviation" id="tmpl_abbr" placeholder="例: 発送, A, 特急" required>
                            <small class="text-muted">承認画面で表示されます</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">本文（BASE送信用）</label>
                            <textarea class="form-control" name="template_body" id="tmpl_body" rows="8" required></textarea>
                            <small class="text-muted">変数: {customer_name}, {product_name}, {order_id}, {cast_name}</small>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">アイコン (Font Awesome)</label>
                                <input type="text" class="form-control" name="icon_class" id="tmpl_icon" placeholder="fas fa-envelope">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">表示順序</label>
                                <input type="number" class="form-control" name="display_order" id="tmpl_order" value="0">
                            </div>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" name="is_active" id="tmpl_active" checked>
                            <label class="form-check-label" for="tmpl_active">有効</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="allow_cast_use" id="tmpl_cast_use" checked>
                            <label class="form-check-label" for="tmpl_cast_use">キャスト使用許可</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">キャンセル</button>
                        <button type="submit" class="btn btn-primary">保存</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function editTemplate(data) {
        if (data) {
            document.getElementById('modalTitle').textContent = '定型文編集';
            document.getElementById('tmpl_id').value = data.id;
            document.getElementById('tmpl_name').value = data.template_name;
            document.getElementById('tmpl_abbr').value = data.template_abbreviation;
            document.getElementById('tmpl_body').value = data.template_body;
            document.getElementById('tmpl_icon').value = data.icon_class;
            document.getElementById('tmpl_order').value = data.display_order;
            document.getElementById('tmpl_active').checked = data.is_active == 1;
            document.getElementById('tmpl_cast_use').checked = data.allow_cast_use == 1;
        } else {
            document.getElementById('modalTitle').textContent = '新規定型文';
            document.getElementById('tmpl_id').value = '';
            document.getElementById('tmpl_name').value = '';
            document.getElementById('tmpl_abbr').value = '';
            document.getElementById('tmpl_body').value = '';
            document.getElementById('tmpl_icon').value = 'fas fa-envelope';
            document.getElementById('tmpl_order').value = '0';
            document.getElementById('tmpl_active').checked = true;
            document.getElementById('tmpl_cast_use').checked = true;
        }
        new bootstrap.Modal(document.getElementById('editModal')).show();
    }
    </script>
</body>
</html>
