-- スーパーチャット管理テーブル
CREATE TABLE IF NOT EXISTS superchat_tbl (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cast_id INT NOT NULL COMMENT 'キャストID',
    donor_name VARCHAR(255) NOT NULL COMMENT '寄付者名',
    amount DECIMAL(10,2) NOT NULL COMMENT '金額',
    currency VARCHAR(10) NOT NULL DEFAULT 'JPY' COMMENT '通貨コード',
    received_date DATE NOT NULL COMMENT '受領日',
    jpy_amount DECIMAL(10,2) DEFAULT NULL COMMENT '日本円換算金額',
    exchange_rate DECIMAL(10,4) DEFAULT NULL COMMENT '為替レート',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '作成日時',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新日時',
    INDEX idx_cast_id (cast_id),
    INDEX idx_received_date (received_date),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='スーパーチャット管理テーブル';

-- 既存テーブルにカラムを追加（テーブルが既に存在する場合）
-- MySQL 8.0.19以降では IF NOT EXISTS がサポートされています

-- 日本円換算金額カラムを追加（既に存在する場合はスキップ）
ALTER TABLE superchat_tbl 
ADD COLUMN IF NOT EXISTS jpy_amount DECIMAL(10,2) DEFAULT NULL COMMENT '日本円換算金額';

-- 為替レートカラムを追加（既に存在する場合はスキップ）
ALTER TABLE superchat_tbl 
ADD COLUMN IF NOT EXISTS exchange_rate DECIMAL(10,4) DEFAULT NULL COMMENT '為替レート';

-- 支給済みフラグカラムを追加（既に存在する場合はスキップ）
ALTER TABLE superchat_tbl 
ADD COLUMN IF NOT EXISTS is_paid TINYINT(1) NOT NULL DEFAULT 0 COMMENT '支給済みフラグ';
