CREATE TABLE IF NOT EXISTS user_coupons (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT REFERENCES users(id) ON DELETE CASCADE,
    prize_id INTEGER REFERENCES prizes(id) ON DELETE SET NULL,
    order_id BIGINT REFERENCES orders(id) ON DELETE SET NULL,
    status TEXT DEFAULT 'active',
    qr_code TEXT UNIQUE NOT NULL,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    used_at TIMESTAMPTZ,
    used_by_partner_id INTEGER REFERENCES partner_accounts(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_user_coupons_user_id ON user_coupons(user_id);
CREATE INDEX IF NOT EXISTS idx_user_coupons_status ON user_coupons(status);
CREATE INDEX IF NOT EXISTS idx_user_coupons_qr_code ON user_coupons(qr_code);
CREATE INDEX IF NOT EXISTS idx_user_coupons_order_id ON user_coupons(order_id);

ALTER TABLE user_coupons ENABLE ROW LEVEL SECURITY;
CREATE POLICY "Allow full access" ON user_coupons FOR ALL USING (true);
