-- Добавление полей логотипа и доп. информации для партнёров

ALTER TABLE shop_categories ADD COLUMN IF NOT EXISTS logo_url TEXT DEFAULT '';
ALTER TABLE shop_categories ADD COLUMN IF NOT EXISTS website TEXT DEFAULT '';
ALTER TABLE shop_categories ADD COLUMN IF NOT EXISTS telegram TEXT DEFAULT '';
ALTER TABLE shop_categories ADD COLUMN IF NOT EXISTS info TEXT DEFAULT '';
