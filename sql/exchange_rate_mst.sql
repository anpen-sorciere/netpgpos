-- 為替レートマスターテーブル
CREATE TABLE IF NOT EXISTS exchange_rate_mst (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rate_date DATE NOT NULL COMMENT '為替レートの日付',
    from_currency VARCHAR(10) NOT NULL COMMENT '変換元通貨コード',
    to_currency VARCHAR(10) NOT NULL DEFAULT 'JPY' COMMENT '変換先通貨コード',
    rate DECIMAL(10,4) NOT NULL COMMENT '為替レート',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '作成日時',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新日時',
    UNIQUE KEY uk_rate_date_currency (rate_date, from_currency, to_currency),
    INDEX idx_rate_date (rate_date),
    INDEX idx_from_currency (from_currency)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='為替レートマスターテーブル';

