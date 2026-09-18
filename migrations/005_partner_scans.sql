-- Таблица для отслеживания сканирований QR партнёров (дедупликация)
CREATE TABLE IF NOT EXISTS partner_scans (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT REFERENCES users(id) ON DELETE CASCADE,
    qr_code TEXT NOT NULL,
    category_id INTEGER,
    points_earned INTEGER DEFAULT 0,
    scanned_at TIMESTAMPTZ DEFAULT NOW(),
    UNIQUE(user_id, qr_code)
);

CREATE INDEX IF NOT EXISTS idx_partner_scans_user_id ON partner_scans(user_id);
CREATE INDEX IF NOT EXISTS idx_partner_scans_qr_code ON partner_scans(qr_code);

ALTER TABLE partner_scans ENABLE ROW LEVEL SECURITY;
CREATE POLICY "Allow full access" ON partner_scans FOR ALL USING (true);
