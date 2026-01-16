-- 定型文マスターテーブル

CREATE TABLE IF NOT EXISTS reply_message_templates (
    id INT PRIMARY KEY AUTO_INCREMENT,
    template_name VARCHAR(100) NOT NULL COMMENT '定型文タイトル（キャスト選択時の表示名）',
    template_body TEXT NOT NULL COMMENT '定型文本文（BASE送信用）',
    icon_class VARCHAR(50) DEFAULT 'fas fa-envelope' COMMENT 'Font Awesomeアイコンクラス',
    display_order INT DEFAULT 0 COMMENT '表示順序',
    is_active BOOLEAN DEFAULT TRUE COMMENT '有効フラグ',
    allow_cast_use BOOLEAN DEFAULT TRUE COMMENT 'キャスト使用可否（FALSE=管理者のみ）',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_display_order (display_order),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='返信メール定型文マスター';

-- 初期データ投入
INSERT INTO reply_message_templates (template_name, template_body, icon_class, display_order, allow_cast_use) VALUES
('DMで動画送付', 'DMで動画送付します。', 'fas fa-paper-plane', 1, TRUE),
('動画URLお知らせ', 'クラウドにアップした動画のURLをお知らせします。', 'fas fa-link', 2, TRUE),
('来店お待ち', '来店お待ちしてます。', 'fas fa-store', 3, TRUE),
('ライブ配信対応', 'ライブ配信で対応完了です。', 'fas fa-video', 4, TRUE),
('来店受け取り完成', '来店受け取り商品が完成しました。', 'fas fa-check-circle', 5, TRUE),
('商品郵送', '商品を郵送します。', 'fas fa-truck', 6, FALSE);

-- 定型文内で使用可能な変数の例
-- {customer_name} : お客様名
-- {product_name} : 商品名
-- {order_id} : 注文ID
-- {cast_name} : キャスト名
