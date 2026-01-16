-- cast_order_completionsテーブルの修正
-- message_typeをtemplate_idに変更し、template_nameカラムを追加

-- 古いカラム削除と新しいカラム追加
ALTER TABLE cast_order_completions 
  DROP COLUMN message_type,
  ADD COLUMN template_id INT NULL COMMENT '使用した定型文ID' AFTER cast_id,
  ADD COLUMN template_name VARCHAR(100) NULL COMMENT '使用した定型文タイトル' AFTER template_id;
