-- BASE API 実用的完全自動化システム用データベーステーブル
-- 不明な仕様に対応した堅牢なシステムのためのテーブル定義

-- システム設定テーブル（暗号化キーなど）
CREATE TABLE IF NOT EXISTS system_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    key_name VARCHAR(100) UNIQUE NOT NULL,
    value TEXT NOT NULL,
    created_at INT NOT NULL,
    updated_at INT NOT NULL
);

-- BASE APIトークンテーブル（暗号化対応）
CREATE TABLE IF NOT EXISTS base_api_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    scope_key VARCHAR(50) UNIQUE NOT NULL,
    access_token TEXT NOT NULL,
    refresh_token TEXT NOT NULL,
    access_expires INT NOT NULL,
    refresh_expires INT NOT NULL,
    created_at INT NOT NULL,
    updated_at INT NOT NULL,
    INDEX idx_scope_key (scope_key),
    INDEX idx_access_expires (access_expires),
    INDEX idx_refresh_expires (refresh_expires)
);

-- システムログテーブル
CREATE TABLE IF NOT EXISTS system_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_type VARCHAR(50) NOT NULL,
    message TEXT NOT NULL,
    created_at INT NOT NULL,
    INDEX idx_event_type (event_type),
    INDEX idx_created_at (created_at)
);

-- API制限管理テーブル
CREATE TABLE IF NOT EXISTS api_rate_limits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    scope_key VARCHAR(50) NOT NULL,
    limit_type ENUM('hour', 'day') NOT NULL,
    request_count INT NOT NULL DEFAULT 0,
    reset_time INT NOT NULL,
    created_at INT NOT NULL,
    updated_at INT NOT NULL,
    UNIQUE KEY unique_scope_limit (scope_key, limit_type),
    INDEX idx_reset_time (reset_time)
);

-- エラー統計テーブル
CREATE TABLE IF NOT EXISTS error_statistics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    error_type VARCHAR(100) NOT NULL,
    error_message TEXT NOT NULL,
    scope_key VARCHAR(50),
    http_code INT,
    occurred_at INT NOT NULL,
    INDEX idx_error_type (error_type),
    INDEX idx_scope_key (scope_key),
    INDEX idx_occurred_at (occurred_at)
);

-- 初期データの挿入
INSERT IGNORE INTO system_config (key_name, value, created_at, updated_at) 
VALUES ('encryption_key', '', UNIX_TIMESTAMP(), UNIX_TIMESTAMP());

-- インデックスの最適化
OPTIMIZE TABLE system_config;
OPTIMIZE TABLE base_api_tokens;
OPTIMIZE TABLE system_logs;
OPTIMIZE TABLE api_rate_limits;
OPTIMIZE TABLE error_statistics;
