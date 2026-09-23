CREATE TABLE IF NOT EXISTS partner_accounts (
    id SERIAL PRIMARY KEY,
    telegram_id BIGINT UNIQUE NOT NULL,
    name TEXT DEFAULT '',
    category_id INTEGER REFERENCES shop_categories(id) ON DELETE SET NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_partner_accounts_telegram_id ON partner_accounts(telegram_id);

ALTER TABLE partner_accounts ENABLE ROW LEVEL SECURITY;
CREATE POLICY "Allow full access" ON partner_accounts FOR ALL USING (true);
