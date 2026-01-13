-- cast_mstテーブルにログイン関連のカラムを追加
-- 実行前に必ずバックアップを取ってください

ALTER TABLE `cast_mst`
ADD COLUMN `email` varchar(100) DEFAULT NULL COMMENT 'ログイン用メールアドレス',
ADD COLUMN `password` varchar(255) DEFAULT NULL COMMENT 'パスワードハッシュ(bcrypt)',
ADD COLUMN `login_enabled` tinyint NOT NULL DEFAULT '0' COMMENT '0:ログイン無効 1:ログイン有効',
ADD COLUMN `last_login_at` datetime DEFAULT NULL COMMENT '最終ログイン日時',
ADD COLUMN `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '作成日時',
ADD COLUMN `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新日時',
ADD UNIQUE KEY `email` (`email`);
