-- Физический адрес партнёра — для карты в карточке купона (Яндекс.Карты).
-- Пустое значение — карта не показывается.

ALTER TABLE shop_categories ADD COLUMN IF NOT EXISTS address TEXT DEFAULT '';
