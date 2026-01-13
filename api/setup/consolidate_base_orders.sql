-- BASE注文テーブル統合: 既存テーブルにカラム追加
-- 実行前に必ずバックアップを取ってください

-- base_ordersにサプライズ・決済関連カラムを追加
ALTER TABLE `base_orders`
ADD COLUMN IF NOT EXISTS `is_surprise` TINYINT(1) DEFAULT 0 COMMENT 'サプライズ注文フラグ',
ADD COLUMN IF NOT EXISTS `surprise_date` DATE DEFAULT NULL COMMENT 'サプライズ日付',
ADD COLUMN IF NOT EXISTS `payment_method` VARCHAR(50) DEFAULT NULL COMMENT '決済方法',
ADD COLUMN IF NOT EXISTS `dispatch_status_detail` VARCHAR(50) DEFAULT NULL COMMENT '配送ステータス詳細';

-- インデックス追加（存在チェックはMySQL 5.7以降でサポート）
ALTER TABLE `base_orders`
ADD INDEX IF NOT EXISTS `idx_is_surprise` (`is_surprise`),
ADD INDEX IF NOT EXISTS `idx_surprise_date` (`surprise_date`);

-- base_order_itemsにキャスト・顧客名・サプライズ関連カラムを追加
ALTER TABLE `base_order_items`
ADD COLUMN IF NOT EXISTS `cast_name` VARCHAR(100) DEFAULT NULL COMMENT 'キャスト名',
ADD COLUMN IF NOT EXISTS `customer_name_from_option` VARCHAR(100) DEFAULT NULL COMMENT 'オプションから抽出した顧客名',
ADD COLUMN IF NOT EXISTS `item_surprise_date` DATE DEFAULT NULL COMMENT 'アイテム別サプライズ日付';

-- インデックス追加
ALTER TABLE `base_order_items`
ADD INDEX IF NOT EXISTS `idx_cast_name` (`cast_name`),
ADD INDEX IF NOT EXISTS `idx_item_surprise_date` (`item_surprise_date`);
