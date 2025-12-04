<?php
// エラー表示を有効化
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
require_once __DIR__ . '/../common/config.php';
require_once __DIR__ . '/../common/dbconnect.php';
require_once __DIR__ . '/../common/functions.php';
require_once 'exchange_rate_service.php';

$uid = $_SESSION['user_id'] ?? null;
$utype = $_SESSION['utype'] ?? null;

if (!$utype) {
    echo "ユーザータイプ情報が無効です。";
    exit();
}

// データベース接続
$pdo = connect();

// 為替レートサービス初期化
$exchange_service = new ExchangeRateService();

// 店舗名取得
$shop_info = get_shop_info($utype);
$shop_name = $shop_info['name'];

// キャスト一覧取得（退職者も含む）
$casts = cast_get_all($pdo, true);

// 通貨リスト
$currencies = [
    'JPY' => '円',
    'USD' => 'ドル',
    'EUR' => 'ユーロ',
    'GBP' => 'ポンド',
    'AUD' => '豪ドル',
    'CAD' => 'カナダドル',
    'CHF' => 'スイスフラン',
    'CNY' => '人民元',
    'KRW' => 'ウォン',
    'THB' => 'バーツ',
    'TRY' => 'トルコリラ',
    'MIR' => 'ミラ',
    'PHP' => 'フィリピンペソ'
];

