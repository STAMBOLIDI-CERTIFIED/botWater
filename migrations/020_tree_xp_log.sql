-- Лог начислений опыта дерева — для блока «История опыта» в разделе «Дерево».
-- Пишется из add_tree_xp(); reason: scan | partner_scan | coupon_buy | coupon_redeem | level_bonus

CREATE TABLE IF NOT EXISTS tree_xp_log (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT REFERENCES users(id) ON DELETE CASCADE,
    xp INTEGER NOT NULL DEFAULT 0,
    reason TEXT NOT NULL DEFAULT '',
    partner_id INTEGER,
    ref_id BIGINT,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_tree_xp_log_user_id ON tree_xp_log(user_id);
CREATE INDEX IF NOT EXISTS idx_tree_xp_log_created_at ON tree_xp_log(created_at);

ALTER TABLE tree_xp_log ENABLE ROW LEVEL SECURITY;
DO $$ BEGIN
    CREATE POLICY "Allow full access" ON tree_xp_log FOR ALL USING (true);
EXCEPTION WHEN duplicate_object THEN NULL;
END $$;
