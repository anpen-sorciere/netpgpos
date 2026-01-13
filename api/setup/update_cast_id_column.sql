-- base_order_itemsのcast_nameをcast_idに変更
-- 既存のcast_nameカラムを削除し、cast_idカラムを追加

-- 既存のインデックスを削除
ALTER TABLE `base_order_items`
DROP INDEX IF EXISTS `idx_cast_name`;

-- cast_nameカラムを削除（データが入っていても削除）
ALTER TABLE `base_order_items`
DROP COLUMN IF EXISTS `cast_name`;

-- cast_idカラムを追加
ALTER TABLE `base_order_items`
ADD COLUMN `cast_id` INT DEFAULT NULL COMMENT 'キャストID（cast_mst参照）',
ADD INDEX `idx_cast_id` (`cast_id`),
ADD CONSTRAINT `fk_base_order_items_cast` 
    FOREIGN KEY (`cast_id`) REFERENCES `cast_mst`(`cast_id`) 
    ON DELETE SET NULL 
    ON UPDATE CASCADE;
