-- base_order_itemsのcast_nameをcast_idに変更
-- MySQL 5.7対応版（エラーを無視する方式）
-- 実行前に必ずバックアップを取ってください

-- 既存のインデックスを削除（存在しない場合はエラーになるが無視）
-- ALTER TABLE `base_order_items` DROP INDEX `idx_cast_name`;

-- cast_nameカラムを削除（存在しない場合はエラーになるが無視）
-- ALTER TABLE `base_order_items` DROP COLUMN `cast_name`;

-- cast_idカラムを追加
ALTER TABLE `base_order_items`
ADD COLUMN `cast_id` INT DEFAULT NULL COMMENT 'キャストID（cast_mst参照）';

-- インデックスを追加
ALTER TABLE `base_order_items`
ADD INDEX `idx_cast_id` (`cast_id`);

-- 外部キー制約を追加
ALTER TABLE `base_order_items`
ADD CONSTRAINT `fk_base_order_items_cast` 
    FOREIGN KEY (`cast_id`) REFERENCES `cast_mst`(`cast_id`) 
    ON DELETE SET NULL 
    ON UPDATE CASCADE;
