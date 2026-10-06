-- Подарок от любимого партнёра.
-- Опыт хранится: общий (users.tree_xp) + отдельно по каждому партнёру (user_partner_xp).
-- Любимый партнёр = больше всего подтверждённых оплаченных заказов, при равенстве — последняя покупка.
-- Подарок выдаётся один раз за порог конкретного партнёра.

-- ─── Опыт по каждому партнёру ─────────────────────────
CREATE TABLE IF NOT EXISTS user_partner_xp (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT REFERENCES users(id) ON DELETE CASCADE,
    category_id INTEGER REFERENCES shop_categories(id) ON DELETE CASCADE,
    xp INTEGER NOT NULL DEFAULT 0,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW(),
    UNIQUE (user_id, category_id)
);

CREATE INDEX IF NOT EXISTS idx_user_partner_xp_user_id ON user_partner_xp(user_id);

ALTER TABLE user_partner_xp ENABLE ROW LEVEL SECURITY;
DO $$ BEGIN
    CREATE POLICY "Allow full access" ON user_partner_xp FOR ALL USING (true);
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;

-- ─── Выданные подарки ─────────────────────────────────
CREATE TABLE IF NOT EXISTS user_gifts (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT REFERENCES users(id) ON DELETE CASCADE,
    category_id INTEGER REFERENCES shop_categories(id) ON DELETE SET NULL,
    prize_id INTEGER REFERENCES prizes(id) ON DELETE SET NULL,
    coupon_id BIGINT REFERENCES user_coupons(id) ON DELETE SET NULL,
    threshold_xp INTEGER NOT NULL DEFAULT 0,
    partner_xp INTEGER NOT NULL DEFAULT 0,
    status TEXT NOT NULL DEFAULT 'claimed',   -- claimed | expired | revoked
    expires_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_user_gifts_user_id ON user_gifts(user_id);
CREATE INDEX IF NOT EXISTS idx_user_gifts_category_id ON user_gifts(category_id);
CREATE INDEX IF NOT EXISTS idx_user_gifts_status ON user_gifts(status);

ALTER TABLE user_gifts ENABLE ROW LEVEL SECURITY;
DO $$ BEGIN
    CREATE POLICY "Allow full access" ON user_gifts FOR ALL USING (true);
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;

-- ─── Настройки подарка у партнёра ─────────────────────
-- gift_threshold_xp = 0 → подарки этого партнёра выключены
ALTER TABLE shop_categories ADD COLUMN IF NOT EXISTS gift_threshold_xp INTEGER NOT NULL DEFAULT 0;
-- gift_limit = 0 → без ограничения количества выдач
ALTER TABLE shop_categories ADD COLUMN IF NOT EXISTS gift_limit INTEGER NOT NULL DEFAULT 0;

-- ─── Поля купона ──────────────────────────────────────
-- is_gift: подарочный купон, опыт за него не начисляется (0 XP)
ALTER TABLE user_coupons ADD COLUMN IF NOT EXISTS is_gift BOOLEAN NOT NULL DEFAULT FALSE;
-- expires_at: срок действия подарочного купона
ALTER TABLE user_coupons ADD COLUMN IF NOT EXISTS expires_at TIMESTAMPTZ;
-- xp_granted: сколько опыта уже начислено по купону; NULL = ещё не обработан
ALTER TABLE user_coupons ADD COLUMN IF NOT EXISTS xp_granted INTEGER;
-- xp_category_id: партнёр (категория), которому начислен опыт по купону
ALTER TABLE user_coupons ADD COLUMN IF NOT EXISTS xp_category_id INTEGER;
-- xp_revoked: опыт по купону отозван (заказ отменён) и может быть возвращён
ALTER TABLE user_coupons ADD COLUMN IF NOT EXISTS xp_revoked BOOLEAN NOT NULL DEFAULT FALSE;

-- ─── Настройки подарка (глобальные) ──────────────────
-- gift_prize_id = 0 → приз-подарок ещё не выбран, выдача отключена
INSERT INTO settings (key, value) VALUES ('gift_prize_id', '0'), ('gift_ttl_days', '30')
ON CONFLICT (key) DO NOTHING;
