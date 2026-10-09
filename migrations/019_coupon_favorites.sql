-- Избранные купоны пользователя (раздел «Мои купоны»)
ALTER TABLE user_coupons ADD COLUMN IF NOT EXISTS is_favorite BOOLEAN NOT NULL DEFAULT FALSE;

CREATE INDEX IF NOT EXISTS idx_user_coupons_favorite ON user_coupons(user_id, is_favorite);
