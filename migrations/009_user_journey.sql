-- Таблица для отслеживания пути пользователя
-- partner_scan: пользователь отсканировал QR партнёра
-- coupon_buy: пользователь купил купон (приз)
-- coupon_redeem: пользователь использовал купон у партнёра

CREATE TABLE IF NOT EXISTS user_journey (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT REFERENCES users(id) ON DELETE CASCADE,
    action_type TEXT NOT NULL,
    partner_id INTEGER,
    related_id BIGINT,
    points_used INTEGER DEFAULT 0,
    partner_reward INTEGER DEFAULT 0,
    metadata JSONB DEFAULT '{}',
    created_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_user_journey_user_id ON user_journey(user_id);
CREATE INDEX IF NOT EXISTS idx_user_journey_action_type ON user_journey(action_type);
CREATE INDEX IF NOT EXISTS idx_user_journey_partner_id ON user_journey(partner_id);

ALTER TABLE user_journey ENABLE ROW LEVEL SECURITY;
CREATE POLICY "Allow full access" ON user_journey FOR ALL USING (true);
