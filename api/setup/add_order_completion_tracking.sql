-- キャスト対応完了履歴テーブル

CREATE TABLE IF NOT EXISTS cast_order_completions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    base_order_id VARCHAR(50) NOT NULL COMMENT '注文ID',
    cast_id INT NOT NULL COMMENT 'キャストID',
    completed_at DATETIME NOT NULL COMMENT '対応完了日時',
    message_type VARCHAR(10) NULL COMMENT 'メッセージタイプ(1-6)',
    reply_message TEXT NULL COMMENT '返信メッセージ',
    base_status_before VARCHAR(50) NULL COMMENT '変更前ステータス',
    base_status_after VARCHAR(50) NULL COMMENT '変更後ステータス',
    success BOOLEAN DEFAULT TRUE COMMENT '成功フラグ',
    error_message TEXT NULL COMMENT 'エラーメッセージ',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (cast_id) REFERENCES cast_mst(cast_id) ON DELETE CASCADE,
    INDEX idx_order_id (base_order_id),
    INDEX idx_cast_id (cast_id),
    INDEX idx_completed_at (completed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='キャスト対応完了履歴';
