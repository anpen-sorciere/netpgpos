-- キャスト専用ポータル用データベーステーブル定義

-- キャスト管理テーブル
CREATE TABLE IF NOT EXISTS casts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE, -- ログインID
    password_hash VARCHAR(255) NOT NULL, -- パスワード（ハッシュ化）
    display_name VARCHAR(100) NOT NULL, -- 表示名（注文オプションの「キャスト名」と一致させる）
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_display_name (display_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 注文データ蓄積テーブル（Monitorから同期）
CREATE TABLE IF NOT EXISTS orders (
    order_id VARCHAR(50) PRIMARY KEY, -- BASEの注文ID (unique_key)
    ordered_at DATETIME NOT NULL,
    customer_name VARCHAR(100), -- 連結した氏名
    total_price INT,
    payment_method VARCHAR(50),
    dispatch_status VARCHAR(50),
    is_surprise TINYINT(1) DEFAULT 0,
    surprise_date DATE, -- サプライズ設定日
    last_synced_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_ordered_at (ordered_at),
    INDEX idx_is_surprise (is_surprise)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 注文詳細データ蓄積テーブル
CREATE TABLE IF NOT EXISTS order_items (
    id INT AUTO_INCREMENT PRIMARY KEY, -- 内部ID
    base_item_id VARCHAR(50), -- BASEの商品ID (item_id)
    order_id VARCHAR(50) NOT NULL,
    title VARCHAR(255),
    price INT,
    quantity INT,
    customer_name VARCHAR(100), -- オプションから抽出したお客様名
    cast_name VARCHAR(100), -- オプションから抽出したキャスト名
    item_surprise_date DATE, -- 商品ごとのサプライズ日付
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_item (order_id, base_item_id, cast_name), -- 同じ商品の同じキャスト指定は重複させない（オプション違いの考慮が必要だが一旦これで）
    FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE CASCADE,
    INDEX idx_cast_name (cast_name),
    INDEX idx_order_id (order_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
