<?php
// 特定キャストの対応ステータス修復スクリプト
// 誤って「対応済み」にしてしまったデータを「未対応」に戻します

require_once __DIR__ . '/../../common/config.php';
require_once __DIR__ . '/../../common/dbconnect.php';

session_start();

// ■設定: 修復対象のキャストID
$target_cast_id = 38; // ウブさん

echo "<h1>Repair Cast Status Tool</h1>";
echo "Target Cast ID: <strong>{$target_cast_id}</strong><br><br>";

// 実行ボタンが押された場合のみ処理
if (isset($_POST['run_repair'])) {
    try {
        $pdo = new PDO(
            "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
            $user,
            $password,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );

        // まず件数確認
        $stmtBefore = $pdo->prepare("SELECT COUNT(*) FROM base_order_items WHERE cast_id = ? AND cast_handled = 1");
        $stmtBefore->execute([$target_cast_id]);
        $count = $stmtBefore->fetchColumn();

        if ($count > 0) {
            // 更新実行
            $sql = "UPDATE base_order_items 
                    SET cast_handled = 0, cast_handled_at = NULL, cast_handled_template_id = NULL 
                    WHERE cast_id = ? AND cast_handled = 1";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$target_cast_id]);
            
            echo "<h3 style='color:green'>Success!</h3>";
            echo "<p>{$count} 件のデータを「未対応」にリセットしました。</p>";
        } else {
            echo "<p>修復が必要なデータ（対応済みデータ）は見つかりませんでした。</p>";
        }

    } catch (PDOException $e) {
        echo "<h3 style='color:red'>Error:</h3>" . $e->getMessage();
    }
} else {
    // 確認画面
    ?>
    <form method="post" onsubmit="return confirm('本当にこのキャストの全対応済みデータをリセットしますか？');">
        <p>キャストID: <?= $target_cast_id ?> の「対応済み」データを全て「未対応」に戻します。</p>
        <button type="submit" name="run_repair" style="padding:10px 20px; background:red; color:white; font-weight:bold;">
            修復を実行する
        </button>
    </form>
    <?php
}
?>
