-- BASE API 完全自動化システム用データベーステーブル
-- このSQLを実行してテーブルを作成してください

-- トークン管理テーブル
CREATE TABLE IF NOT EXISTS base_api_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    scope_key VARCHAR(50) NOT NULL UNIQUE,
    access_token TEXT NOT NULL,
    refresh_token TEXT NOT NULL,
    access_expires INT NOT NULL,
    refresh_expires INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_scope (scope_key),
    INDEX idx_access_expires (access_expires),
    INDEX idx_refresh_expires (refresh_expires)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- レート制限管理テーブル
CREATE TABLE IF NOT EXISTS base_rate_limit (
    id INT PRIMARY KEY DEFAULT 1,
    requests_per_hour INT DEFAULT 1000,
    requests_per_minute INT DEFAULT 100,
    current_hour_requests INT DEFAULT 0,
    current_minute_requests INT DEFAULT 0,
    last_hour_reset INT DEFAULT 0,
    last_minute_reset INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- システムログテーブル
CREATE TABLE IF NOT EXISTS base_system_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    log_level ENUM('INFO', 'WARNING', 'ERROR', 'CRITICAL') NOT NULL,
    scope_key VARCHAR(50),
    message TEXT NOT NULL,
    details TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_log_level (log_level),
    INDEX idx_scope_key (scope_key),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 初期データ挿入
INSERT IGNORE INTO base_rate_limit (id, requests_per_hour, requests_per_minute) 
VALUES (1, 1000, 100);
