-- Журнал взаиморасчётов: оплаты партнёров ISTOK
-- Долг партнёра = 10% от номинала активированных им купонов (начислено)
--                − сумма оплат из этого журнала.
CREATE TABLE IF NOT EXISTS partner_settlements (
    id BIGSERIAL PRIMARY KEY,
    partner_account_id INTEGER NOT NULL REFERENCES partner_accounts(id) ON DELETE CASCADE,
    period_month DATE NOT NULL,          -- первый день месяца (YYYY-MM-01)
    amount INTEGER NOT NULL,             -- оплачено, баллы
    comment TEXT DEFAULT '',
    created_by TEXT DEFAULT '',          -- кто внёс запись (WP admin)
    created_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_partner_settlements_account_month
    ON partner_settlements(partner_account_id, period_month);

ALTER TABLE partner_settlements ENABLE ROW LEVEL SECURITY;
DO $$ BEGIN
    CREATE POLICY "Allow full access" ON partner_settlements FOR ALL USING (true);
EXCEPTION WHEN duplicate_object THEN NULL; END $$;