// 処理
$message = $_SESSION['superchat_message'] ?? '';
if (isset($_SESSION['superchat_message'])) {
    unset($_SESSION['superchat_message']);
}
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                // スーパーチャット追加
                $cast_ids = $_POST['cast_ids'] ?? [];
                // 空値を除外し、最大3人に制限
                $cast_ids = array_values(array_filter($cast_ids, function($v){ return $v !== '' && $v !== null; }));
                if (count($cast_ids) > 3) {
                    $cast_ids = array_slice($cast_ids, 0, 3);
                }
                $donor_name = trim($_POST['donor_name'] ?? '');
                $amount = $_POST['amount'] ?? '';
                $currency = $_POST['currency'] ?? 'JPY';
                $received_date = $_POST['received_date'] ?? '';
                
                if (!empty($cast_ids) && $amount && $received_date) {
                    try {
                        // キャスト数に応じて金額を分割
                        $cast_count = count($cast_ids);
                        $split_amount = floor($amount * 100 / $cast_count) / 100; // 小数点以下2桁で切り捨て
                        
                        $registered_count = 0;
                        $total_jpy_amount = 0;
                        
                        foreach ($cast_ids as $cast_id) {
                            // 為替レート取得と日本円換算
                            $conversion = $exchange_service->convertToJPY($split_amount, $currency, $received_date);
                            $jpy_amount = $conversion['jpy_amount'];
                            $exchange_rate = $conversion['rate'];
                            
                            $stmt = $pdo->prepare("INSERT INTO superchat_tbl (cast_id, donor_name, amount, currency, received_date, jpy_amount, exchange_rate) VALUES (?, ?, ?, ?, ?, ?, ?)");
                            $stmt->execute([$cast_id, $donor_name, $split_amount, $currency, $received_date, $jpy_amount, $exchange_rate]);
                            
                            $registered_count++;
                            $total_jpy_amount += $jpy_amount;
                        }
                        
                        $splitText = ($registered_count > 1) ? '分割して' : '';
                        $success_message = "スーパーチャットを{$registered_count}人に{$splitText}登録しました。（日本円換算合計: " . number_format($total_jpy_amount) . "円）";
                        
                        // 登録成功後、受領日の年月でリダイレクト
                        if ($received_date) {
                            $date_parts = explode('-', $received_date);
                            $redirect_year = $date_parts[0] ?? date('Y');
                            $redirect_month = $date_parts[1] ?? date('m');
                            $_SESSION['superchat_message'] = $success_message;
                            header('Location: superchat.php?utype=' . htmlspecialchars($utype) . '&year=' . $redirect_year . '&month=' . $redirect_month);
                            exit();
                        }
                    } catch (PDOException $e) {
                        $error = "登録に失敗しました: " . $e->getMessage();
                    }
                } else {
                    $error = "キャスト、金額、受領日を入力してください。";
                }
                break;
                
            case 'update':
                // スーパーチャット更新（編集時は単一キャストのみ）
                $id = $_POST['id'] ?? '';
                $cast_ids = $_POST['cast_ids'] ?? [];
                // 空値を除外し、1人目のみ採用
                $cast_ids = array_values(array_filter($cast_ids, function($v){ return $v !== '' && $v !== null; }));
                $donor_name = trim($_POST['donor_name'] ?? '');
                $amount = $_POST['amount'] ?? '';
                $currency = $_POST['currency'] ?? 'JPY';
                $received_date = $_POST['received_date'] ?? '';
                
                if ($id && !empty($cast_ids) && $amount && $received_date) {
                    try {
                        // 編集時は最初のキャストのみ使用（単一レコードの更新）
                        $cast_id = $cast_ids[0];
                        
                        // 為替レート取得と日本円換算
                        $conversion = $exchange_service->convertToJPY($amount, $currency, $received_date);
                        $jpy_amount = $conversion['jpy_amount'];
                        $exchange_rate = $conversion['rate'];
                        
                        $stmt = $pdo->prepare("UPDATE superchat_tbl SET cast_id = ?, donor_name = ?, amount = ?, currency = ?, received_date = ?, jpy_amount = ?, exchange_rate = ? WHERE id = ?");
                        $stmt->execute([$cast_id, $donor_name, $amount, $currency, $received_date, $jpy_amount, $exchange_rate, $id]);
                        $success_message = "スーパーチャットを更新しました。（日本円換算: " . number_format($jpy_amount) . "円）";
                        
                        // 更新成功後、受領日の年月でリダイレクト
                        if ($received_date) {
                            $date_parts = explode('-', $received_date);
                            $redirect_year = $date_parts[0] ?? date('Y');
                            $redirect_month = $date_parts[1] ?? date('m');
                            $_SESSION['superchat_message'] = $success_message;
                            header('Location: superchat.php?utype=' . htmlspecialchars($utype) . '&year=' . $redirect_year . '&month=' . $redirect_month);
                            exit();
                        }
                    } catch (PDOException $e) {
                        $error = "更新に失敗しました: " . $e->getMessage();
                    }
                } else {
                    $error = "キャスト、金額、受領日を入力してください。";
                }
                break;
                
            case 'delete':
                // スーパーチャット削除
                $id = $_POST['id'] ?? '';
                if ($id) {
                    try {
                        $stmt = $pdo->prepare("DELETE FROM superchat_tbl WHERE id = ?");
                        $stmt->execute([$id]);
                        $message = "スーパーチャットを削除しました。";
                    } catch (PDOException $e) {
                        $error = "削除に失敗しました: " . $e->getMessage();
                    }
                }
                break;
                
            case 'update_rates':
                // 既存データの為替レート更新
                try {
                    $stmt = $pdo->prepare("SELECT id, amount, currency, received_date FROM superchat_tbl WHERE currency != 'JPY' AND (jpy_amount IS NULL OR exchange_rate IS NULL)");
                    $stmt->execute();
                    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    $updated_count = 0;
                    foreach ($records as $record) {
                        $conversion = $exchange_service->convertToJPY($record['amount'], $record['currency'], $record['received_date']);
                        $jpy_amount = $conversion['jpy_amount'];
                        $exchange_rate = $conversion['rate'];
                        
                        $update_stmt = $pdo->prepare("UPDATE superchat_tbl SET jpy_amount = ?, exchange_rate = ? WHERE id = ?");
                        $update_stmt->execute([$jpy_amount, $exchange_rate, $record['id']]);
                        $updated_count++;
                    }
                    
                    $message = "為替レートを更新しました。（{$updated_count}件）";
                } catch (PDOException $e) {
                    $error = "為替レート更新に失敗しました: " . $e->getMessage();
                }
                break;
                
            case 'toggle_paid':
                // 支給済みフラグのトグル
                $id = $_POST['id'] ?? '';
                $is_paid = isset($_POST['is_paid']) ? (int)$_POST['is_paid'] : 0;
                
                if ($id) {
                    try {
                        $stmt = $pdo->prepare("UPDATE superchat_tbl SET is_paid = ? WHERE id = ?");
                        $stmt->execute([$is_paid, $id]);
                        $success_message = $is_paid ? "支給済みに更新しました。" : "未支給に更新しました。";
                        
                        // 現在表示中の年月でリダイレクト
                        $current_year = $_GET['year'] ?? date('Y');
                        $current_month = $_GET['month'] ?? date('m');
                        $_SESSION['superchat_message'] = $success_message;
                        header('Location: superchat.php?utype=' . htmlspecialchars($utype) . '&year=' . $current_year . '&month=' . $current_month);
                        exit();
                    } catch (PDOException $e) {
                        $error = "更新に失敗しました: " . $e->getMessage();
                    }
                }
                break;
        }
    }
}

