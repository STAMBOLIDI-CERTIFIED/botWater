-- Добавление QR-кода и количества баллов за сканирование для партнёров
ALTER TABLE shop_categories ADD COLUMN IF NOT EXISTS qr_code TEXT DEFAULT '';
ALTER TABLE shop_categories ADD COLUMN IF NOT EXISTS scan_points INTEGER DEFAULT 10;

-- Уникальный индекс на qr_code (только для непустых значений)
CREATE UNIQUE INDEX IF NOT EXISTS idx_shop_categories_qr_code ON shop_categories(qr_code) WHERE qr_code != '';

-- Генерация уникальных QR-кодов для существующих партнёров
UPDATE shop_categories SET qr_code = 'partner_' || id, scan_points = 10
WHERE subtitle ILIKE '%партнёр%' AND qr_code = '';
