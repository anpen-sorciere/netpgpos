<?php
/**
 * BASE API連携 複数店舗対応 マイグレーションスクリプト
 * 
 * 1. shop_mst テーブルの拡張
 * 2. base_api_tokens テーブルの構造変更
 * 3. base_orders テーブルの構造変更
 * 4. 既存データの移行 (shop_id = 1)
 */

// 共通設定の読み込み (htdocs/common/config.php を想定)
if (file_exists(__DIR__ . '/../../../common/config.php')) {
    require_once __DIR__ . '/../../../common/config.php';
} elseif (file_exists(__DIR__ . '/../../common/config.php')) {
    require_once __DIR__ . '/../../common/config.php';
} else {
    // netpgpos/common/config.php の可能性（念のため）
    require_once __DIR__ . '/../../common/config.php';
}

require_once __DIR__ . '/../../../common/dbconnect.php';

// スクリプト実行の安全確保
if (php_sapi_name() !== 'cli' && !isset($_GET['run'])) {
    echo "<h1>DB Migration Tool</h1>";
    echo "<p>このスクリプトはデータベース構造を変更します。バックアップを取ってから実行してください。</p>";
    echo "<form method='get'><button type='submit' name='run' value='1' style='padding:10px 20px; font-size:1.2em;'>マイグレーションを実行する</button></form>";
    exit;
}

echo "<h2>Migration process started...</h2>";
echo "<pre>";

try {
    $pdo = new PDO(
        "mysql:host={$host};dbname={$dbname};charset=utf8mb4",
        $user,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // ---------------------------------------------------------
    // 1. shop_mst テーブルの拡張
    // ---------------------------------------------------------
    echo "Checking shop_mst table...\n";
    
    // カラム存在チェック
    $stmt = $pdo->query("SHOW COLUMNS FROM shop_mst LIKE 'base_client_id'");
    if (!$stmt->fetch()) {
        echo "Adding BASE API columns to shop_mst...\n";
        $sql = "ALTER TABLE shop_mst 
                ADD COLUMN base_client_id VARCHAR(255) NULL DEFAULT NULL,
                ADD COLUMN base_client_secret VARCHAR(255) NULL DEFAULT NULL,
                ADD COLUMN base_redirect_uri VARCHAR(255) NULL DEFAULT NULL,
                ADD COLUMN base_is_active TINYINT DEFAULT 0,
                ADD COLUMN base_token_status VARCHAR(50) DEFAULT 'unconfigured'";
        $pdo->exec($sql);
        echo "Done.\n";
    } else {
        echo "shop_mst already has BASE columns. Skipping.\n";
    }

    // ---------------------------------------------------------
    // 2. base_api_tokens テーブルの改修
    // ---------------------------------------------------------
    echo "Checking base_api_tokens table...\n";
    
    $stmt = $pdo->query("SHOW COLUMNS FROM base_api_tokens LIKE 'shop_id'");
    if (!$stmt->fetch()) {
        echo "Adding shop_id to base_api_tokens...\n";
        
        // shop_id追加 (INT)
        $pdo->exec("ALTER TABLE base_api_tokens ADD COLUMN shop_id INT NOT NULL DEFAULT 1 FIRST");
        
        // 主キー変更 (scope_key -> shop_id, scope_key)
        // まず既存のPKを削除するが、PKの名前が不明な場合もあるため、DROP PRIMARY KEYを試みる
        try {
            $pdo->exec("ALTER TABLE base_api_tokens DROP PRIMARY KEY");
        } catch (Exception $e) {
            echo "Notice: Could not drop primary key (might not exist or different name). " . $e->getMessage() . "\n";
        }
        
        $pdo->exec("ALTER TABLE base_api_tokens ADD PRIMARY KEY (shop_id, scope_key)");
        echo "Done. PK is now (shop_id, scope_key).\n";
    } else {
        echo "base_api_tokens already has shop_id. Skipping.\n";
    }

    // ---------------------------------------------------------
    // 3. base_orders テーブルの改修
    // ---------------------------------------------------------
    echo "Checking base_orders table...\n";
    
    $stmt = $pdo->query("SHOW COLUMNS FROM base_orders LIKE 'shop_id'");
    if (!$stmt->fetch()) {
        echo "Adding shop_id to base_orders...\n";
        
        $pdo->exec("ALTER TABLE base_orders ADD COLUMN shop_id INT NOT NULL DEFAULT 1 AFTER base_order_id");
        $pdo->exec("CREATE INDEX idx_shop_id ON base_orders(shop_id)");
        
        // 複合インデックスなども考慮できるが、まずはシンプルに
        echo "Done.\n";
    } else {
        echo "base_orders already has shop_id. Skipping.\n";
    }

    // ---------------------------------------------------------
    // 4. データ移行 (config.php -> DB)
    // ---------------------------------------------------------
    echo "Migrating config data to DB (shop_id=1)...\n";
    
    global $base_client_id, $base_client_secret, $base_redirect_uri;
    
    if (!empty($base_client_id)) {
        // shop_id=1 が存在するか確認
        $stmt = $pdo->query("SELECT shop_id FROM shop_mst WHERE shop_id = 1");
        if ($stmt->fetch()) {
            // 更新
            $sql = "UPDATE shop_mst SET 
                    base_client_id = ?,
                    base_client_secret = ?,
                    base_redirect_uri = ?,
                    base_is_active = 1,
                    base_token_status = 'active'
                    WHERE shop_id = 1";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$base_client_id, $base_client_secret, $base_redirect_uri]);
            echo "Updated shop_id=1 with config.php values.\n";
        } else {
            echo "Warning: shop_id=1 does not exist in shop_mst. Attempting to insert...\n";
            $sql = "INSERT INTO shop_mst (shop_id, shop_name, base_client_id, base_client_secret, base_redirect_uri, base_is_active, base_token_status)
                    VALUES (1, 'Main Shop', ?, ?, ?, 1, 'active')";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$base_client_id, $base_client_secret, $base_redirect_uri]);
            echo "Inserted shop_id=1.\n";
        }
    } else {
        echo "Skipping config migration: base_client_id is empty or not set in global scope.\n";
    }

    echo "\n---------------------------------------------------------\n";
    echo "Migration Completed Successfully!\n";
    echo "---------------------------------------------------------\n";

} catch (PDOException $e) {
    echo "Database Error: " . $e->getMessage() . "\n";
    exit;
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit;
}

echo "</pre>";
echo '<br><a href="../cast/admin_cast_manager_v2.php">管理画面へ戻る</a>';
?>
