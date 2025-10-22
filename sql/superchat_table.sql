-- スーパーチャット管理テーブル
CREATE TABLE IF NOT EXISTS superchat_tbl (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cast_id INT NOT NULL COMMENT 'キャストID',
    donor_name VARCHAR(255) NOT NULL COMMENT '寄付者名',
    amount DECIMAL(10,2) NOT NULL COMMENT '金額',
    currency VARCHAR(10) NOT NULL DEFAULT 'JPY' COMMENT '通貨コード',
    received_date DATE NOT NULL COMMENT '受領日',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '作成日時',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新日時',
    INDEX idx_cast_id (cast_id),
    INDEX idx_received_date (received_date),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='スーパーチャット管理テーブル';