// 表示する年月を取得
$display_year = $_GET['year'] ?? date('Y');
$display_month = $_GET['month'] ?? date('m');

// スーパーチャット一覧取得
$superchats = [];
try {
    $start_date = $display_year . '-' . str_pad($display_month, 2, '0', STR_PAD_LEFT) . '-01';
    $end_date = date('Y-m-t', strtotime($start_date));
    
    $stmt = $pdo->prepare("
        SELECT s.*, c.cast_name 
        FROM superchat_tbl s 
        LEFT JOIN cast_mst c ON s.cast_id = c.cast_id 
        WHERE s.received_date BETWEEN ? AND ? 
        ORDER BY s.received_date DESC, s.created_at DESC
    ");
    $stmt->execute([$start_date, $end_date]);
    $superchats = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = "データの取得に失敗しました: " . $e->getMessage();
}

// 編集用データ取得
$edit_data = null;
if (isset($_GET['edit'])) {
    $edit_id = $_GET['edit'];
    try {
        $stmt = $pdo->prepare("SELECT * FROM superchat_tbl WHERE id = ?");
        $stmt->execute([$edit_id]);
        $edit_data = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $error = "編集データの取得に失敗しました: " . $e->getMessage();
    }
}

// 受領日のデフォルト値を設定
$default_date = date('Y-m-d'); // 本日
if (isset($_POST['received_date'])) {
    // フォーム送信後は同じ日付を引き継ぐ
    $default_date = $_POST['received_date'];
} elseif ($edit_data) {
    // 編集時は編集データの日付を使用
    $default_date = $edit_data['received_date'];
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>スーパーチャット管理 - <?= htmlspecialchars($shop_name) ?></title>
    <link href="https://use.fontawesome.com/releases/v5.6.1/css/all.css" rel="stylesheet">
    <link href="https://unpkg.com/sanitize.css" rel="stylesheet"/>
    <link rel="stylesheet" href="css/style.css">
    <style>
        .superchat-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .form-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
        }
        
        .form-row {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
            align-items: center;
        }
        
        .form-group {
            flex: 1;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        
        .form-group input, .form-group select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }
        
        .btn-primary {
            background: #007bff;
            color: white;
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-warning {
            background: #ffc107;
            color: black;
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
        }
        
        .btn:hover {
            opacity: 0.8;
        }
        
        .list-section {
            background: white;
            border-radius: 8px;
            padding: 20px;
        }
        
        .month-selector {
            margin-bottom: 20px;
            padding: 15px;
            background: #e9ecef;
            border-radius: 4px;
        }
        
        .month-selector select {
            padding: 8px;
            margin-right: 10px;
        }
        
        .superchat-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .superchat-table th,
        .superchat-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        
        .superchat-table th {
            background: #f8f9fa;
            font-weight: bold;
        }
        
        .superchat-table tr:hover {
            background: #f5f5f5;
        }
        
        .message {
            padding: 10px;
            margin: 10px 0;
            border-radius: 4px;
        }
        
        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .amount-cell {
            text-align: right;
            font-weight: bold;
        }
        
        .actions {
            white-space: nowrap;
        }
        
        .actions a {
            margin-right: 5px;
        }
    </style>
</head>
<body>
    <div class="superchat-container">
        <h1><i class="fas fa-heart"></i> スーパーチャット管理 - <?= htmlspecialchars($shop_name) ?></h1>
        
        <a href="index.php?utype=<?= htmlspecialchars($utype) ?>" class="btn btn-primary">
            <i class="fas fa-arrow-left"></i> メニューに戻る
        </a>
        
        <?php if ($message): ?>
            <div class="message success"><?= htmlspecialchars($message) ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="message error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <!-- スーパーチャット入力フォーム -->
        <div class="form-section">
            <h2>
                <i class="fas fa-plus"></i> 
                <?= $edit_data ? 'スーパーチャット編集' : 'スーパーチャット登録' ?>
            </h2>
            
            <form method="POST">
                <input type="hidden" name="action" value="<?= $edit_data ? 'update' : 'add' ?>">
                <?php if ($edit_data): ?>
                    <input type="hidden" name="id" value="<?= htmlspecialchars($edit_data['id']) ?>">
                <?php endif; ?>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>キャスト（最大3人まで選択可能）</label>
                        <div style="display: grid; grid-template-columns: 1fr; gap: 6px;">
                            <select name="cast_ids[]" id="cast_id_1" style="width: 100%;">
                                <option value="">未選択</option>
                                <?php foreach ($casts as $cast): ?>
                                    <option value="<?= htmlspecialchars($cast['cast_id']) ?>" 
                                        <?= ($edit_data && $edit_data['cast_id'] == $cast['cast_id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($cast['cast_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <select name="cast_ids[]" id="cast_id_2" style="width: 100%;">
                                <option value="">未選択</option>
                                <?php foreach ($casts as $cast): ?>
                                    <option value="<?= htmlspecialchars($cast['cast_id']) ?>">
                                        <?= htmlspecialchars($cast['cast_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <select name="cast_ids[]" id="cast_id_3" style="width: 100%;">
                                <option value="">未選択</option>
                                <?php foreach ($casts as $cast): ?>
                                    <option value="<?= htmlspecialchars($cast['cast_id']) ?>">
                                        <?= htmlspecialchars($cast['cast_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <small style="color: #666;">未選択の欄は空のままで大丈夫です</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="donor_name">寄付者名</label>
                        <input type="text" name="donor_name" id="donor_name" 
                               value="<?= htmlspecialchars($edit_data['donor_name'] ?? '') ?>" 
                               placeholder="匿名の場合は空欄でOK">
                    </div>
                    
                    <div class="form-group">
                        <label for="received_date">受領日</label>
                        <div style="display: flex; gap: 5px;">
                            <input type="date" name="received_date" id="received_date" 
                                   value="<?= htmlspecialchars($default_date) ?>" required style="flex: 1;">
                            <button type="button" onclick="setToday()" class="btn btn-primary" style="padding: 8px 12px;">
                                <i class="fas fa-calendar-day"></i> 本日
                            </button>
                        </div>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="amount">金額</label>
                        <input type="number" name="amount" id="amount" step="0.01" min="0"
                               value="<?= htmlspecialchars($edit_data['amount'] ?? '') ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="currency">通貨</label>
                        <select name="currency" id="currency" required>
                            <?php foreach ($currencies as $code => $name): ?>
                                <option value="<?= htmlspecialchars($code) ?>" 
                                    <?= ($edit_data && $edit_data['currency'] == $code) ? 'selected' : ($code == 'JPY' ? 'selected' : '') ?>>
                                    <?= htmlspecialchars($name) ?> (<?= htmlspecialchars($code) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>&nbsp;</label>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-save"></i> 
                            <?= $edit_data ? '更新' : '登録' ?>
                        </button>
                        <?php if ($edit_data): ?>
                            <a href="superchat.php?utype=<?= htmlspecialchars($utype) ?>&year=<?= htmlspecialchars($display_year) ?>&month=<?= htmlspecialchars($display_month) ?>" 
                               class="btn btn-primary">
                                <i class="fas fa-times"></i> キャンセル
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>
        
        <!-- 月別一覧表示 -->
        <div class="list-section">
            <h2><i class="fas fa-list"></i> スーパーチャット一覧</h2>
            
            <div class="month-selector">
                <form method="GET" style="display: inline;">
                    <input type="hidden" name="utype" value="<?= htmlspecialchars($utype) ?>">
                    <label for="year">年:</label>
                    <select name="year" id="year" onchange="this.form.submit()">
                        <?php for ($year = date('Y') - 2; $year <= date('Y') + 1; $year++): ?>
                            <option value="<?= $year ?>" <?= ($display_year == $year) ? 'selected' : '' ?>>
                                <?= $year ?>年
                            </option>
                        <?php endfor; ?>
                    </select>
                    
                    <label for="month">月:</label>
                    <select name="month" id="month" onchange="this.form.submit()">
                        <?php for ($month = 1; $month <= 12; $month++): ?>
                            <option value="<?= $month ?>" <?= ($display_month == $month) ? 'selected' : '' ?>>
                                <?= $month ?>月
                            </option>
                        <?php endfor; ?>
                    </select>
                </form>
            </div>
            
            <div style="margin-bottom: 20px;">
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="update_rates">
                    <button type="submit" class="btn btn-primary" onclick="return confirm('既存データの為替レートを更新しますか？')">
                        <i class="fas fa-sync"></i> 為替レート更新
                    </button>
                </form>
            </div>
            
            <?php if (empty($superchats)): ?>
                <p>該当月のスーパーチャットはありません。</p>
            <?php else: ?>
                <table class="superchat-table">
                    <thead>
                        <tr>
                            <th>受領日</th>
                            <th>キャスト</th>
                            <th>寄付者名</th>
                            <th>金額</th>
                            <th>通貨</th>
                            <th>日本円換算</th>
                            <th>為替レート</th>
                            <th>支給済み</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($superchats as $sc): ?>
                            <tr>
                                <td><?= htmlspecialchars($sc['received_date']) ?></td>
                                <td><?= htmlspecialchars($sc['cast_name'] ?? '不明') ?></td>
                                <td><?= htmlspecialchars($sc['donor_name'] ?: '匿名') ?></td>
                                <td class="amount-cell"><?= number_format($sc['amount'], 2) ?></td>
                                <td><?= htmlspecialchars($sc['currency']) ?></td>
                                <td class="amount-cell">
                                    <?php if ($sc['jpy_amount'] !== null): ?>
                                        <?= number_format($sc['jpy_amount'], 0) ?>円
                                    <?php else: ?>
                                        <span style="color: #999;">未換算</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($sc['exchange_rate'] !== null && $sc['currency'] !== 'JPY'): ?>
                                        <?= number_format($sc['exchange_rate'], 4) ?>
                                    <?php elseif ($sc['currency'] === 'JPY'): ?>
                                        -
                                    <?php else: ?>
                                        <span style="color: #999;">-</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: center;">
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('<?= ($sc['is_paid'] ? '未支給' : '支給済み') ?>に変更しますか？')">
                                        <input type="hidden" name="action" value="toggle_paid">
                                        <input type="hidden" name="id" value="<?= htmlspecialchars($sc['id']) ?>">
                                        <input type="hidden" name="is_paid" value="<?= $sc['is_paid'] ? 0 : 1 ?>">
                                        <button type="submit" class="btn <?= $sc['is_paid'] ? 'btn-success' : 'btn-warning' ?>" style="padding: 5px 10px; font-size: 0.9em;">
                                            <?= $sc['is_paid'] ? '✓ 支給済み' : '未支給' ?>
                                        </button>
                                    </form>
                                </td>
                                <td class="actions">
                                    <a href="superchat.php?utype=<?= htmlspecialchars($utype) ?>&year=<?= htmlspecialchars($display_year) ?>&month=<?= htmlspecialchars($display_month) ?>&edit=<?= htmlspecialchars($sc['id']) ?>" 
                                       class="btn btn-warning">
                                        <i class="fas fa-edit"></i> 編集
                                    </a>
                                    <form method="POST" style="display: inline;" 
                                          onsubmit="return confirm('本当に削除しますか？')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?= htmlspecialchars($sc['id']) ?>">
                                        <button type="submit" class="btn btn-danger">
                                            <i class="fas fa-trash"></i> 削除
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
    <script>
        function setToday() {
            const today = new Date();
            const year = today.getFullYear();
            const month = String(today.getMonth() + 1).padStart(2, '0');
            const day = String(today.getDate()).padStart(2, '0');
            const todayString = year + '-' + month + '-' + day;
            document.getElementById('received_date').value = todayString;
        }
        
        // キャスト選択の制限（最大3人まで）
        document.getElementById('cast_ids').addEventListener('change', function() {
            if (this.selectedOptions.length > 3) {
                alert('最大3人まで選択できます。');
                // 最後に選択したものを解除
                this.selectedOptions[this.selectedOptions.length - 1].selected = false;
            }
        });
    </script>
</body>
</html>
