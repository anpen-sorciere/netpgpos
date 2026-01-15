-- 重複データ削除とユニーク制約追加

-- STEP 1: 重複データの削除（IDが大きい方 = 新しい方を削除）
DELETE t1 FROM base_order_items t1
INNER JOIN base_order_items t2 
WHERE 
    t1.base_order_id = t2.base_order_id 
    AND t1.product_id = t2.product_id 
    AND t1.id > t2.id;

-- 確認: 削除後の件数チェック
SELECT 
    base_order_id, 
    product_id,
    COUNT(*) as count
FROM base_order_items
GROUP BY base_order_id, product_id
HAVING count > 1;

-- STEP 2: ユニーク制約の追加（再発防止）
ALTER TABLE base_order_items 
ADD UNIQUE KEY unique_order_product (base_order_id, product_id);

-- 確認: テーブル構造チェック
SHOW CREATE TABLE base_order_items;
