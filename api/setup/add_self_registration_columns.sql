-- キャストセルフ登録用のカラム追加
-- ワンタイムトークンと有効期限を保存

ALTER TABLE cast_mst 
ADD COLUMN registration_token VARCHAR(64) NULL COMMENT 'セルフ登録用ワンタイムトークン',
ADD COLUMN token_expires_at DATETIME NULL COMMENT 'トークン有効期限',
ADD COLUMN token_used_at DATETIME NULL COMMENT 'トークン使用日時';

-- インデックス追加（トークン検索の高速化）
CREATE INDEX idx_registration_token ON cast_mst(registration_token);
